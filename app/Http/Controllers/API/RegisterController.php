<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\AccountSetupNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterController extends BaseController
{
    /**
     * Public register API
     * For safety, public registration always creates a student account.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'     => 'required|string|max:255',
                'email'    => 'nullable|email|max:255|unique:users,email|required_without:phone',
                'phone'    => 'nullable|string|max:20|unique:users,phone|required_without:email',
                'password' => 'required|string|min:8',
            ],
            [
                'email.required_without' => 'Email or phone is required.',
                'phone.required_without' => 'Phone or email is required.',
            ]
        );

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $userPayload = [
            'name'          => trim((string) $request->name),
            'email'         => $request->email,
            'phone'         => $request->phone,
            'password'      => Hash::make($request->password),
            'status'        => 'active',
            'is_active'     => true,
            'last_login_at' => null,
        ];

        if (Schema::hasColumn('users', 'daily_rate')) {
            $userPayload['daily_rate'] = 0;
        }

        $user = User::create($userPayload);

        $this->assignUserRole($user, 'student');
        $this->loadUserRelations($user);
        $this->ensureTrainerWallet($user);

        $emailSetupSent = false;
        $emailSetupMessage = null;

        if (!empty($user->email)) {
            try {
                $emailSetupSent = $this->sendAccountSetupEmail($user);
                $emailSetupMessage = $emailSetupSent
                    ? 'Account setup email sent successfully.'
                    : 'User created, but account setup email could not be sent.';
            } catch (\Throwable $e) {
                Log::error('Failed to send account setup email.', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'error'   => $e->getMessage(),
                ]);

                $emailSetupSent = false;
                $emailSetupMessage = 'User created, but account setup email could not be sent.';
            }
        }

        $success = [
            'token' => $user->createToken('AsyncAfrica')->plainTextToken,
            'user'  => $this->formatUser($user),
            'email_setup_sent'    => $emailSetupSent,
            'email_setup_message' => $emailSetupMessage,
        ];

        return $this->sendResponse($success, 'User registered successfully.');
    }

    /**
     * Login API
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email'    => 'nullable|email|required_without:phone',
                'phone'    => 'nullable|string|required_without:email',
                'password' => 'required|string',
            ],
            [
                'email.required_without' => 'Email or phone is required.',
                'phone.required_without' => 'Phone or email is required.',
            ]
        );

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $loginField = $request->filled('email') ? 'email' : 'phone';

        $credentials = [
            $loginField => $request->$loginField,
            'password'  => $request->password,
        ];

        if (!Auth::attempt($credentials)) {
            return $this->sendError('Unauthorised.', [
                'error' => 'Invalid credentials',
            ], 401);
        }

        /** @var \App\Models\User|null $user */
        $user = User::find(Auth::id());

        if (!$user) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not found after authentication.',
            ], 401);
        }

        if (!$user->is_active || $user->status !== 'active') {
            Auth::logout();

            return $this->sendError('Account access denied.', [
                'error' => 'Your account is inactive or suspended.',
            ], 403);
        }

        $user->update([
            'last_login_at' => now(),
        ]);

        $user->refresh();
        $this->loadUserRelations($user);
        $this->ensureTrainerWallet($user);

        $primaryRole = $this->resolvePrimaryRole($user->roles);

        if (!$primaryRole) {
            Auth::logout();

            return $this->sendError('Account role error.', [
                'error' => 'No valid role is assigned to this user.',
            ], 403);
        }

        $success = [
            'token' => $user->createToken('AsyncAfrica')->plainTextToken,
            'user'  => $this->formatUser($user),
        ];

        return $this->sendResponse($success, 'User login successfully.');
    }

    /**
     * Send forgot password email
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        try {
            Password::broker()->sendResetLink([
                'email' => $request->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send forgot password email.', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'If the email exists, a password reset link has been sent.',
        ], 200);
    }

    /**
     * Reset password API
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $status = Password::reset(
            [
                'email' => $request->email,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'token' => $request->token,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully.',
        ], 200);
    }

    /**
     * Current authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $this->loadUserRelations($user);
        $this->ensureTrainerWallet($user);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated user retrieved successfully.',
            'data' => $this->formatUser($user),
        ], 200);
    }

    /**
     * Logout current token
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }

        /**
     * List users
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $query = User::with($this->buildUserRelations())->latest();

        if ($authUser && $this->hasRole($authUser, 'agent')) {
            $requestedRole = trim((string) $request->input('role', 'student'));

            if ($request->filled('role') && $requestedRole !== 'student') {
                return response()->json([
                    'success' => false,
                    'message' => 'Agents can only view students registered under them.',
                ], 403);
            }

            $query->whereHas('roles', function ($q) {
                $q->where('slug', 'student');
            });

            if (Schema::hasTable('agent_student_referrals')) {
                $query->whereHas('referredByAgent', function ($q) use ($authUser) {
                    $q->where('agent_user_id', $authUser->id);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $roleSlug = trim((string) $request->role);
            $query->whereHas('roles', function ($q) use ($roleSlug) {
                $q->where('slug', $roleSlug);
            });
        }

        if ($request->filled('program_id') && Schema::hasTable('program_user')) {
            $programId = (int) $request->program_id;
            $query->whereHas('programs', function ($q) use ($programId) {
                $q->where('programs.id', $programId);
            });
        }

        $users = $query->get()->map(function (User $user) {
            $this->ensureTrainerWallet($user);
            return $this->formatUser($user);
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => $users,
        ], 200);
    }
    /**
     * Admin create user
     * Password is auto-generated internally.
     * Email is required so user can receive setup/reset link.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'          => 'required|string|max:255',
                'email'         => 'required|email|max:255|unique:users,email',
                'phone'         => 'nullable|string|max:20|unique:users,phone',
                'status'        => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
                'is_active'     => 'nullable|boolean',
                'role_slug'     => ['nullable', 'string', Rule::in(['admin', 'ceo', 'trainer', 'student', 'agent', 'school_owner'])],
                'program_ids'   => 'nullable|array',
                'program_ids.*' => 'integer|exists:programs,id',
                'daily_rate'    => 'nullable|numeric|min:0',
            ],
            [
                'email.required' => 'Email is required so the user can receive account setup instructions.',
            ]
        );

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $status = $request->input('status', 'active');
        $isActive = $request->has('is_active')
            ? (bool) $request->boolean('is_active')
            : $status === 'active';

        $generatedPassword = Str::random(32);

        $userPayload = [
            'name'          => trim((string) $request->name),
            'email'         => trim((string) $request->email),
            'phone'         => $request->phone,
            'password'      => Hash::make($generatedPassword),
            'status'        => $status,
            'is_active'     => $isActive,
            'last_login_at' => null,
        ];

        if (Schema::hasColumn('users', 'daily_rate')) {
            $userPayload['daily_rate'] = (float) $request->input('daily_rate', 0);
        }

        $user = User::create($userPayload);

        $this->assignUserRole($user, $request->input('role_slug', 'student'));
        $this->ensureTrainerWallet($user);
        $this->syncUserPrograms($user, $request->input('program_ids', []));
        $this->loadUserRelations($user);

        $emailSetupSent = false;
        $emailSetupMessage = 'User created successfully, but account setup email could not be sent.';

        try {
            $emailSetupSent = $this->sendAccountSetupEmail($user);
            $emailSetupMessage = $emailSetupSent
                ? 'User created successfully. Account setup email sent successfully.'
                : 'User created successfully, but account setup email could not be sent.';
        } catch (\Throwable $e) {
            Log::error('Failed to send account setup email for admin-created user.', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => [
                'user' => $this->formatUser($user),
                'email_setup_sent' => $emailSetupSent,
                'email_setup_message' => $emailSetupMessage,
            ],
        ], 201);
    }

        /**
     * Show one user
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $authUser = $request->user();
        $user = User::with($this->buildUserRelations())->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if ($authUser && $this->hasRole($authUser, 'agent')) {
            $isOwnRecord = (int) $authUser->id === (int) $user->id;
            $isOwnStudent = false;

            if (!$isOwnRecord && Schema::hasTable('agent_student_referrals')) {
                $user->loadMissing('referredByAgent');
                $isOwnStudent = (int) optional($user->referredByAgent)->agent_user_id === (int) $authUser->id;
            }

            if (!$isOwnRecord && !$isOwnStudent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agents can only view their own profile or students registered under them.',
                ], 403);
            }
        }

        $this->ensureTrainerWallet($user);

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => $this->formatUser($user),
        ], 200);
    }
    /**
     * Update user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::with($this->buildUserRelations())->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'sometimes|required|string|max:255',
                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'phone' => [
                    'nullable',
                    'string',
                    'max:20',
                    Rule::unique('users', 'phone')->ignore($user->id),
                ],
                'password' => 'nullable|string|min:8',
                'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
                'is_active' => 'nullable|boolean',
                'role_slug' => ['nullable', 'string', Rule::in(['admin', 'ceo', 'trainer', 'student', 'agent', 'school_owner'])],
                'program_ids' => 'nullable|array',
                'program_ids.*' => 'integer|exists:programs,id',
                'daily_rate' => 'nullable|numeric|min:0',
            ]
        );

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $payload = [];

        if ($request->filled('name')) {
            $payload['name'] = trim((string) $request->name);
        }

        if (array_key_exists('email', $request->all())) {
            $payload['email'] = $request->email;
        }

        if (array_key_exists('phone', $request->all())) {
            $payload['phone'] = $request->phone;
        }

        if ($request->filled('password')) {
            $payload['password'] = Hash::make($request->password);
        }

        if (array_key_exists('status', $request->all())) {
            $payload['status'] = $request->status;
        }

        if (array_key_exists('is_active', $request->all())) {
            $payload['is_active'] = (bool) $request->boolean('is_active');
        } elseif (array_key_exists('status', $request->all())) {
            $payload['is_active'] = $request->status === 'active';
        }

        if (array_key_exists('daily_rate', $request->all()) && Schema::hasColumn('users', 'daily_rate')) {
            $payload['daily_rate'] = (float) $request->input('daily_rate', 0);
        }

        if (!empty($payload)) {
            $user->update($payload);
        }

        if (array_key_exists('role_slug', $request->all())) {
            $this->assignUserRole($user, $request->input('role_slug', 'student'));
            $this->ensureTrainerWallet($user);
        }

        if (array_key_exists('program_ids', $request->all())) {
            $this->syncUserPrograms($user, $request->input('program_ids', []));
        }

        $user->refresh();
        $this->loadUserRelations($user);
        $this->ensureTrainerWallet($user);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => [
                'user' => $this->formatUser($user),
            ],
        ], 200);
    }

    /**
     * Delete user
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if (Schema::hasTable('program_user')) {
            $user->programs()->detach();
        }

        if (Schema::hasTable('role_user')) {
            $user->roles()->detach();
        }

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ], 200);
    }

    /**
     * Toggle user status
     */
    public function toggleStatus(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $nextActive = !$user->is_active;

        $user->update([
            'is_active' => $nextActive,
            'status' => $nextActive ? 'active' : 'inactive',
        ]);

        $user->refresh();
        $this->ensureTrainerWallet($user);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully.',
            'data' => [
                'status' => $user->status,
                'is_active' => $user->is_active,
                'user' => $this->formatUser($user->fresh()),
            ],
        ], 200);
    }

    /**
     * Roles options
     */
    public function roles(): JsonResponse
    {
        if (Schema::hasTable('roles')) {
            $roles = Role::query()
                ->select('id', 'name', 'slug')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Roles retrieved successfully.',
                'data' => $roles,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully.',
            'data' => collect([
                ['id' => 1, 'name' => 'Admin', 'slug' => 'admin'],
                ['id' => 2, 'name' => 'CEO', 'slug' => 'ceo'],
                ['id' => 3, 'name' => 'Trainer', 'slug' => 'trainer'],
                ['id' => 4, 'name' => 'Student', 'slug' => 'student'],
                ['id' => 5, 'name' => 'Agent', 'slug' => 'agent'],
                ['id' => 6, 'name' => 'School Owner', 'slug' => 'school_owner'],
            ]),
        ], 200);
    }

        /**
     * Program options for select boxes
     */
    public function programOptions(): JsonResponse
    {
        if (!Schema::hasTable('programs')) {
            return response()->json([
                'success' => true,
                'message' => 'Programs retrieved successfully.',
                'data' => [],
            ], 200);
        }

        $programs = Program::query()
            ->select($this->programOptionSelectColumns())
            ->latest()
            ->get()
            ->map(function (Program $program) {
                return [
                    'id' => $program->id,
                    'name' => $program->name,
                    'slug' => $program->slug,
                    'category' => $program->category,
                    'duration' => $program->duration,
                    'start_date' => $program->start_date,
                    'end_date' => $program->end_date,
                    'price' => Schema::hasColumn('programs', 'price')
                        ? (float) ($program->price ?? 0)
                        : 0,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Programs retrieved successfully.',
            'data' => $programs,
        ], 200);
    }
    /**
     * Get users assigned to one program
     */
    public function programUsers(string $program): JsonResponse
    {
        $programModel = Program::with([
            'users.roles:id,name,slug',
            'users.programs:id,name,slug,category,duration,start_date,end_date',
        ])->find($program);

        if (!$programModel) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.',
            ], 404);
        }

        $users = $programModel->users->map(function (User $user) {
            $this->ensureTrainerWallet($user);
            return $this->formatUser($user);
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Program users retrieved successfully.',
            'data' => [
                'program' => [
                    'id' => $programModel->id,
                    'name' => $programModel->name,
                    'slug' => $programModel->slug,
                ],
                'users' => $users,
            ],
        ], 200);
    }

    /**
     * Sync users to one program
     */
    public function syncProgramUsers(Request $request, string $program): JsonResponse
    {
        $programModel = Program::with('users')->find($program);

        if (!$programModel) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        if (Schema::hasTable('program_user')) {
            $programModel->users()->sync($request->input('user_ids', []));
        }

        $programModel->refresh()->load([
            'users.roles:id,name,slug',
            'users.programs:id,name,slug,category,duration,start_date,end_date',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Program users synced successfully.',
            'data' => [
                'program' => [
                    'id' => $programModel->id,
                    'name' => $programModel->name,
                    'slug' => $programModel->slug,
                ],
                'users' => $programModel->users->map(function (User $user) {
                    $this->ensureTrainerWallet($user);
                    return $this->formatUser($user);
                })->values(),
            ],
        ], 200);
    }

    /**
     * Send account setup email with password reset link
     */
    private function sendAccountSetupEmail(User $user): bool
    {
        if (empty($user->email)) {
            return false;
        }

        $token = Password::broker()->createToken($user);

        $user->notify(new AccountSetupNotification($token, $user->email));

        return true;
    }

    /**
     * Assign one role to a user
     */
    private function assignUserRole(User $user, ?string $roleSlug = 'student'): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('role_user')) {
            return;
        }

        $roleSlug = $roleSlug ?: 'student';

        $role = Role::where('slug', $roleSlug)->first();

        if (!$role) {
            $role = Role::where('slug', 'student')->first();
        }

        if (!$role) {
            $role = Role::query()->first();
        }

        if ($role) {
            $user->roles()->sync([$role->id]);
        }
    }

    /**
     * Sync user programs
     */
    private function syncUserPrograms(User $user, $programIds = []): void
    {
        if (!Schema::hasTable('programs') || !Schema::hasTable('program_user')) {
            return;
        }

        $programIds = is_array($programIds) ? $programIds : [];

        $validIds = Program::query()
            ->whereIn('id', $programIds)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->values()
            ->all();

        $user->programs()->sync($validIds);
    }

    /**
     * Load required relationships
     */
    private function loadUserRelations(User $user): void
    {
        $user->load($this->buildUserRelations());
    }

        /**
     * Build user relationships safely
     */
    private function buildUserRelations(): array
    {
        $relations = [
            'roles:id,name,slug',
            'programs:' . $this->programRelationshipColumns(),
        ];

        if (Schema::hasTable('agent_profiles')) {
            $relations[] = 'agentProfile:id,user_id,image,commission_percentage,created_by';
        }

        if (Schema::hasTable('agent_student_referrals')) {
            $relations[] = 'referredByAgent:id,agent_user_id,student_user_id,program_id,amount_paid,commission_percentage,commission_amount,currency,status,registered_at';
            $relations[] = 'referredByAgent.agent:id,name,email,phone';
            $relations[] = 'referredByAgent.program:' . $this->programRelationshipColumns();
        }

        return $relations;
    }
    /**
     * Ensure wallet exists for trainer or agent
     */
    private function ensureTrainerWallet(User $user): void
    {
        if (!Schema::hasTable('wallets')) {
            return;
        }

        $this->loadUserRelations($user);

        $needsWallet = $user->roles->contains(function ($role) {
            return in_array((string) $role->slug, ['trainer', 'agent'], true);
        });

        if (!$needsWallet) {
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

        /**
     * Format user for API response
     */
    private function formatUser(User $user): array
    {
        $this->loadUserRelations($user);

        $primaryRole = $this->resolvePrimaryRole($user->roles);

        $walletData = null;

        if (Schema::hasTable('wallets')) {
            $wallet = Wallet::where('user_id', $user->id)->first();

            if ($wallet) {
                $walletData = [
                    'id' => $wallet->id,
                    'balance' => (float) $wallet->balance,
                    'currency' => $wallet->currency,
                    'status' => $wallet->status,
                ];
            }
        }

        $agentProfile = null;
        if (Schema::hasTable('agent_profiles') && $user->relationLoaded('agentProfile') && $user->agentProfile) {
            $agentProfile = [
                'image' => $user->agentProfile->image,
                'image_url' => $user->agentProfile->image_url ?? null,
                'commission_percentage' => (float) $user->agentProfile->commission_percentage,
            ];
        }

        $referredByAgent = null;
        if (Schema::hasTable('agent_student_referrals') && $user->relationLoaded('referredByAgent') && $user->referredByAgent) {
            $referredByAgent = [
                'referral_id' => $user->referredByAgent->id,
                'agent_user_id' => $user->referredByAgent->agent_user_id,
                'agent_name' => optional($user->referredByAgent->agent)->name,
                'agent_email' => optional($user->referredByAgent->agent)->email,
                'agent_phone' => optional($user->referredByAgent->agent)->phone,
                'program' => $user->referredByAgent->program ? [
                    'id' => $user->referredByAgent->program->id,
                    'name' => $user->referredByAgent->program->name,
                    'slug' => $user->referredByAgent->program->slug,
                    'price' => Schema::hasColumn('programs', 'price')
                        ? (float) ($user->referredByAgent->program->price ?? 0)
                        : 0,
                ] : null,
                'amount_paid' => (float) $user->referredByAgent->amount_paid,
                'commission_percentage' => (float) $user->referredByAgent->commission_percentage,
                'commission_amount' => (float) $user->referredByAgent->commission_amount,
                'currency' => $user->referredByAgent->currency,
                'status' => $user->referredByAgent->status,
                'registered_at' => optional($user->referredByAgent->registered_at)?->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'status'        => $user->status,
            'is_active'     => $user->is_active,
            'daily_rate'    => Schema::hasColumn('users', 'daily_rate')
                ? (float) $user->daily_rate
                : 0,
            'last_login_at' => $user->last_login_at,
            'created_at'    => optional($user->created_at)->format('Y-m-d H:i:s'),
            'updated_at'    => optional($user->updated_at)->format('Y-m-d H:i:s'),
            'role'          => $primaryRole,
            'roles'         => $user->roles->map(function ($role) {
                return [
                    'id'   => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ];
            })->values(),
            'programs'      => $user->programs->map(function ($program) {
                return [
                    'id'         => $program->id,
                    'name'       => $program->name,
                    'slug'       => $program->slug,
                    'category'   => $program->category,
                    'duration'   => $program->duration,
                    'start_date' => $program->start_date,
                    'end_date'   => $program->end_date,
                    'price'      => Schema::hasColumn('programs', 'price')
                        ? (float) ($program->price ?? 0)
                        : 0,
                ];
            })->values(),
            'wallet' => $walletData,
            'agent_profile' => $agentProfile,
            'referred_by_agent' => $referredByAgent,
        ];
    }
    /**
     * Resolve one primary role for frontend redirect
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

    private function hasRole(User $user, string $roleSlug): bool
    {
        $user->loadMissing('roles:id,name,slug');

        return $user->roles->contains(function ($role) use ($roleSlug) {
            return (string) $role->slug === $roleSlug;
        });
    }

    private function programOptionSelectColumns(): array
    {
        $columns = ['id', 'name', 'slug', 'category', 'duration', 'start_date', 'end_date'];

        if (Schema::hasColumn('programs', 'price')) {
            $columns[] = 'price';
        }

        return $columns;
    }

    private function programRelationshipColumns(): string
    {
        $columns = ['id', 'name', 'slug', 'category', 'duration', 'start_date', 'end_date'];

        if (Schema::hasColumn('programs', 'price')) {
            $columns[] = 'price';
        }

        return implode(',', $columns);
    }

private function resolvePrimaryRole(Collection $roles): ?array
    {
        if ($roles->isEmpty()) {
            return null;
        }

        $preferredOrder = ['ceo', 'admin', 'school_owner', 'agent', 'trainer', 'student'];

        foreach ($preferredOrder as $slug) {
            $role = $roles->firstWhere('slug', $slug);

            if ($role) {
                return [
                    'id'   => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ];
            }
        }

        $firstRole = $roles->first();

        if (!$firstRole) {
            return null;
        }

        return [
            'id'   => $firstRole->id,
            'name' => $firstRole->name,
            'slug' => $firstRole->slug,
        ];
    }
}