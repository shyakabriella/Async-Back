<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TrainerAttendance;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TrainerAttendanceController extends Controller
{
    /**
     * List trainer attendance records
     */
    public function index(Request $request): JsonResponse
    {
        $query = TrainerAttendance::with([
            'trainer:id,name,email,phone,daily_rate',
            'markedByUser:id,name',
            'paidByUser:id,name',
        ])->latest('attendance_date');

        if ($request->filled('trainer_id')) {
            $query->where('trainer_id', $request->trainer_id);
        }

        if ($request->filled('attendance_date')) {
            $query->whereDate('attendance_date', $request->attendance_date);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('attendance_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('attendance_date', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('is_paid')) {
            $query->where('is_paid', filter_var($request->is_paid, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->whereHas('trainer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $records = $query->get();

        $summary = [
            'total_records' => $records->count(),
            'present' => $records->where('status', 'Present')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'excused' => $records->where('status', 'Excused')->count(),
            'not_marked' => $records->where('status', 'Not Marked')->count(),
            'total_salary' => (float) $records->sum('salary_amount'),
            'total_paid' => (float) $records->where('is_paid', true)->sum('salary_amount'),
            'total_unpaid' => (float) $records->where('is_paid', false)->sum('salary_amount'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Trainer attendance records retrieved successfully.',
            'data' => $records,
            'summary' => $summary,
        ], 200);
    }

    /**
     * Save trainer attendance
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trainer_id' => 'required|exists:users,id',
            'attendance_date' => 'required|date',
            'status' => 'required|in:Present,Absent,Late,Excused,Not Marked',
            'check_in_at' => 'nullable|date',
            'check_out_at' => 'nullable|date|after_or_equal:check_in_at',
            'note' => 'nullable|string',
            'daily_rate' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $trainer = User::with('roles:id,name,slug')->find($data['trainer_id']);

        if (!$trainer) {
            return response()->json([
                'success' => false,
                'message' => 'Trainer not found.',
            ], 404);
        }

        if (!$this->userIsTrainer($trainer)) {
            return response()->json([
                'success' => false,
                'message' => 'Selected user is not a trainer.',
            ], 422);
        }

        $existing = TrainerAttendance::where('trainer_id', $trainer->id)
            ->whereDate('attendance_date', $data['attendance_date'])
            ->first();

        if ($existing && $existing->is_paid) {
            return response()->json([
                'success' => false,
                'message' => 'This trainer attendance has already been paid and cannot be changed.',
            ], 422);
        }

        $dailyRate = array_key_exists('daily_rate', $data)
            ? (float) $data['daily_rate']
            : (float) ($trainer->daily_rate ?? 0);

        $salaryAmount = $this->calculateSalary($dailyRate, $data['status']);

        $attendance = TrainerAttendance::updateOrCreate(
            [
                'trainer_id' => $trainer->id,
                'attendance_date' => $data['attendance_date'],
            ],
            [
                'status' => $data['status'],
                'check_in_at' => $data['check_in_at'] ?? null,
                'check_out_at' => $data['check_out_at'] ?? null,
                'note' => $data['note'] ?? null,
                'daily_rate' => $dailyRate,
                'salary_amount' => $salaryAmount,
                'marked_by' => auth()->id(),
            ]
        );

        $attendance->load([
            'trainer:id,name,email,phone,daily_rate',
            'markedByUser:id,name',
            'paidByUser:id,name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trainer attendance saved successfully.',
            'data' => $attendance,
        ], 200);
    }

    /**
     * Show one trainer attendance
     */
    public function show(string $id): JsonResponse
    {
        $attendance = TrainerAttendance::with([
            'trainer:id,name,email,phone,daily_rate',
            'markedByUser:id,name',
            'paidByUser:id,name',
        ])->find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Trainer attendance record not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trainer attendance record retrieved successfully.',
            'data' => $attendance,
        ], 200);
    }

    /**
     * Update trainer attendance
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $attendance = TrainerAttendance::with('trainer.roles:id,name,slug')->find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Trainer attendance record not found.',
            ], 404);
        }

        if ($attendance->is_paid) {
            return response()->json([
                'success' => false,
                'message' => 'Paid trainer attendance cannot be updated.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:Present,Absent,Late,Excused,Not Marked',
            'check_in_at' => 'nullable|date',
            'check_out_at' => 'nullable|date|after_or_equal:check_in_at',
            'note' => 'nullable|string',
            'daily_rate' => 'nullable|numeric|min:0',
            'attendance_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $status = $data['status'] ?? $attendance->status;
        $dailyRate = array_key_exists('daily_rate', $data)
            ? (float) $data['daily_rate']
            : (float) $attendance->daily_rate;

        $attendance->update([
            'attendance_date' => $data['attendance_date'] ?? $attendance->attendance_date,
            'status' => $status,
            'check_in_at' => array_key_exists('check_in_at', $data)
                ? $data['check_in_at']
                : $attendance->check_in_at,
            'check_out_at' => array_key_exists('check_out_at', $data)
                ? $data['check_out_at']
                : $attendance->check_out_at,
            'note' => array_key_exists('note', $data)
                ? $data['note']
                : $attendance->note,
            'daily_rate' => $dailyRate,
            'salary_amount' => $this->calculateSalary($dailyRate, $status),
            'marked_by' => auth()->id(),
        ]);

        $attendance->load([
            'trainer:id,name,email,phone,daily_rate',
            'markedByUser:id,name',
            'paidByUser:id,name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trainer attendance updated successfully.',
            'data' => $attendance,
        ], 200);
    }

    /**
     * Pay trainer salary into wallet
     */
    public function pay(string $id): JsonResponse
    {
        $attendance = TrainerAttendance::with([
            'trainer:id,name,email,phone,daily_rate',
        ])->find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Trainer attendance record not found.',
            ], 404);
        }

        if ($attendance->is_paid) {
            return response()->json([
                'success' => false,
                'message' => 'This trainer attendance has already been paid.',
            ], 422);
        }

        $trainer = User::with('roles:id,name,slug')->find($attendance->trainer_id);

        if (!$trainer || !$this->userIsTrainer($trainer)) {
            return response()->json([
                'success' => false,
                'message' => 'Valid trainer not found.',
            ], 422);
        }

        $amount = (float) $attendance->salary_amount;

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This attendance does not generate salary.',
            ], 422);
        }

        $result = DB::transaction(function () use ($attendance, $trainer, $amount) {
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $trainer->id],
                [
                    'balance' => 0,
                    'currency' => 'RWF',
                    'status' => 'active',
                ]
            );

            $wallet->increment('balance', $amount);
            $wallet->refresh();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $trainer->id,
                'amount' => $amount,
                'type' => 'credit',
                'source' => 'trainer_salary',
                'reference' => 'trainer-attendance-' . $attendance->id,
                'note' => 'Trainer salary for attendance on ' . $attendance->attendance_date,
                'created_by' => auth()->id(),
            ]);

            $attendance->update([
                'is_paid' => true,
                'paid_at' => now(),
                'paid_by' => auth()->id(),
            ]);

            return [
                'wallet' => $wallet,
                'attendance' => $attendance->fresh([
                    'trainer:id,name,email,phone,daily_rate',
                    'markedByUser:id,name',
                    'paidByUser:id,name',
                ]),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Trainer salary paid to wallet successfully.',
            'data' => $result,
        ], 200);
    }

    /**
     * Delete trainer attendance
     */
    public function destroy(string $id): JsonResponse
    {
        $attendance = TrainerAttendance::find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Trainer attendance record not found.',
            ], 404);
        }

        if ($attendance->is_paid) {
            return response()->json([
                'success' => false,
                'message' => 'Paid trainer attendance cannot be deleted.',
            ], 422);
        }

        $attendance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Trainer attendance deleted successfully.',
        ], 200);
    }

    /**
     * Check if user has trainer role
     */
    private function userIsTrainer(User $user): bool
    {
        $user->loadMissing('roles:id,name,slug');

        return $user->roles->contains(function ($role) {
            return (string) $role->slug === 'trainer';
        });
    }

    /**
     * Salary logic
     * Present = 100%
     * Late = 50%
     * Excused = 0
     * Absent = 0
     * Not Marked = 0
     */
    private function calculateSalary(float $dailyRate, string $status): float
    {
        return match ($status) {
            'Present' => round($dailyRate, 2),
            'Late' => round($dailyRate * 0.5, 2),
            'Excused', 'Absent', 'Not Marked' => 0,
            default => 0,
        };
    }
}