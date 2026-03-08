<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        $validator = Validator::make($request->all(), [
            'auth_provider' => 'nullable|string|max:50',

            'program_id' => 'required|exists:programs,id',
            'shift_id' => 'nullable|string|max:255',
            'experience_level' => 'nullable|string|max:255',

            'selected_skills' => 'nullable|array',
            'selected_skills.*' => 'nullable|string|max:255',

            'selected_tools' => 'nullable|array',
            'selected_tools.*' => 'nullable|string|max:255',

            'applicant.first_name' => 'required|string|max:255',
            'applicant.last_name' => 'required|string|max:255',
            'applicant.email' => 'required|email|max:255',
            'applicant.phone' => 'required|string|max:255',
            'applicant.country' => 'required|string|max:255',
            'applicant.city' => 'nullable|string|max:255',
            'applicant.date_of_birth' => 'nullable|date',
            'applicant.gender' => 'nullable|string|max:255',

            'background.education_level' => 'nullable|string|max:255',
            'background.school_name' => 'nullable|string|max:255',
            'background.field_of_study' => 'nullable|string|max:255',

            'consents.agree_terms' => 'required|boolean',
            'consents.agree_communication' => 'nullable|boolean',

            'submitted_at' => 'nullable|date',
        ]);

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
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $program = Program::findOrFail($request->program_id);
        $shifts = $this->normalizeShifts($program->shifts ?? []);
        $selectedShift = collect($shifts)->firstWhere('id', $request->shift_id);

        $application = ProgramApplication::create([
            'program_id' => $program->id,
            'program_title' => $program->name ?? null,
            'program_slug' => $program->slug ?? null,

            'shift_id' => $request->shift_id,
            'shift_name' => $selectedShift['name'] ?? null,
            'experience_level' => $request->experience_level,
            'selected_skills' => array_values($request->input('selected_skills', [])),
            'selected_tools' => array_values($request->input('selected_tools', [])),

            'auth_provider' => $request->input('auth_provider', 'manual'),

            'first_name' => $request->input('applicant.first_name'),
            'last_name' => $request->input('applicant.last_name'),
            'email' => $request->input('applicant.email'),
            'phone' => $request->input('applicant.phone'),
            'country' => $request->input('applicant.country'),
            'city' => $request->input('applicant.city'),
            'date_of_birth' => $request->input('applicant.date_of_birth'),
            'gender' => $request->input('applicant.gender'),

            'education_level' => $request->input('background.education_level'),
            'school_name' => $request->input('background.school_name'),
            'field_of_study' => $request->input('background.field_of_study'),

            'agree_terms' => $request->boolean('consents.agree_terms'),
            'agree_communication' => $request->has('consents.agree_communication')
                ? $request->boolean('consents.agree_communication')
                : true,

            'status' => 'Pending',
            'submitted_at' => $request->input('submitted_at', now()),
            'meta' => [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully.',
            'data' => $this->formatApplication($application->fresh('program')),
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
     * Update application status/admin note.
     */
    public function update(Request $request, string $id)
    {
        $application = ProgramApplication::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:Pending,Reviewed,Accepted,Rejected,Waitlisted',
            'admin_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $application->update([
            'status' => $request->input('status', $application->status),
            'admin_note' => $request->input('admin_note', $application->admin_note),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully.',
            'data' => $this->formatApplication($application->fresh('program')),
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

        $application->delete();

        return response()->json([
            'success' => true,
            'message' => 'Application deleted successfully.',
        ], 200);
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

            $capacity = (int) ($shift['capacity'] ?? 0);
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
                'filled' => $filled,
                'available_slots' => $shift['available_slots'] ?? max($capacity - $filled, 0),
                'is_full' => $shift['is_full'] ?? $shift['isFull'] ?? ($capacity > 0 && $filled >= $capacity),
            ];
        }

        return array_values($normalized);
    }

    /**
     * Format one application response.
     */
    private function formatApplication(ProgramApplication $application): array
    {
        return [
            'id' => $application->id,
            'program' => [
                'id' => $application->program_id,
                'title' => $application->program_title ?: optional($application->program)->name,
                'slug' => $application->program_slug ?: optional($application->program)->slug,
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
}