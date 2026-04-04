<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\AgentProfile;
use App\Models\AgentStudentReferral;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\AccountSetupNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgentController extends BaseController
{
    /**
     * Admin / CEO: list all agents
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or CEO can view agents.',
            ], 403);
        }

        $agents = User::with([
            'roles:id,name,slug',
            'agentProfile:id,user_id,image,commission_percentage,created_by',
        ])
            ->whereHas('roles', function ($q) {
                $q->where('slug', 'agent');
            })
            ->latest()
            ->get()
            ->map(function (User $agent) {
                $totals = $this->syncAgentFinancials($agent);
                $formatted = $this->formatAgent(
                    $agent->fresh([
                        'roles:id,name,slug',
                        'agentProfile:id,user_id,image,commission_percentage,created_by',
                    ])
                );

                $formatted['stats'] = [
                    'total_students' => $totals['total_students'],
                    'approved_students' => $totals['approved_students'],
                    'pending_students' => $totals['pending_students'],
                    'total_amount_paid' => $totals['total_amount_paid'],
                    'total_commission' => $totals['total_commission'],
                    'expected_commission' => $totals['expected_commission'],
                ];

                return $formatted;
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Agents retrieved successfully.',
            'data' => $agents,
        ], 200);
    }

    /**
     * Admin / CEO: create agent
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or CEO can create agents.',
            ], 403);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'phone' => 'required|string|max:20|unique:users,phone',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'commission_percentage' => 'nullable|numeric|min:0|max:100',
                'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
                'is_active' => 'nullable|boolean',
            ]
        );

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $status = $request->input('status', 'active');
        $isActive = $request->has('is_active')
            ? (bool) $request->boolean('is_active')
            : $status === 'active';

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => trim((string) $request->name),
                'email' => trim((string) $request->email),
                'phone' => trim((string) $request->phone),
                'password' => Hash::make(Str::random(32)),
                'status' => $status,
                'is_active' => $isActive,
                'last_login_at' => null,
            ]);

            $this->assignRole($user, 'agent');
            $this->ensureAgentWallet($user);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('agents', 'public');
            }

            AgentProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'image' => $imagePath,
                    'commission_percentage' => (float) $request->input('commission_percentage', 0),
                    'created_by' => $authUser?->id,
                ]
            );

            $emailSetupSent = false;
            $emailSetupMessage = 'Agent created, but account setup email could not be sent.';

            try {
                $emailSetupSent = $this->sendAccountSetupEmail($user);
                $emailSetupMessage = $emailSetupSent
                    ? 'Agent created successfully and account setup email sent.'
                    : 'Agent created, but account setup email could not be sent.';
            } catch (\Throwable $e) {
                Log::error('Failed to send agent account setup email.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }

            DB::commit();

            $user->load([
                'roles:id,name,slug',
                'agentProfile:id,user_id,image,commission_percentage,created_by',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agent created successfully.',
                'data' => [
                    'agent' => $this->formatAgent($user),
                    'email_setup_sent' => $emailSetupSent,
                    'email_setup_message' => $emailSetupMessage,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to create agent.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create agent.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin / CEO: show one agent
     * Agent can also view his own record
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();

        $agent = User::with([
            'roles:id,name,slug',
            'agentProfile:id,user_id,image,commission_percentage,created_by',
        ])->find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found.',
            ], 404);
        }

        if (!$this->hasRole($agent, 'agent')) {
            return response()->json([
                'success' => false,
                'message' => 'The selected user is not an agent.',
            ], 422);
        }

        $isSelf = $authUser && (int) $authUser->id === (int) $agent->id;

        if (!$isSelf && !$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view this agent.',
            ], 403);
        }

        $totals = $this->syncAgentFinancials($agent);

        return response()->json([
            'success' => true,
            'message' => 'Agent retrieved successfully.',
            'data' => [
                'agent' => $this->formatAgent($agent),
                'stats' => $totals,
            ],
        ], 200);
    }

    /**
     * Admin / CEO: update one agent
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();

        if (!$this->isAdminish($authUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin or CEO can update agents.',
            ], 403);
        }

        $agent = User::with([
            'roles:id,name,slug',
            'agentProfile:id,user_id,image,commission_percentage,created_by',
        ])->find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found.',
            ], 404);
        }

        if (!$this->hasRole($agent, 'agent')) {
            return response()->json([
                'success' => false,
                'message' => 'The selected user is not an agent.',
            ], 422);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'sometimes|required|string|max:255',
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($agent->id),
                ],
                'phone' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('users', 'phone')->ignore($agent->id),
                ],
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'commission_percentage' => 'nullable|numeric|min:0|max:100',
                'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
                'is_active' => 'nullable|boolean',
            ]
        );

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        DB::beginTransaction();

        try {
            if ($request->filled('name')) {
                $agent->name = trim((string) $request->name);
            }

            if ($request->filled('email')) {
                $agent->email = trim((string) $request->email);
            }

            if ($request->filled('phone')) {
                $agent->phone = trim((string) $request->phone);
            }

            if ($request->filled('status')) {
                $agent->status = $request->input('status');
            }

            if ($request->has('is_active')) {
                $agent->is_active = (bool) $request->boolean('is_active');
            } elseif ($request->filled('status')) {
                $agent->is_active = $request->input('status') === 'active';
            }

            $agent->save();

            $profilePayload = [
                'commission_percentage' => (float) $request->input(
                    'commission_percentage',
                    optional($agent->agentProfile)->commission_percentage ?? 0
                ),
                'created_by' => optional($agent->agentProfile)->created_by ?? $authUser?->id,
            ];

            if ($request->hasFile('image')) {
                $profilePayload['image'] = $request->file('image')->store('agents', 'public');
            } elseif ($agent->agentProfile && $agent->agentProfile->image) {
                $profilePayload['image'] = $agent->agentProfile->image;
            }

            AgentProfile::updateOrCreate(
                ['user_id' => $agent->id],
                $profilePayload
            );

            $this->ensureAgentWallet($agent);
            $this->syncAgentFinancials($agent);

            DB::commit();

            $agent->refresh()->load([
                'roles:id,name,slug',
                'agentProfile:id,user_id,image,commission_percentage,created_by',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Agent updated successfully.',
                'data' => $this->formatAgent($agent),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to update agent.', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Agent: dashboard (only own data)
     */
    public function myDashboard(Request $request): JsonResponse
    {
        $agent = $request->user();

        if (!$agent || !$this->hasRole($agent, 'agent')) {
            return response()->json([
                'success' => false,
                'message' => 'Only an agent can view this dashboard.',
            ], 403);
        }

        $agent->load([
            'roles:id,name,slug',
            'agentProfile:id,user_id,image,commission_percentage,created_by',
        ]);

        $totals = $this->syncAgentFinancials($agent);
        $wallet = Wallet::where('user_id', $agent->id)->first();

        $referrals = AgentStudentReferral::with([
            'student:id,name,email,phone,status,is_active,created_at',
            $this->programRelationSelect(),
        ])
            ->where('agent_user_id', $agent->id)
            ->latest()
            ->get();

        $data = [
            'agent' => $this->formatAgent($agent),
            'wallet' => [
                'balance' => $wallet ? (float) $wallet->balance : 0,
                'currency' => $wallet ? $wallet->currency : 'RWF',
                'status' => $wallet ? $wallet->status : 'active',
            ],
            'stats' => $totals,
            'students' => $referrals->map(function (AgentStudentReferral $referral) {
                $programPrice = $this->resolveProgramPrice($referral->program);

                return [
                    'referral_id' => $referral->id,
                    'student_id' => $referral->student_user_id,
                    'student_name' => optional($referral->student)->name,
                    'student_email' => optional($referral->student)->email,
                    'student_phone' => optional($referral->student)->phone,
                    'program' => $referral->program ? [
                        'id' => $referral->program->id,
                        'name' => $referral->program->name,
                        'slug' => $referral->program->slug,
                        'price' => $programPrice,
                    ] : null,
                    'program_price' => $programPrice,
                    'amount_paid' => (float) $referral->amount_paid,
                    'commission_percentage' => (float) $referral->commission_percentage,
                    'commission_amount' => (float) $referral->commission_amount,
                    'currency' => $referral->currency ?: 'RWF',
                    'status' => $referral->status,
                    'registered_at' => optional($referral->registered_at)->format('Y-m-d H:i:s'),
                    'created_at' => optional($referral->created_at)->format('Y-m-d H:i:s'),
                ];
            })->values(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Agent dashboard retrieved successfully.',
            'data' => $data,
        ], 200);
    }

    /**
     * Agent: register student under himself
     */
    public function registerStudent(Request $request): JsonResponse
    {
        $agent = $request->user();

        if (!$agent || !$this->hasRole($agent, 'agent')) {
            return response()->json([
                'success' => false,
                'message' => 'Only an agent can register a student.',
            ], 403);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255|unique:users,email|required_without:phone',
                'phone' => 'nullable|string|max:20|unique:users,phone|required_without:email',
                'program_id' => 'required|integer|exists:programs,id',
                'status' => ['nullable', Rule::in(['pending', 'approved', 'paid', 'rejected'])],
                'commission_percentage' => 'nullable|numeric|min:0|max:100',
                'notes' => 'nullable|string|max:5000',
            ],
            [
                'email.required_without' => 'Email or phone is required.',
                'phone.required_without' => 'Phone or email is required.',
            ]
        );

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $agent->load('agentProfile');

        $program = Program::find((int) $request->input('program_id'));
        $programPrice = $this->resolveProgramPrice($program);

        $agentCommission = (float) optional($agent->agentProfile)->commission_percentage;
        $commissionPercentage = (float) $request->input('commission_percentage', $agentCommission);
        $commissionPercentage = max(0, min(100, $commissionPercentage));

        $amountPaid = max(0, $programPrice);
        $commissionAmount = $this->calculateCommissionAmount($amountPaid, $commissionPercentage);

        // student account remains active
        $studentAccountStatus = 'active';
        $studentIsActive = true;

        // referral must remain pending until approval
        $referralStatus = 'pending';

        DB::beginTransaction();

        try {
            $student = User::create([
                'name' => trim((string) $request->name),
                'email' => $request->email ? trim((string) $request->email) : null,
                'phone' => $request->phone ? trim((string) $request->phone) : null,
                'password' => Hash::make(Str::random(32)),
                'status' => $studentAccountStatus,
                'is_active' => $studentIsActive,
                'last_login_at' => null,
            ]);

            $this->assignRole($student, 'student');

            if (Schema::hasTable('program_user')) {
                $student->programs()->sync([(int) $program->id]);
            }

            $referral = AgentStudentReferral::create([
                'agent_user_id' => $agent->id,
                'student_user_id' => $student->id,
                'program_id' => $program->id,
                'amount_paid' => $amountPaid,
                'commission_percentage' => $commissionPercentage,
                'commission_amount' => $commissionAmount,
                'currency' => 'RWF',
                'status' => $referralStatus,
                'notes' => $request->input('notes'),
                'registered_at' => now(),
            ]);

            $emailSetupSent = false;
            $emailSetupMessage = null;

            if (!empty($student->email)) {
                try {
                    $emailSetupSent = $this->sendAccountSetupEmail($student);
                    $emailSetupMessage = $emailSetupSent
                        ? 'Student created successfully and account setup email sent.'
                        : 'Student created, but setup email could not be sent.';
                } catch (\Throwable $e) {
                    Log::error('Failed to send student account setup email.', [
                        'student_id' => $student->id,
                        'email' => $student->email,
                        'error' => $e->getMessage(),
                    ]);

                    $emailSetupSent = false;
                    $emailSetupMessage = 'Student created, but setup email could not be sent.';
                }
            }

            $totals = $this->syncAgentFinancials($agent);

            DB::commit();

            $referral->refresh()->load([
                'student:id,name,email,phone,status,is_active,created_at',
                $this->programRelationSelect(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Student registered under agent successfully.',
                'data' => [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'email' => $student->email,
                        'phone' => $student->phone,
                    ],
                    'referral' => [
                        'id' => $referral->id,
                        'program' => $referral->program ? [
                            'id' => $referral->program->id,
                            'name' => $referral->program->name,
                            'slug' => $referral->program->slug,
                            'price' => $this->resolveProgramPrice($referral->program),
                        ] : null,
                        'amount_paid' => (float) $referral->amount_paid,
                        'commission_percentage' => (float) $referral->commission_percentage,
                        'commission_amount' => (float) $referral->commission_amount,
                        'currency' => $referral->currency,
                        'status' => $referral->status,
                    ],
                    'wallet' => [
                        'balance' => $totals['total_commission'],
                        'currency' => 'RWF',
                        'status' => 'active',
                    ],
                    'email_setup_sent' => $emailSetupSent,
                    'email_setup_message' => $emailSetupMessage,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Agent failed to register student.', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register student under agent.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helpers
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

    private function hasRole(User $user, string $slug): bool
    {
        $user->loadMissing('roles:id,name,slug');

        return $user->roles->contains(function ($role) use ($slug) {
            return (string) $role->slug === $slug;
        });
    }

    private function assignRole(User $user, string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->first();

        if ($role) {
            $user->roles()->sync([$role->id]);
        }
    }

    private function ensureAgentWallet(User $user): void
    {
        if (!Schema::hasTable('wallets')) {
            return;
        }

        if (!$this->hasRole($user, 'agent')) {
            return;
        }

        Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'currency' => 'RWF',
                'status' => 'active',
            ]
        );
    }

    private function programRelationSelect(): string
    {
        $columns = ['id', 'name', 'slug'];

        if (Schema::hasTable('programs') && Schema::hasColumn('programs', 'price')) {
            $columns[] = 'price';
        }

        return 'program:' . implode(',', $columns);
    }

    private function resolveProgramPrice(?Program $program): float
    {
        if (!$program) {
            return 0;
        }

        return Schema::hasTable('programs') && Schema::hasColumn('programs', 'price')
            ? (float) ($program->price ?? 0)
            : 0;
    }

    private function calculateCommissionAmount(float $amountPaid, float $commissionPercentage): float
    {
        $amountPaid = max(0, $amountPaid);
        $commissionPercentage = max(0, min(100, $commissionPercentage));

        return round(($amountPaid * $commissionPercentage) / 100, 2);
    }

    private function isApprovedReferralStatus(?string $status): bool
    {
        return in_array(
            strtolower(trim((string) $status)),
            ['approved', 'paid'],
            true
        );
    }

    private function isPendingReferralStatus(?string $status): bool
    {
        return strtolower(trim((string) $status)) === 'pending';
    }

    private function syncAgentFinancials(User $agent): array
    {
        $agent->loadMissing('agentProfile:id,user_id,commission_percentage');

        $this->ensureAgentWallet($agent);

        $commissionPercentage = max(
            0,
            min(100, (float) optional($agent->agentProfile)->commission_percentage)
        );

        $referrals = AgentStudentReferral::with([$this->programRelationSelect()])
            ->where('agent_user_id', $agent->id)
            ->get();

        $totalStudents = $referrals->count();
        $approvedStudents = 0;
        $pendingStudents = 0;
        $totalAmountPaid = 0;
        $totalCommission = 0;
        $expectedCommission = 0;

        foreach ($referrals as $referral) {
            $programPrice = $this->resolveProgramPrice($referral->program);
            $amountPaid = $programPrice > 0
                ? $programPrice
                : (float) ($referral->amount_paid ?? 0);

            $amountPaid = round(max(0, $amountPaid), 2);
            $commissionAmount = $this->calculateCommissionAmount($amountPaid, $commissionPercentage);

            $currentStatus = strtolower(trim((string) $referral->status));

            if (in_array($currentStatus, ['approved', 'paid', 'pending', 'rejected'], true)) {
                $status = $currentStatus;
            } else {
                $status = 'pending';
            }

            $dirty = false;

            if ((float) $referral->amount_paid !== $amountPaid) {
                $referral->amount_paid = $amountPaid;
                $dirty = true;
            }

            if ((float) $referral->commission_percentage !== $commissionPercentage) {
                $referral->commission_percentage = $commissionPercentage;
                $dirty = true;
            }

            if ((float) $referral->commission_amount !== $commissionAmount) {
                $referral->commission_amount = $commissionAmount;
                $dirty = true;
            }

            if ((string) $referral->currency !== 'RWF') {
                $referral->currency = 'RWF';
                $dirty = true;
            }

            if ((string) $referral->status !== (string) $status) {
                $referral->status = $status;
                $dirty = true;
            }

            if ($dirty) {
                $referral->save();
            }

            if ($this->isApprovedReferralStatus($status)) {
                $approvedStudents++;
                $totalAmountPaid += (float) $referral->amount_paid;
                $totalCommission += (float) $referral->commission_amount;
            } elseif ($this->isPendingReferralStatus($status)) {
                $pendingStudents++;
                $expectedCommission += (float) $referral->commission_amount;
            }
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $agent->id],
            [
                'balance' => 0,
                'currency' => 'RWF',
                'status' => 'active',
            ]
        );

        $wallet->balance = round($totalCommission, 2);
        $wallet->currency = 'RWF';
        $wallet->status = $wallet->status ?: 'active';
        $wallet->save();

        return [
            'total_students' => $totalStudents,
            'approved_students' => $approvedStudents,
            'pending_students' => $pendingStudents,
            'total_amount_paid' => round($totalAmountPaid, 2),
            'total_commission' => round($totalCommission, 2),
            'expected_commission' => round($expectedCommission, 2),
        ];
    }

    private function sendAccountSetupEmail(User $user): bool
    {
        if (empty($user->email)) {
            return false;
        }

        $token = Password::broker()->createToken($user);

        $user->notify(new AccountSetupNotification($token, $user->email));

        return true;
    }

    private function formatAgent(User $agent): array
    {
        $agent->loadMissing([
            'roles:id,name,slug',
            'agentProfile:id,user_id,image,commission_percentage,created_by',
        ]);

        $wallet = Wallet::where('user_id', $agent->id)->first();

        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'phone' => $agent->phone,
            'status' => $agent->status,
            'is_active' => (bool) $agent->is_active,
            'created_at' => optional($agent->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($agent->updated_at)->format('Y-m-d H:i:s'),
            'role' => [
                'slug' => 'agent',
                'name' => 'Agent',
            ],
            'profile' => [
                'image' => optional($agent->agentProfile)->image,
                'image_url' => optional($agent->agentProfile)->image_url,
                'commission_percentage' => (float) optional($agent->agentProfile)->commission_percentage,
            ],
            'wallet' => [
                'balance' => $wallet ? (float) $wallet->balance : 0,
                'currency' => $wallet ? $wallet->currency : 'RWF',
                'status' => $wallet ? $wallet->status : 'active',
            ],
        ];
    }
}