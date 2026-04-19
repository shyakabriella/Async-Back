<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramApplication;
use App\Notifications\ApplicationApprovedNotification;
use App\Notifications\ApplicationReceivedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProgramApplicationController extends Controller
{
    /**
     * Display a listing of applications.
     */
    public function index(Request $request)
    {
        $query = ProgramApplication::with('program')->latest();

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $applications = $query->paginate($request->integer('per_page', 20));

        $applications->getCollection()->transform(function ($application) {
            return $this->formatApplication($application);
        });

        return response()->json([
            'success' => true,
            'message' => 'Applications retrieved successfully.',
            'data' => $applications,
        ], 200);
    }

    /**
     * Store a newly created application.
     */
    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'auth_provider' => 'nullable|string|max:50',

                'program_id' => 'required|exists:programs,id',
                'shift_id' => 'nullable|string|max:255',
                'experience_level' => 'nullable|string|max:255',

                'selected_skills' => 'nullable|array',
                'selected_skills.*' => 'nullable|string|max:255',

                'selected_tools' => 'nullable|array',
                'selected_tools.*' => 'nullable|string|max:255',

                'applicant.first_name' => 'required|string|min:2|max:255',
                'applicant.last_name' => 'required|string|min:2|max:255',
                'applicant.email' => 'required|email:rfc|max:255',
                'applicant.phone' => ['required', 'string', 'min:8', 'max:25', 'regex:/^[0-9+\-\s\(\)]+$/'],
                'applicant.country' => 'required|string|max:255',
                'applicant.city' => 'nullable|string|max:255',
                'applicant.date_of_birth' => 'nullable|date|before:today',
                'applicant.gender' => 'nullable|string|max:255',

                'background.education_level' => 'nullable|string|max:255',
                'background.school_name' => 'nullable|string|max:255',
                'background.field_of_study' => 'nullable|string|max:255',

                'consents.agree_terms' => 'required|boolean',
                'consents.agree_communication' => 'nullable|boolean',

                'submitted_at' => 'nullable|date',
            ],
            [
                'applicant.phone.regex' => 'Phone number format is invalid.',
                'applicant.date_of_birth.before' => 'Date of birth must be a date before today.',
            ]
        );

        $validator->after(function ($validator) use ($request) {
            $program = Program::find($request->program_id);

            if (!$program) {
                return;
            }

            $allowedSkills = $this->normalizeOptionValues($program->skills ?? []);
            $allowedTools = $this->normalizeOptionValues($program->tools ?? []);
            $allowedExperienceLevels = $this->normalizeOptionValues($program->experience_levels ?? []);
            $allowedShifts = $this->normalizeShifts($program->shifts ?? []);

            $shiftId = $request->input('shift_id');
            $selectedSkills = $request->input('selected_skills', []);
            $selectedTools = $request->input('selected_tools', []);
            $experienceLevel = $request->input('experience_level');

            $firstName = $request->input('applicant.first_name');
            $lastName = $request->input('applicant.last_name');
            $email = $request->input('applicant.email');
            $phone = $request->input('applicant.phone');

            if ($shiftId) {
                $matchedShift = collect($allowedShifts)->firstWhere('id', $shiftId);

                if (!$matchedShift) {
                    $validator->errors()->add('shift_id', 'Selected shift does not belong to this program.');
                } elseif (!empty($matchedShift['is_full'])) {
                    $validator->errors()->add('shift_id', 'Selected shift is already full.');
                }
            }

            if (is_array($selectedSkills)) {
                foreach ($selectedSkills as $index => $skill) {
                    if (!in_array($skill, $allowedSkills, true)) {
                        $validator->errors()->add(
                            "selected_skills.$index",
                            'Selected skill is not allowed for this program.'
                        );
                    }
                }
            }

            if (is_array($selectedTools)) {
                foreach ($selectedTools as $index => $tool) {
                    if (!in_array($tool, $allowedTools, true)) {
                        $validator->errors()->add(
                            "selected_tools.$index",
                            'Selected tool is not allowed for this program.'
                        );
                    }
                }
            }

            if (!empty($allowedExperienceLevels) && $experienceLevel) {
                if (!in_array($experienceLevel, $allowedExperienceLevels, true)) {
                    $validator->errors()->add(
                        'experience_level',
                        'Selected experience level is not allowed for this program.'
                    );
                }
            }

            if (!$request->boolean('consents.agree_terms')) {
                $validator->errors()->add(
                    'consents.agree_terms',
                    'You must agree to the terms before submitting.'
                );
            }

            $duplicates = $this->findDuplicateApplicantFields(
                (int) $program->id,
                $email,
                $phone,
                $firstName,
                $lastName
            );

            if ($duplicates['email']) {
                $validator->errors()->add(
                    'applicant.email',
                    'This email has already been used to apply for this program.'
                );
            }

            if ($duplicates['phone']) {
                $validator->errors()->add(
                    'applicant.phone',
                    'This phone number has already been used to apply for this program.'
                );
            }

            if ($duplicates['full_name']) {
                $validator->errors()->add(
                    'applicant.first_name',
                    'An application with the same first name and last name already exists for this program.'
                );
                $validator->errors()->add(
                    'applicant.last_name',
                    'An application with the same first name and last name already exists for this program.'
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $application = DB::transaction(function () use ($request) {
                $program = Program::where('id', $request->program_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->syncProgramShiftAvailability($program);

                $program->refresh();
                $shifts = $this->normalizeShifts($program->shifts ?? []);
                $shiftId = (string) $request->input('shift_id');
                $selectedShift = null;

                if ($shiftId !== '') {
                    $selectedShift = collect($shifts)->first(function ($shift) use ($shiftId) {
                        return (string) ($shift['id'] ?? '') === $shiftId;
                    });

                    if (!$selectedShift) {
                        throw ValidationException::withMessages([
                            'shift_id' => ['Selected shift does not belong to this program.'],
                        ]);
                    }

                    if (!empty($selectedShift['is_full'])) {
                        throw ValidationException::withMessages([
                            'shift_id' => ['Selected shift is already full.'],
                        ]);
                    }
                }

                $application = ProgramApplication::create([
                    'program_id' => $program->id,
                    'program_title' => $program->name ?? null,
                    'program_slug' => $program->slug ?? null,

                    'shift_id' => $request->shift_id,
                    'shift_name' => $selectedShift['name'] ?? null,
                    'experience_level' => $request->experience_level,
                    'selected_skills' => $this->normalizeStringArray($request->input('selected_skills', [])),
                    'selected_tools' => $this->normalizeStringArray($request->input('selected_tools', [])),

                    'auth_provider' => trim((string) $request->input('auth_provider', 'manual')),

                    'first_name' => $this->cleanValue($request->input('applicant.first_name')),
                    'last_name' => $this->cleanValue($request->input('applicant.last_name')),
                    'email' => $this->normalizeEmail($request->input('applicant.email')),
                    'phone' => $this->cleanPhoneForStorage($request->input('applicant.phone')),
                    'country' => $this->cleanValue($request->input('applicant.country')),
                    'city' => $this->cleanValue($request->input('applicant.city')),
                    'date_of_birth' => $request->input('applicant.date_of_birth'),
                    'gender' => $this->cleanValue($request->input('applicant.gender')),

                    'education_level' => $this->cleanValue($request->input('background.education_level')),
                    'school_name' => $this->cleanValue($request->input('background.school_name')),
                    'field_of_study' => $this->cleanValue($request->input('background.field_of_study')),

                    'agree_terms' => $request->boolean('consents.agree_terms'),
                    'agree_communication' => $request->has('consents.agree_communication')
                        ? $request->boolean('consents.agree_communication')
                        : true,

                    'status' => 'Pending',
                    'admin_note' => null,
                    'submitted_at' => $request->input('submitted_at', now()),
                    'meta' => [
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ],
                ]);

                $this->syncProgramShiftAvailability($program);

                return $application->fresh('program');
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors(),
            ], 422);
        }

        $this->sendNotificationSafely(
            $application->email,
            new ApplicationReceivedNotification($application)
        );

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully.',
            'data' => $this->formatApplication($application),
        ], 201);
    }

    /**
     * Display the specified application.
     */
    public function show(string $id)
    {
        $application = ProgramApplication::with('program')->find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Application retrieved successfully.',
            'data' => $this->formatApplication($application),
        ], 200);
    }

    /**
     * Update the specified application fully.
     */
    public function update(Request $request, string $id)
    {
        $application = ProgramApplication::with('program')->find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found.',
            ], 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'auth_provider' => 'nullable|string|max:50',

                'program_id' => 'nullable|exists:programs,id',
                'shift_id' => 'nullable|string|max:255',
                'experience_level' => 'nullable|string|max:255',

                'selected_skills' => 'nullable|array',
                'selected_skills.*' => 'nullable|string|max:255',

                'selected_tools' => 'nullable|array',
                'selected_tools.*' => 'nullable|string|max:255',

                'applicant.first_name' => 'nullable|string|min:2|max:255',
                'applicant.last_name' => 'nullable|string|min:2|max:255',
                'applicant.email' => 'nullable|email:rfc|max:255',
                'applicant.phone' => ['nullable', 'string', 'min:8', 'max:25', 'regex:/^[0-9+\-\s\(\)]+$/'],
                'applicant.country' => 'nullable|string|max:255',
                'applicant.city' => 'nullable|string|max:255',
                'applicant.date_of_birth' => 'nullable|date|before:today',
                'applicant.gender' => 'nullable|string|max:255',

                'background.education_level' => 'nullable|string|max:255',
                'background.school_name' => 'nullable|string|max:255',
                'background.field_of_study' => 'nullable|string|max:255',

                'consents.agree_terms' => 'nullable|boolean',
                'consents.agree_communication' => 'nullable|boolean',

                'status' => 'nullable|in:Pending,Reviewed,Accepted,Rejected,Waitlisted',
                'admin_note' => 'nullable|string|max:5000',
                'submitted_at' => 'nullable|date',
            ],
            [
                'applicant.phone.regex' => 'Phone number format is invalid.',
                'applicant.date_of_birth.before' => 'Date of birth must be a date before today.',
            ]
        );

        $validator->after(function ($validator) use ($request, $application) {
            $final = $this->buildFinalUpdatePayload($request, $application);

            $program = Program::find($final['program_id']);

            if (!$program) {
                return;
            }

            $allowedSkills = $this->normalizeOptionValues($program->skills ?? []);
            $allowedTools = $this->normalizeOptionValues($program->tools ?? []);
            $allowedExperienceLevels = $this->normalizeOptionValues($program->experience_levels ?? []);
            $allowedShifts = $this->normalizeShifts($program->shifts ?? []);

            if (!empty($final['shift_id'])) {
                $matchedShift = collect($allowedShifts)->first(function ($shift) use ($final) {
                    return (string) ($shift['id'] ?? '') === (string) $final['shift_id'];
                });

                if (!$matchedShift) {
                    $validator->errors()->add(
                        'shift_id',
                        'Selected shift does not belong to this program.'
                    );
                }
            }

            if (is_array($final['selected_skills'])) {
                foreach ($final['selected_skills'] as $index => $skill) {
                    if (!in_array($skill, $allowedSkills, true)) {
                        $validator->errors()->add(
                            "selected_skills.$index",
                            'Selected skill is not allowed for this program.'
                        );
                    }
                }
            }

            if (is_array($final['selected_tools'])) {
                foreach ($final['selected_tools'] as $index => $tool) {
                    if (!in_array($tool, $allowedTools, true)) {
                        $validator->errors()->add(
                            "selected_tools.$index",
                            'Selected tool is not allowed for this program.'
                        );
                    }
                }
            }

            if (!empty($allowedExperienceLevels) && !empty($final['experience_level'])) {
                if (!in_array($final['experience_level'], $allowedExperienceLevels, true)) {
                    $validator->errors()->add(
                        'experience_level',
                        'Selected experience level is not allowed for this program.'
                    );
                }
            }

            $duplicates = $this->findDuplicateApplicantFields(
                (int) $program->id,
                $final['applicant']['email'],
                $final['applicant']['phone'],
                $final['applicant']['first_name'],
                $final['applicant']['last_name'],
                (int) $application->id
            );

            if ($duplicates['email']) {
                $validator->errors()->add(
                    'applicant.email',
                    'This email has already been used to apply for this program.'
                );
            }

            if ($duplicates['phone']) {
                $validator->errors()->add(
                    'applicant.phone',
                    'This phone number has already been used to apply for this program.'
                );
            }

            if ($duplicates['full_name']) {
                $validator->errors()->add(
                    'applicant.first_name',
                    'An application with the same first name and last name already exists for this program.'
                );
                $validator->errors()->add(
                    'applicant.last_name',
                    'An application with the same first name and last name already exists for this program.'
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldStatus = $application->status;
        $updatedApplication = null;

        try {
            DB::transaction(function () use ($request, $application, &$updatedApplication) {
                $application = ProgramApplication::query()
                    ->lockForUpdate()
                    ->findOrFail($application->id);

                $oldProgramId = (int) $application->program_id;
                $final = $this->buildFinalUpdatePayload($request, $application);

                $targetProgram = Program::where('id', $final['program_id'])
                    ->lockForUpdate()
                    ->first();

                $oldProgram = null;

                if ($oldProgramId && (!$targetProgram || $oldProgramId !== (int) $targetProgram->id)) {
                    $oldProgram = Program::where('id', $oldProgramId)
                        ->lockForUpdate()
                        ->first();
                }

                if ($oldProgram) {
                    $this->syncProgramShiftAvailability($oldProgram);
                }

                if ($targetProgram) {
                    $this->syncProgramShiftAvailability($targetProgram);
                    $targetProgram->refresh();
                }

                $selectedShift = null;
                $finalStatus = $final['status'];

                if ($targetProgram && !empty($final['shift_id'])) {
                    $shifts = $this->normalizeShifts($targetProgram->shifts ?? []);
                    $selectedShift = collect($shifts)->first(function ($shift) use ($final) {
                        return (string) ($shift['id'] ?? '') === (string) $final['shift_id'];
                    });

                    if (!$selectedShift) {
                        throw ValidationException::withMessages([
                            'shift_id' => ['Selected shift does not belong to this program.'],
                        ]);
                    }

                    $currentOccupies = in_array($application->status, ['Pending', 'Reviewed', 'Accepted'], true);
                    $finalOccupies = in_array($finalStatus, ['Pending', 'Reviewed', 'Accepted'], true);
                    $sameProgram = (int) $targetProgram->id === (int) $application->program_id;
                    $sameShift = (string) $final['shift_id'] === (string) $application->shift_id;
                    $canKeepCurrentSlot = $sameProgram && $sameShift && $currentOccupies && $finalOccupies;

                    if ($finalOccupies && !empty($selectedShift['is_full']) && !$canKeepCurrentSlot) {
                        throw ValidationException::withMessages([
                            'shift_id' => ['Selected shift is already full.'],
                        ]);
                    }
                }

                $application->update([
                    'program_id' => $targetProgram?->id ?? $application->program_id,
                    'program_title' => $targetProgram ? ($targetProgram->name ?? $targetProgram->title ?? null) : $application->program_title,
                    'program_slug' => $targetProgram ? ($targetProgram->slug ?? null) : $application->program_slug,

                    'shift_id' => $final['shift_id'] !== '' ? $final['shift_id'] : null,
                    'shift_name' => $selectedShift['name'] ?? ($final['shift_id'] ? $application->shift_name : null),

                    'experience_level' => $this->cleanValue($final['experience_level']),
                    'selected_skills' => $this->normalizeStringArray($final['selected_skills']),
                    'selected_tools' => $this->normalizeStringArray($final['selected_tools']),

                    'auth_provider' => trim((string) ($final['auth_provider'] ?? 'manual')),

                    'first_name' => $this->cleanValue($final['applicant']['first_name']),
                    'last_name' => $this->cleanValue($final['applicant']['last_name']),
                    'email' => $this->normalizeEmail($final['applicant']['email']),
                    'phone' => $this->cleanPhoneForStorage($final['applicant']['phone']),
                    'country' => $this->cleanValue($final['applicant']['country']),
                    'city' => $this->cleanValue($final['applicant']['city']),
                    'date_of_birth' => $final['applicant']['date_of_birth'] ?: null,
                    'gender' => $this->cleanValue($final['applicant']['gender']),

                    'education_level' => $this->cleanValue($final['background']['education_level']),
                    'school_name' => $this->cleanValue($final['background']['school_name']),
                    'field_of_study' => $this->cleanValue($final['background']['field_of_study']),

                    'agree_terms' => (bool) $final['consents']['agree_terms'],
                    'agree_communication' => (bool) $final['consents']['agree_communication'],

                    'status' => $finalStatus,
                    'admin_note' => $final['admin_note'] !== null ? trim((string) $final['admin_note']) : null,
                    'submitted_at' => $final['submitted_at'] ?: $application->submitted_at,
                ]);

                if ($oldProgram) {
                    $this->syncProgramShiftAvailability($oldProgram);
                }

                if ($targetProgram) {
                    $this->syncProgramShiftAvailability($targetProgram);
                }

                $updatedApplication = $application->fresh('program');
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($oldStatus !== 'Accepted' && $updatedApplication->status === 'Accepted') {
            $this->sendNotificationSafely(
                $updatedApplication->email,
                new ApplicationApprovedNotification($updatedApplication)
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully.',
            'data' => $this->formatApplication($updatedApplication),
        ], 200);
    }

    /**
     * Remove the specified application.
     */
    public function destroy(string $id)
    {
        $application = ProgramApplication::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found.',
            ], 404);
        }

        DB::transaction(function () use ($application) {
            $program = null;

            if ($application->program_id) {
                $program = Program::where('id', $application->program_id)
                    ->lockForUpdate()
                    ->first();
            }

            $application->delete();

            if ($program) {
                $this->syncProgramShiftAvailability($program);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Application deleted successfully.',
        ], 200);
    }

    /**
     * Safely send notification without breaking request if mail fails.
     */
    private function sendNotificationSafely(string $email, $notification): void
    {
        try {
            Notification::route('mail', $email)->notify($notification);
        } catch (\Throwable $e) {
            Log::error('Failed to send application notification.', [
                'email' => $email,
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build final update payload by merging request data with current application.
     */
    private function buildFinalUpdatePayload(Request $request, ProgramApplication $application): array
    {
        return [
            'auth_provider' => $request->exists('auth_provider')
                ? $request->input('auth_provider')
                : $application->auth_provider,

            'program_id' => $request->exists('program_id')
                ? (int) $request->input('program_id')
                : (int) $application->program_id,

            'shift_id' => $request->exists('shift_id')
                ? $request->input('shift_id')
                : $application->shift_id,

            'experience_level' => $request->exists('experience_level')
                ? $request->input('experience_level')
                : $application->experience_level,

            'selected_skills' => $request->exists('selected_skills')
                ? $request->input('selected_skills', [])
                : ($application->selected_skills ?? []),

            'selected_tools' => $request->exists('selected_tools')
                ? $request->input('selected_tools', [])
                : ($application->selected_tools ?? []),

            'applicant' => [
                'first_name' => $request->exists('applicant.first_name')
                    ? $request->input('applicant.first_name')
                    : $application->first_name,
                'last_name' => $request->exists('applicant.last_name')
                    ? $request->input('applicant.last_name')
                    : $application->last_name,
                'email' => $request->exists('applicant.email')
                    ? $request->input('applicant.email')
                    : $application->email,
                'phone' => $request->exists('applicant.phone')
                    ? $request->input('applicant.phone')
                    : $application->phone,
                'country' => $request->exists('applicant.country')
                    ? $request->input('applicant.country')
                    : $application->country,
                'city' => $request->exists('applicant.city')
                    ? $request->input('applicant.city')
                    : $application->city,
                'date_of_birth' => $request->exists('applicant.date_of_birth')
                    ? $request->input('applicant.date_of_birth')
                    : optional($application->date_of_birth)->format('Y-m-d'),
                'gender' => $request->exists('applicant.gender')
                    ? $request->input('applicant.gender')
                    : $application->gender,
            ],

            'background' => [
                'education_level' => $request->exists('background.education_level')
                    ? $request->input('background.education_level')
                    : $application->education_level,
                'school_name' => $request->exists('background.school_name')
                    ? $request->input('background.school_name')
                    : $application->school_name,
                'field_of_study' => $request->exists('background.field_of_study')
                    ? $request->input('background.field_of_study')
                    : $application->field_of_study,
            ],

            'consents' => [
                'agree_terms' => $request->exists('consents.agree_terms')
                    ? $request->boolean('consents.agree_terms')
                    : (bool) $application->agree_terms,
                'agree_communication' => $request->exists('consents.agree_communication')
                    ? $request->boolean('consents.agree_communication')
                    : (bool) $application->agree_communication,
            ],

            'status' => $request->exists('status')
                ? $request->input('status')
                : $application->status,

            'admin_note' => $request->exists('admin_note')
                ? $request->input('admin_note')
                : $application->admin_note,

            'submitted_at' => $request->exists('submitted_at')
                ? $request->input('submitted_at')
                : optional($application->submitted_at)->toISOString(),
        ];
    }

    /**
     * Recalculate shifts from current applications.
     */
    private function syncProgramShiftAvailability(Program $program): void
    {
        $shifts = $this->normalizeShifts($program->shifts ?? []);
        $usageCounts = $this->getShiftUsageCounts((int) $program->id);
        $program->shifts = $this->applyShiftUsageCounts($shifts, $usageCounts);
        $program->save();
    }

    /**
     * Count active applications per shift.
     */
    private function getShiftUsageCounts(int $programId): array
    {
        return ProgramApplication::query()
            ->where('program_id', $programId)
            ->whereNotNull('shift_id')
            ->whereIn('status', ['Pending', 'Reviewed', 'Accepted'])
            ->get(['shift_id'])
            ->groupBy(function ($application) {
                return (string) $application->shift_id;
            })
            ->map(function ($items) {
                return $items->count();
            })
            ->toArray();
    }

    /**
     * Apply counts to shifts.
     */
    private function applyShiftUsageCounts(array $shifts, array $usageCounts): array
    {
        $updated = [];

        foreach ($shifts as $shift) {
            $id = (string) ($shift['id'] ?? '');
            $capacity = max((int) ($shift['capacity'] ?? $shift['volume'] ?? 0), 0);
            $filled = $id !== '' ? (int) ($usageCounts[$id] ?? 0) : 0;
            $availableSlots = max($capacity - $filled, 0);
            $isFull = $capacity > 0 && $filled >= $capacity;

            $shift['capacity'] = $capacity;
            $shift['volume'] = $capacity;
            $shift['filled'] = $filled;
            $shift['available_slots'] = $availableSlots;
            $shift['is_full'] = $isFull;
            $shift['message'] = $isFull ? 'Shift is full.' : 'Shift available.';

            $updated[] = $shift;
        }

        return array_values($updated);
    }

    /**
     * Find duplicate applicant details for the same program.
     */
    private function findDuplicateApplicantFields(
        int $programId,
        ?string $email,
        ?string $phone,
        ?string $firstName,
        ?string $lastName,
        ?int $ignoreApplicationId = null
    ): array {
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedPhone = $this->normalizePhone($phone);
        $normalizedFirstName = $this->normalizeText($firstName);
        $normalizedLastName = $this->normalizeText($lastName);

        $duplicates = [
            'email' => false,
            'phone' => false,
            'full_name' => false,
        ];

        $applications = ProgramApplication::where('program_id', $programId)
            ->when($ignoreApplicationId, function ($query) use ($ignoreApplicationId) {
                $query->where('id', '!=', $ignoreApplicationId);
            })
            ->get(['email', 'phone', 'first_name', 'last_name']);

        foreach ($applications as $application) {
            if (
                !$duplicates['email'] &&
                $normalizedEmail !== '' &&
                $this->normalizeEmail($application->email) === $normalizedEmail
            ) {
                $duplicates['email'] = true;
            }

            if (
                !$duplicates['phone'] &&
                $normalizedPhone !== '' &&
                $this->normalizePhone($application->phone) === $normalizedPhone
            ) {
                $duplicates['phone'] = true;
            }

            if (
                !$duplicates['full_name'] &&
                $normalizedFirstName !== '' &&
                $normalizedLastName !== '' &&
                $this->normalizeText($application->first_name) === $normalizedFirstName &&
                $this->normalizeText($application->last_name) === $normalizedLastName
            ) {
                $duplicates['full_name'] = true;
            }

            if ($duplicates['email'] && $duplicates['phone'] && $duplicates['full_name']) {
                break;
            }
        }

        return $duplicates;
    }

    /**
     * Convert stored arrays/options into string values list.
     */
    private function normalizeOptionValues($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $items[] = trim($item);
                continue;
            }

            if (is_array($item)) {
                $items[] = trim(
                    (string) (
                        $item['value']
                        ?? $item['label']
                        ?? $item['name']
                        ?? $item['title']
                        ?? ''
                    )
                );
            }
        }

        return array_values(array_filter(array_unique($items)));
    }

    /**
     * Normalize shifts from program record.
     */
    private function normalizeShifts($shifts): array
    {
        if (is_string($shifts)) {
            $decoded = json_decode($shifts, true);
            $shifts = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (!is_array($shifts)) {
            return [];
        }

        $normalized = [];

        foreach ($shifts as $index => $shift) {
            if (!is_array($shift)) {
                continue;
            }

            $capacity = (int) ($shift['capacity'] ?? $shift['volume'] ?? 0);
            $filled = (int) (
                $shift['filled']
                ?? $shift['enrolled']
                ?? $shift['current_students']
                ?? 0
            );

            $normalized[] = [
                'id' => $shift['id'] ?? ('shift_' . ($index + 1)),
                'name' => $shift['name'] ?? '',
                'start_time' => $shift['start_time'] ?? $shift['startTime'] ?? null,
                'end_time' => $shift['end_time'] ?? $shift['endTime'] ?? null,
                'capacity' => $capacity,
                'volume' => $capacity,
                'filled' => $filled,
                'available_slots' => $shift['available_slots'] ?? max($capacity - $filled, 0),
                'is_full' => $shift['is_full'] ?? $shift['isFull'] ?? ($capacity > 0 && $filled >= $capacity),
                'message' => $shift['message'] ?? null,
            ];
        }

        return array_values($normalized);
    }

    /**
     * Format one application response.
     */
    private function formatApplication(ProgramApplication $application): array
    {
        $program = $application->program;

        return [
            'id' => $application->id,
            'program' => [
                'id' => $application->program_id,
                'title' => $application->program_title ?: optional($program)->name,
                'name' => $application->program_title ?: optional($program)->name,
                'slug' => $application->program_slug ?: optional($program)->slug,
                'skills' => $this->normalizeOptionValues(optional($program)->skills ?? []),
                'tools' => $this->normalizeOptionValues(optional($program)->tools ?? []),
                'experience_levels' => $this->normalizeOptionValues(optional($program)->experience_levels ?? []),
                'shifts' => $this->normalizeShifts(optional($program)->shifts ?? []),
            ],
            'shift' => [
                'id' => $application->shift_id,
                'name' => $application->shift_name,
            ],
            'experience_level' => $application->experience_level,
            'selected_skills' => $application->selected_skills ?? [],
            'selected_tools' => $application->selected_tools ?? [],
            'auth_provider' => $application->auth_provider,

            'applicant' => [
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'country' => $application->country,
                'city' => $application->city,
                'date_of_birth' => $application->date_of_birth?->format('Y-m-d'),
                'gender' => $application->gender,
            ],

            'background' => [
                'education_level' => $application->education_level,
                'school_name' => $application->school_name,
                'field_of_study' => $application->field_of_study,
            ],

            'consents' => [
                'agree_terms' => (bool) $application->agree_terms,
                'agree_communication' => (bool) $application->agree_communication,
            ],

            'status' => $application->status,
            'admin_note' => $application->admin_note,
            'submitted_at' => optional($application->submitted_at)->toISOString(),
            'created_at' => optional($application->created_at)->toISOString(),
            'updated_at' => optional($application->updated_at)->toISOString(),
        ];
    }

    /**
     * Normalize email for comparison/storage.
     */
    private function normalizeEmail(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /**
     * Normalize general text for comparison.
     */
    private function normalizeText(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return mb_strtolower($value);
    }

    /**
     * Normalize phone for duplicate comparison.
     */
    private function normalizePhone(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    /**
     * Clean regular value before saving.
     */
    private function cleanValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return $value === '' ? null : $value;
    }

    /**
     * Clean phone before saving.
     */
    private function cleanPhoneForStorage($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return $value === '' ? null : $value;
    }

    /**
     * Clean string array values.
     */
    private function normalizeStringArray($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $cleaned = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value !== '') {
                $cleaned[] = $value;
            }
        }

        return array_values(array_unique($cleaned));
    }
}