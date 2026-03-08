<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $programs = Program::latest()->get()->map(function ($program) {
            return $this->formatProgram($program);
        });

        return response()->json([
            'success' => true,
            'message' => 'Programs retrieved successfully.',
            'data' => $programs
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->storeValidationRules());

        $this->validateShifts($validator, $request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $program = Program::create([
            'code' => $this->generateProgramCode($data['name']),
            'slug' => $data['slug'] ?? null,
            'name' => $data['name'],
            'badge' => $data['badge'] ?? null,
            'category' => $data['category'],
            'duration' => $data['duration'],
            'level' => $data['level'] ?? null,
            'format' => $data['format'] ?? null,
            'status' => $data['status'],
            'instructor' => $data['instructor'],
            'students' => $data['students'] ?? 0,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'image' => $data['image'] ?? null,
            'intro' => $data['intro'] ?? null,
            'description' => $data['description'] ?? null,
            'overview' => $data['overview'] ?? null,
            'icon_key' => $data['icon_key'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'objectives' => $data['objectives'] ?? [],
            'modules' => $data['modules'] ?? [],
            'skills' => $data['skills'] ?? [],
            'outcomes' => $data['outcomes'] ?? [],
            'tools' => $data['tools'] ?? [],
            'shifts' => $this->prepareShifts($data['shifts'] ?? []),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Program created successfully.',
            'data' => $this->formatProgram($program)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $program = Program::find($id);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Program retrieved successfully.',
            'data' => $this->formatProgram($program)
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $program = Program::find($id);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->updateValidationRules());

        $this->validateShifts($validator, $request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $program->update([
            'slug' => $data['slug'] ?? $program->slug,
            'name' => $data['name'] ?? $program->name,
            'badge' => $data['badge'] ?? $program->badge,
            'category' => $data['category'] ?? $program->category,
            'duration' => $data['duration'] ?? $program->duration,
            'level' => $data['level'] ?? $program->level,
            'format' => $data['format'] ?? $program->format,
            'status' => $data['status'] ?? $program->status,
            'instructor' => $data['instructor'] ?? $program->instructor,
            'students' => $data['students'] ?? $program->students,
            'start_date' => $data['start_date'] ?? $program->start_date,
            'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $program->end_date,
            'image' => array_key_exists('image', $data) ? $data['image'] : $program->image,
            'intro' => array_key_exists('intro', $data) ? $data['intro'] : $program->intro,
            'description' => array_key_exists('description', $data) ? $data['description'] : $program->description,
            'overview' => array_key_exists('overview', $data) ? $data['overview'] : $program->overview,
            'icon_key' => array_key_exists('icon_key', $data) ? $data['icon_key'] : $program->icon_key,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $program->is_active,
            'objectives' => array_key_exists('objectives', $data) ? $data['objectives'] : $program->objectives,
            'modules' => array_key_exists('modules', $data) ? $data['modules'] : $program->modules,
            'skills' => array_key_exists('skills', $data) ? $data['skills'] : $program->skills,
            'outcomes' => array_key_exists('outcomes', $data) ? $data['outcomes'] : $program->outcomes,
            'tools' => array_key_exists('tools', $data) ? $data['tools'] : $program->tools,
            'shifts' => array_key_exists('shifts', $data)
                ? $this->prepareShifts($data['shifts'] ?? [])
                : $program->shifts,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Program updated successfully.',
            'data' => $this->formatProgram($program->fresh())
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $program = Program::find($id);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.'
            ], 404);
        }

        $program->delete();

        return response()->json([
            'success' => true,
            'message' => 'Program deleted successfully.'
        ], 200);
    }

    /**
     * Validation rules for store.
     */
    private function storeValidationRules(): array
    {
        return [
            'slug' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'badge' => 'nullable|string|max:255',
            'category' => 'required|string|max:255',
            'duration' => 'required|string|max:255',
            'level' => 'nullable|string|max:255',
            'format' => 'nullable|string|max:255',
            'status' => 'required|in:Active,Draft,Archived',
            'instructor' => 'required|string|max:255',
            'students' => 'nullable|integer|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'image' => 'nullable|string',
            'intro' => 'nullable|string',
            'description' => 'nullable|string',
            'overview' => 'nullable|string',
            'icon_key' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'objectives' => 'nullable|array',
            'modules' => 'nullable|array',
            'skills' => 'nullable|array',
            'outcomes' => 'nullable|array',
            'tools' => 'nullable|array',

            'shifts' => 'nullable|array',
            'shifts.*.id' => 'nullable|string|max:255',
            'shifts.*.name' => 'nullable|string|max:255',
            'shifts.*.start_time' => 'nullable|date_format:H:i',
            'shifts.*.end_time' => 'nullable|date_format:H:i',
            'shifts.*.capacity' => 'nullable|integer|min:1',
            'shifts.*.filled' => 'nullable|integer|min:0',
            'shifts.*.enrolled' => 'nullable|integer|min:0',
            'shifts.*.current_students' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Validation rules for update.
     */
    private function updateValidationRules(): array
    {
        return [
            'slug' => 'nullable|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'badge' => 'nullable|string|max:255',
            'category' => 'sometimes|required|string|max:255',
            'duration' => 'sometimes|required|string|max:255',
            'level' => 'nullable|string|max:255',
            'format' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:Active,Draft,Archived',
            'instructor' => 'sometimes|required|string|max:255',
            'students' => 'nullable|integer|min:0',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'image' => 'nullable|string',
            'intro' => 'nullable|string',
            'description' => 'nullable|string',
            'overview' => 'nullable|string',
            'icon_key' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'objectives' => 'nullable|array',
            'modules' => 'nullable|array',
            'skills' => 'nullable|array',
            'outcomes' => 'nullable|array',
            'tools' => 'nullable|array',

            'shifts' => 'nullable|array',
            'shifts.*.id' => 'nullable|string|max:255',
            'shifts.*.name' => 'nullable|string|max:255',
            'shifts.*.start_time' => 'nullable|date_format:H:i',
            'shifts.*.end_time' => 'nullable|date_format:H:i',
            'shifts.*.capacity' => 'nullable|integer|min:1',
            'shifts.*.filled' => 'nullable|integer|min:0',
            'shifts.*.enrolled' => 'nullable|integer|min:0',
            'shifts.*.current_students' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Extra validation for shifts.
     */
    private function validateShifts($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            $shifts = $request->input('shifts', []);

            if (!is_array($shifts)) {
                return;
            }

            $usedNames = [];

            foreach ($shifts as $index => $shift) {
                if (!is_array($shift)) {
                    $validator->errors()->add(
                        "shifts.$index",
                        'Each shift must be a valid object.'
                    );
                    continue;
                }

                $name = trim((string) ($shift['name'] ?? ''));
                $startTime = trim((string) ($shift['start_time'] ?? ''));
                $endTime = trim((string) ($shift['end_time'] ?? ''));
                $capacity = (int) ($shift['capacity'] ?? 0);
                $filled = (int) (
                    $shift['filled']
                    ?? $shift['enrolled']
                    ?? $shift['current_students']
                    ?? 0
                );

                $hasAnyValue =
                    $name !== '' ||
                    $startTime !== '' ||
                    $endTime !== '' ||
                    $capacity > 0;

                if (!$hasAnyValue) {
                    continue;
                }

                if ($name === '') {
                    $validator->errors()->add(
                        "shifts.$index.name",
                        'Shift name is required.'
                    );
                }

                if ($startTime === '') {
                    $validator->errors()->add(
                        "shifts.$index.start_time",
                        'Start time is required.'
                    );
                }

                if ($endTime === '') {
                    $validator->errors()->add(
                        "shifts.$index.end_time",
                        'End time is required.'
                    );
                }

                if ($capacity < 1) {
                    $validator->errors()->add(
                        "shifts.$index.capacity",
                        'Capacity must be at least 1.'
                    );
                }

                if ($name !== '') {
                    $lowerName = strtolower($name);

                    if (in_array($lowerName, $usedNames, true)) {
                        $validator->errors()->add(
                            "shifts.$index.name",
                            'Shift name must be unique in the same program.'
                        );
                    }

                    $usedNames[] = $lowerName;
                }

                if ($startTime !== '' && $endTime !== '') {
                    if (strtotime($startTime) >= strtotime($endTime)) {
                        $validator->errors()->add(
                            "shifts.$index.end_time",
                            'End time must be after start time.'
                        );
                    }
                }

                if ($capacity > 0 && $filled > $capacity) {
                    $validator->errors()->add(
                        "shifts.$index.filled",
                        'Filled students cannot be greater than shift capacity.'
                    );
                }
            }
        });
    }

    /**
     * Prepare shifts before saving.
     */
    private function prepareShifts($shifts): array
    {
        if (!is_array($shifts)) {
            return [];
        }

        $prepared = [];

        foreach ($shifts as $index => $shift) {
            if (!is_array($shift)) {
                continue;
            }

            $name = trim((string) ($shift['name'] ?? ''));
            $startTime = trim((string) ($shift['start_time'] ?? ''));
            $endTime = trim((string) ($shift['end_time'] ?? ''));
            $capacity = max((int) ($shift['capacity'] ?? 0), 0);
            $filled = max((int) (
                $shift['filled']
                ?? $shift['enrolled']
                ?? $shift['current_students']
                ?? 0
            ), 0);

            if ($name === '' && $startTime === '' && $endTime === '' && $capacity === 0) {
                continue;
            }

            $isFull = $capacity > 0 && $filled >= $capacity;

            $prepared[] = [
                'id' => !empty($shift['id']) ? (string) $shift['id'] : (string) Str::uuid(),
                'name' => $name,
                'start_time' => $startTime !== '' ? $startTime : null,
                'end_time' => $endTime !== '' ? $endTime : null,
                'capacity' => $capacity,
                'volume' => $capacity,
                'filled' => $filled,
                'available_slots' => max($capacity - $filled, 0),
                'is_full' => $isFull,
                'message' => $isFull ? 'Shift is full.' : 'Shift available.',
            ];
        }

        return array_values($prepared);
    }

    /**
     * Format one program with shift summary.
     */
    private function formatProgram(Program $program): array
    {
        $data = $program->toArray();

        $shifts = $this->prepareShifts($data['shifts'] ?? []);
        $totalCapacity = 0;
        $totalFilled = 0;
        $fullShiftCount = 0;

        foreach ($shifts as $shift) {
            $totalCapacity += (int) ($shift['capacity'] ?? 0);
            $totalFilled += (int) ($shift['filled'] ?? 0);

            if (!empty($shift['is_full'])) {
                $fullShiftCount++;
            }
        }

        $data['shifts'] = $shifts;
        $data['shift_summary'] = [
            'total_shifts' => count($shifts),
            'total_capacity' => $totalCapacity,
            'total_filled' => $totalFilled,
            'total_available_slots' => max($totalCapacity - $totalFilled, 0),
            'full_shifts' => $fullShiftCount,
            'has_full_shift' => $fullShiftCount > 0,
            'notification' => $fullShiftCount > 0
                ? 'One or more shifts are full.'
                : 'All shifts still have space.',
        ];

        return $data;
    }

    /**
     * Generate program code automatically.
     * Example: Software Development => SD-2026-001
     */
    private function generateProgramCode(string $programName): string
    {
        $prefix = $this->makeProgramPrefix($programName);
        $year = now()->format('Y');
        $base = $prefix . '-' . $year . '-';

        $latestCode = Program::where('code', 'like', $base . '%')
            ->orderByDesc('id')
            ->value('code');

        $nextNumber = 1;

        if ($latestCode) {
            $parts = explode('-', $latestCode);
            $lastNumber = (int) end($parts);
            $nextNumber = $lastNumber + 1;
        }

        do {
            $code = $base . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Program::where('code', $code)->exists());

        return $code;
    }

    /**
     * Create abbreviation from program name.
     * Software Development => SD
     * Artificial Intelligence => AI
     * Accounting => ACC
     */
    private function makeProgramPrefix(string $programName): string
    {
        $cleanName = strtoupper(trim(preg_replace('/[^A-Za-z0-9\s]/', ' ', $programName)));
        $words = array_values(array_filter(preg_split('/\s+/', $cleanName)));

        if (count($words) >= 2) {
            $prefix = '';
            foreach ($words as $word) {
                $prefix .= substr($word, 0, 1);
            }
            return substr($prefix, 0, 10);
        }

        if (count($words) === 1) {
            return substr($words[0], 0, 3);
        }

        return 'PRG';
    }
}