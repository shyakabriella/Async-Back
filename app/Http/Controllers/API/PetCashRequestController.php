<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class PetCashRequestController extends Controller
{
    /**
     * List pet cash requests
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or CEO can view pet cash requests.',
            ], 403);
        }

        if (!Schema::hasTable('pet_cash_requests')) {
            return response()->json([
                'success' => false,
                'message' => 'pet_cash_requests table does not exist yet.',
            ], 500);
        }

        $query = DB::table('pet_cash_requests')->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', trim((string) $request->status));
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', (int) $request->program_id);
        }

        if ($request->filled('requested_by')) {
            $query->where('requested_by', (int) $request->requested_by);
        }

        if ($request->filled('approved_by')) {
            $query->where('approved_by', (int) $request->approved_by);
        }

        if ($request->boolean('mine')) {
            $query->where('requested_by', (int) $authUser->id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $requests = $query->get()->map(function ($item) {
            return $this->formatRequest((array) $item);
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Pet cash requests retrieved successfully.',
            'data' => $requests,
            'summary' => [
                'total_requests' => $requests->count(),
                'pending_requests' => $requests->where('status', 'pending')->count(),
                'approved_requests' => $requests->where('status', 'approved')->count(),
                'rejected_requests' => $requests->where('status', 'rejected')->count(),
                'total_requested_amount' => (float) $requests->sum('amount'),
                'total_approved_amount' => (float) $requests->where('status', 'approved')->sum('amount'),
            ],
        ], 200);
    }

    /**
     * Create new pet cash request
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or CEO can create a pet cash request.',
            ], 403);
        }

        if (!Schema::hasTable('pet_cash_requests')) {
            return response()->json([
                'success' => false,
                'message' => 'pet_cash_requests table does not exist yet.',
            ], 500);
        }

        $validator = Validator::make($request->all(), [
            'program_id' => 'required|integer|exists:programs,id',
            'title' => 'required|string|max:255',
            'purpose' => 'required|string',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $program = Program::query()->find((int) $request->program_id);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.',
            ], 404);
        }

        $now = now();

        $payload = [
            'program_id' => (int) $request->program_id,
            'requested_by' => (int) $authUser->id,
            'title' => trim((string) $request->title),
            'purpose' => trim((string) $request->purpose),
            'description' => $request->input('description'),
            'amount' => round((float) $request->amount, 2),
            'currency' => strtoupper(trim((string) $request->input('currency', 'RWF'))),
            'status' => 'pending',
        ];

        $this->addOptionalColumn('pet_cash_requests', $payload, 'code', $this->generateRequestCode());
        $this->addOptionalColumn('pet_cash_requests', $payload, 'requested_at', $now);
        $this->addOptionalColumn('pet_cash_requests', $payload, 'created_at', $now);
        $this->addOptionalColumn('pet_cash_requests', $payload, 'updated_at', $now);

        $id = DB::table('pet_cash_requests')->insertGetId($payload);

        return response()->json([
            'success' => true,
            'message' => 'Pet cash request created successfully.',
            'data' => $this->formatRequestById($id),
        ], 201);
    }

    /**
     * Show one request
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or CEO can view this pet cash request.',
            ], 403);
        }

        $item = DB::table('pet_cash_requests')->where('id', $id)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Pet cash request not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pet cash request retrieved successfully.',
            'data' => $this->formatRequest((array) $item),
        ], 200);
    }

    /**
     * Update pending request
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or CEO can update this pet cash request.',
            ], 403);
        }

        $item = DB::table('pet_cash_requests')->where('id', $id)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Pet cash request not found.',
            ], 404);
        }

        if ((string) $item->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be updated.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'program_id' => 'nullable|integer|exists:programs,id',
            'title' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'description' => 'nullable|string',
            'amount' => 'nullable|numeric|min:1',
            'currency' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = [];

        if ($request->filled('program_id')) {
            $payload['program_id'] = (int) $request->program_id;
        }

        if ($request->filled('title')) {
            $payload['title'] = trim((string) $request->title);
        }

        if ($request->filled('purpose')) {
            $payload['purpose'] = trim((string) $request->purpose);
        }

        if (array_key_exists('description', $request->all())) {
            $payload['description'] = $request->input('description');
        }

        if ($request->filled('amount')) {
            $payload['amount'] = round((float) $request->amount, 2);
        }

        if ($request->filled('currency')) {
            $payload['currency'] = strtoupper(trim((string) $request->currency));
        }

        $this->addOptionalColumn('pet_cash_requests', $payload, 'updated_at', now());

        if (!empty($payload)) {
            DB::table('pet_cash_requests')->where('id', $id)->update($payload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pet cash request updated successfully.',
            'data' => $this->formatRequestById((int) $id),
        ], 200);
    }

    /**
     * Approve request and deduct program balance
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or CEO can approve a pet cash request.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'approval_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = DB::table('pet_cash_requests')->where('id', $id)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Pet cash request not found.',
            ], 404);
        }

        if ((string) $item->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be approved.',
            ], 422);
        }

        $balanceColumn = $this->getProgramBalanceColumn();

        if (!$balanceColumn) {
            return response()->json([
                'success' => false,
                'message' => 'No program balance column found. Add one of these columns to programs table: balance, available_balance, budget_balance, petty_cash_balance.',
            ], 422);
        }

        $program = DB::table('programs')->where('id', $item->program_id)->first();

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program linked to this request was not found.',
            ], 404);
        }

        $currentBalance = (float) ($program->{$balanceColumn} ?? 0);
        $amount = (float) $item->amount;

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Request amount must be greater than zero.',
            ], 422);
        }

        if ($currentBalance < $amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient program balance for approval.',
                'data' => [
                    'program_id' => (int) $item->program_id,
                    'current_balance' => $currentBalance,
                    'requested_amount' => $amount,
                    'balance_column' => $balanceColumn,
                ],
            ], 422);
        }

        DB::transaction(function () use ($id, $item, $authUser, $request, $balanceColumn, $currentBalance, $amount) {
            $newBalance = round($currentBalance - $amount, 2);

            $programUpdate = [
                $balanceColumn => $newBalance,
            ];

            $this->addOptionalColumn('programs', $programUpdate, 'updated_at', now());

            DB::table('programs')
                ->where('id', $item->program_id)
                ->update($programUpdate);

            $requestUpdate = [
                'status' => 'approved',
                'approved_by' => (int) $authUser->id,
            ];

            $this->addOptionalColumn('pet_cash_requests', $requestUpdate, 'approved_at', now());
            $this->addOptionalColumn('pet_cash_requests', $requestUpdate, 'approval_note', $request->input('approval_note'));
            $this->addOptionalColumn('pet_cash_requests', $requestUpdate, 'balance_before', $currentBalance);
            $this->addOptionalColumn('pet_cash_requests', $requestUpdate, 'balance_after', $newBalance);
            $this->addOptionalColumn('pet_cash_requests', $requestUpdate, 'updated_at', now());

            DB::table('pet_cash_requests')
                ->where('id', $id)
                ->update($requestUpdate);
        });

        return response()->json([
            'success' => true,
            'message' => 'Pet cash request approved successfully and program balance updated.',
            'data' => $this->formatRequestById((int) $id),
        ], 200);
    }

    /**
     * Reject request
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or CEO can reject a pet cash request.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = DB::table('pet_cash_requests')->where('id', $id)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Pet cash request not found.',
            ], 404);
        }

        if ((string) $item->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be rejected.',
            ], 422);
        }

        $payload = [
            'status' => 'rejected',
        ];

        $this->addOptionalColumn('pet_cash_requests', $payload, 'rejected_by', (int) $authUser->id);
        $this->addOptionalColumn('pet_cash_requests', $payload, 'rejected_at', now());
        $this->addOptionalColumn('pet_cash_requests', $payload, 'rejection_reason', trim((string) $request->rejection_reason));
        $this->addOptionalColumn('pet_cash_requests', $payload, 'updated_at', now());

        DB::table('pet_cash_requests')->where('id', $id)->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Pet cash request rejected successfully.',
            'data' => $this->formatRequestById((int) $id),
        ], 200);
    }

    /**
     * Delete pending request only
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or CEO can delete this pet cash request.',
            ], 403);
        }

        $item = DB::table('pet_cash_requests')->where('id', $id)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Pet cash request not found.',
            ], 404);
        }

        if ((string) $item->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be deleted.',
            ], 422);
        }

        DB::table('pet_cash_requests')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pet cash request deleted successfully.',
        ], 200);
    }

    /**
     * Format one request by ID
     */
    private function formatRequestById(int $id): ?array
    {
        $item = DB::table('pet_cash_requests')->where('id', $id)->first();

        return $item ? $this->formatRequest((array) $item) : null;
    }

    /**
     * Format request data for API response
     */
    private function formatRequest(array $item): array
    {
        $programId = (int) ($item['program_id'] ?? 0);
        $requestedById = (int) ($item['requested_by'] ?? 0);
        $approvedById = (int) ($item['approved_by'] ?? 0);
        $rejectedById = (int) ($item['rejected_by'] ?? 0);

        $program = $programId > 0 ? Program::query()->find($programId) : null;
        $requester = $requestedById > 0 ? User::query()->find($requestedById) : null;
        $approver = $approvedById > 0 ? User::query()->find($approvedById) : null;
        $rejecter = $rejectedById > 0 ? User::query()->find($rejectedById) : null;

        $balanceColumn = $this->getProgramBalanceColumn();
        $currentProgramBalance = null;

        if ($program && $balanceColumn) {
            $currentProgramBalance = (float) ($program->{$balanceColumn} ?? 0);
        }

        return [
            'id' => (int) ($item['id'] ?? 0),
            'code' => $item['code'] ?? null,
            'title' => $item['title'] ?? null,
            'purpose' => $item['purpose'] ?? null,
            'description' => $item['description'] ?? null,
            'amount' => (float) ($item['amount'] ?? 0),
            'currency' => $item['currency'] ?? 'RWF',
            'status' => $item['status'] ?? 'pending',

            'program' => $program ? [
                'id' => $program->id,
                'name' => $program->name ?? null,
                'balance_column' => $balanceColumn,
                'current_balance' => $currentProgramBalance,
            ] : null,

            'requested_by' => $requester ? [
                'id' => $requester->id,
                'name' => $requester->name,
                'email' => $requester->email,
                'phone' => $requester->phone,
            ] : null,

            'approved_by' => $approver ? [
                'id' => $approver->id,
                'name' => $approver->name,
                'email' => $approver->email,
                'phone' => $approver->phone,
            ] : null,

            'rejected_by' => $rejecter ? [
                'id' => $rejecter->id,
                'name' => $rejecter->name,
                'email' => $rejecter->email,
                'phone' => $rejecter->phone,
            ] : null,

            'approval_note' => $item['approval_note'] ?? null,
            'rejection_reason' => $item['rejection_reason'] ?? null,
            'balance_before' => array_key_exists('balance_before', $item) && $item['balance_before'] !== null
                ? (float) $item['balance_before']
                : null,
            'balance_after' => array_key_exists('balance_after', $item) && $item['balance_after'] !== null
                ? (float) $item['balance_after']
                : null,

            'requested_at' => $item['requested_at'] ?? ($item['created_at'] ?? null),
            'approved_at' => $item['approved_at'] ?? null,
            'rejected_at' => $item['rejected_at'] ?? null,
            'created_at' => $item['created_at'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
        ];
    }

    /**
     * Check if user is admin or CEO
     */
    private function isAdminish(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $user->loadMissing('roles:id,name,slug');

        return $user->roles->contains(function ($role) {
            return in_array((string) $role->slug, ['admin', 'ceo'], true);
        });
    }

    /**
     * Detect usable balance column on programs table
     */
    private function getProgramBalanceColumn(): ?string
    {
        $candidates = [
            'balance',
            'available_balance',
            'budget_balance',
            'petty_cash_balance',
        ];

        foreach ($candidates as $column) {
            if (Schema::hasColumn('programs', $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Add optional column only if it exists in table
     */
    private function addOptionalColumn(string $table, array &$payload, string $column, mixed $value): void
    {
        if (Schema::hasColumn($table, $column)) {
            $payload[$column] = $value;
        }
    }

    /**
     * Generate readable request code
     */
    private function generateRequestCode(): string
    {
        $date = now()->format('Ymd');

        $latest = DB::table('pet_cash_requests')
            ->where('code', 'like', 'PCR-' . $date . '-%')
            ->orderByDesc('id')
            ->value('code');

        $next = 1;

        if ($latest) {
            $parts = explode('-', $latest);
            $lastNumber = (int) end($parts);
            $next = $lastNumber + 1;
        }

        return 'PCR-' . $date . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}