<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            Log::warning('Program store validation failed', [
                'errors' => $validator->errors()->toArray(),
                'payload' => $request->all(),
            ]);

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
            'price' => $data['price'] ?? 0,
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
            'shifts' => $this->prepareShifts($request->input('shifts', [])),
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
            Log::warning('Program update validation failed', [
                'program_id' => $id,
                'errors' => $validator->errors()->toArray(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $program->update([
            'slug' => array_key_exists('slug', $data) ? $data['slug'] : $program->slug,
            'name' => $data['name'] ?? $program->name,
            'badge' => array_key_exists('badge', $data) ? $data['badge'] : $program->badge,
            'category' => $data['category'] ?? $program->category,
            'duration' => $data['duration'] ?? $program->duration,
            'level' => array_key_exists('level', $data) ? $data['level'] : $program->level,
            'format' => array_key_exists('format', $data) ? $data['format'] : $program->format,
            'status' => $data['status'] ?? $program->status,
            'instructor' => $data['instructor'] ?? $program->instructor,
            'students' => array_key_exists('students', $data) ? $data['students'] : $program->students,
            'price' => array_key_exists('price', $data) ? $data['price'] : $program->price,
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
            'shifts' => array_key_exists('shifts', $request->all())
                ? $this->prepareShifts($request->input('shifts', []))
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
            'price' => 'required|numeric|min:0',
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
            'shifts.*' => 'array',
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
            'price' => 'nullable|numeric|min:0',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date',
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

            'shifts' => 'sometimes|array',
            'shifts.*' => 'array',
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
     * Normalize incoming shifts.
     * Accept both snake_case and camelCase from frontend.
     */
    private function normalizeIncomingShifts($shifts): array
    {
        if (!is_array($shifts)) {
            return [];
        }

        $normalized = [];

        foreach ($shifts as $shift) {
            if (!is_array($shift)) {
                continue;
            }

            $normalized[] = [
                'id' => $shift['id'] ?? null,
                'name' => trim((string) ($shift['name'] ?? '')),
                'start_time' => trim((string) ($shift['start_time'] ?? $shift['startTime'] ?? '')),
                'end_time' => trim((string) ($shift['end_time'] ?? $shift['endTime'] ?? '')),
                'capacity' => (int) ($shift['capacity'] ?? $shift['volume'] ?? 0),
                'filled' => (int) (
                    $shift['filled']
                    ?? $shift['enrolled']
                    ?? $shift['current_students']
                    ?? 0
                ),
            ];
        }

        return $normalized;
    }

    /**
     * Extra validation for shifts.
     */
    private function validateShifts($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            if (!array_key_exists('shifts', $request->all())) {
                return;
            }

            $shifts = $this->normalizeIncomingShifts($request->input('shifts', []));

            $usedNames = [];

            foreach ($shifts as $index => $shift) {
                $name = $shift['name'];
                $startTime = $shift['start_time'];
                $endTime = $shift['end_time'];
                $capacity = (int) $shift['capacity'];
                $filled = (int) $shift['filled'];

                $hasAnyValue =
                    $name !== '' ||
                    $startTime !== '' ||
                    $endTime !== '' ||
                    $capacity > 0 ||
                    $filled > 0;

                if (!$hasAnyValue) {
                    continue;
                }

                if ($name === '') {
                    $validator->errors()->add("shifts.$index.name", 'Shift name is required.');
                }

                if ($startTime === '') {
                    $validator->errors()->add("shifts.$index.start_time", 'Start time is required.');
                }

                if ($endTime === '') {
                    $validator->errors()->add("shifts.$index.end_time", 'End time is required.');
                }

                if ($capacity < 1) {
                    $validator->errors()->add("shifts.$index.capacity", 'Capacity must be at least 1.');
                }

                if ($name !== '') {
                    $lowerName = mb_strtolower($name);

                    if (in_array($lowerName, $usedNames, true)) {
                        $validator->errors()->add("shifts.$index.name", 'Shift name must be unique in the same program.');
                    }

                    $usedNames[] = $lowerName;
                }

                if ($startTime !== '' && $endTime !== '') {
                    $start = strtotime('1970-01-01 ' . $startTime);
                    $end = strtotime('1970-01-01 ' . $endTime);

                    if ($start === false || $end === false) {
                        $validator->errors()->add("shifts.$index.start_time", 'Invalid shift time format.');
                    } elseif ($start >= $end) {
                        $validator->errors()->add("shifts.$index.end_time", 'End time must be after start time.');
                    }
                }

                if ($capacity > 0 && $filled > $capacity) {
                    $validator->errors()->add("shifts.$index.filled", 'Filled students cannot be greater than shift capacity.');
                }
            }

            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            if (!empty($startDate) && !empty($endDate) && strtotime($endDate) < strtotime($startDate)) {
                $validator->errors()->add('end_date', 'End date must be after or equal to start date.');
            }
        });
    }

    /**
     * Prepare shifts before saving.
     */
    private function prepareShifts($shifts): array
    {
        $normalizedShifts = $this->normalizeIncomingShifts($shifts);

        $prepared = [];

        foreach ($normalizedShifts as $shift) {
            $name = $shift['name'];
            $startTime = $shift['start_time'];
            $endTime = $shift['end_time'];
            $capacity = max((int) $shift['capacity'], 0);
            $filled = max((int) $shift['filled'], 0);

            if ($name === '' && $startTime === '' && $endTime === '' && $capacity === 0 && $filled === 0) {
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

        $data['price'] = isset($data['price']) ? (float) $data['price'] : 0;

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