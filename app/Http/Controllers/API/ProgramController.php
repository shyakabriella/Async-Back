<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        $validator = Validator::make($request->all(), $this->validationRules());

        $this->validateShifts($validator, $request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors()
            ], 422);
        }

        $generatedCode = $this->generateProgramCode($request->name);

        $program = Program::create([
            'code' => $generatedCode,
            'slug' => $request->slug,
            'name' => $request->name,
            'badge' => $request->badge,
            'category' => $request->category,
            'duration' => $request->duration,
            'level' => $request->level,
            'format' => $request->format,
            'status' => $request->status,
            'instructor' => $request->instructor,
            'students' => $request->students ?? 0,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'image' => $request->image,
            'intro' => $request->intro,
            'description' => $request->description,
            'overview' => $request->overview,
            'icon_key' => $request->icon_key,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'objectives' => $request->objectives,
            'modules' => $request->modules,
            'skills' => $request->skills,
            'outcomes' => $request->outcomes,
            'tools' => $request->tools,
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

        $validator = Validator::make($request->all(), $this->validationRules());

        $this->validateShifts($validator, $request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors()
            ], 422);
        }

        $program->update([
            'slug' => $request->slug,
            'name' => $request->name,
            'badge' => $request->badge,
            'category' => $request->category,
            'duration' => $request->duration,
            'level' => $request->level,
            'format' => $request->format,
            'status' => $request->status,
            'instructor' => $request->instructor,
            'students' => $request->students ?? 0,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'image' => $request->image,
            'intro' => $request->intro,
            'description' => $request->description,
            'overview' => $request->overview,
            'icon_key' => $request->icon_key,
            'is_active' => $request->has('is_active')
                ? $request->boolean('is_active')
                : $program->is_active,
            'objectives' => $request->objectives,
            'modules' => $request->modules,
            'skills' => $request->skills,
            'outcomes' => $request->outcomes,
            'tools' => $request->tools,
            'shifts' => $this->prepareShifts($request->input('shifts', [])),
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
     * Common validation rules.
     */
    private function validationRules(): array
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

            // Shift settings
            'shifts' => 'nullable|array',
            'shifts.*.name' => 'required_with:shifts|string|max:255',
            'shifts.*.start_time' => 'required_with:shifts|date_format:H:i',
            'shifts.*.end_time' => 'required_with:shifts|date_format:H:i',
            'shifts.*.capacity' => 'required_with:shifts|integer|min:1',

            // optional current filled students inside shift
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
                $name = trim((string) ($shift['name'] ?? ''));
                $startTime = $shift['start_time'] ?? null;
                $endTime = $shift['end_time'] ?? null;
                $capacity = (int) ($shift['capacity'] ?? 0);
                $filled = (int) (
                    $shift['filled']
                    ?? $shift['enrolled']
                    ?? $shift['current_students']
                    ?? 0
                );

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

                if ($startTime && $endTime) {
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
            $name = trim((string) ($shift['name'] ?? ''));
            $startTime = $shift['start_time'] ?? null;
            $endTime = $shift['end_time'] ?? null;
            $capacity = (int) ($shift['capacity'] ?? 0);
            $filled = (int) (
                $shift['filled']
                ?? $shift['enrolled']
                ?? $shift['current_students']
                ?? 0
            );

            if ($name === '' && !$startTime && !$endTime && $capacity === 0) {
                continue;
            }

            $isFull = $capacity > 0 && $filled >= $capacity;

            $prepared[] = [
                'id' => $shift['id'] ?? ('shift_' . ($index + 1) . '_' . time()),
                'name' => $name,
                'start_time' => $startTime,
                'end_time' => $endTime,
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