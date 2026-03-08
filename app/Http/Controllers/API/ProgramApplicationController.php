<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    /**
     * Display a listing of the programs.
     */
    public function index()
    {
        $programs = Program::latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Programs retrieved successfully.',
            'data' => $programs,
        ], 200);
    }

    /**
     * Display the specified program.
     */
    public function show(Program $program)
    {
        return response()->json([
            'success' => true,
            'message' => 'Program retrieved successfully.',
            'data' => $program,
        ], 200);
    }

    /**
     * Store a newly created program.
     */
    public function store(Request $request)
    {
        $payload = $this->normalizePayload($request);

        $validator = Validator::make($payload, $this->rules(), $this->messages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (empty($validated['slug']) && !empty($validated['name'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name']);
        }

        $validated = $this->filterOnlyExistingColumns($validated);

        $program = Program::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Program created successfully.',
            'data' => $program,
        ], 201);
    }

    /**
     * Update the specified program.
     */
    public function update(Request $request, Program $program)
    {
        $payload = $this->normalizePayload($request);

        $validator = Validator::make(
            $payload,
            $this->rules($program->id),
            $this->messages()
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (empty($validated['slug']) && !empty($validated['name'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $program->id);
        }

        $validated = $this->filterOnlyExistingColumns($validated);

        $program->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Program updated successfully.',
            'data' => $program->fresh(),
        ], 200);
    }

    /**
     * Remove the specified program.
     */
    public function destroy(Program $program)
    {
        $program->delete();

        return response()->json([
            'success' => true,
            'message' => 'Program deleted successfully.',
        ], 200);
    }

    /**
     * Validation rules.
     */
    private function rules(?int $programId = null): array
    {
        return [
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('programs', 'slug')->ignore($programId),
            ],
            'name' => 'required|string|max:255',
            'badge' => 'nullable|string|max:255',
            'category' => 'required|string|max:255',
            'duration' => 'required|string|max:255',
            'level' => 'nullable|string|max:255',
            'format' => 'nullable|string|max:255',
            'status' => 'required|in:Active,Draft,Closed',

            'description' => 'nullable|string',
            'overview' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'instructor' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date',
            'applicationDeadline' => 'nullable|date',

            'objectives' => 'nullable|array',
            'objectives.*' => 'nullable|string',

            'modules' => 'nullable|array',
            'modules.*' => 'nullable|string',

            'requirements' => 'nullable|array',
            'requirements.*' => 'nullable|string',

            'benefits' => 'nullable|array',
            'benefits.*' => 'nullable|string',

            'skills' => 'nullable|array',
            'skills.*' => 'nullable|string',

            'tools' => 'nullable|array',
            'tools.*' => 'nullable|string',

            'outcomes' => 'nullable|array',
            'outcomes.*' => 'nullable|string',

            /**
             * SHIFTS FIX
             * This is the important part for multi-shift support.
             */
            'shifts' => 'nullable|array',
            'shifts.*.name' => 'required|string|max:255',
            'shifts.*.isFull' => 'nullable|boolean',
            'shifts.*.capacity' => 'nullable|integer|min:1',
            'shifts.*.startDate' => 'nullable|date',
            'shifts.*.endDate' => 'nullable|date',
        ];
    }

    /**
     * Custom validation messages.
     */
    private function messages(): array
    {
        return [
            'shifts.array' => 'Shifts must be sent as an array.',
            'shifts.*.name.required' => 'Each shift must have a name.',
            'shifts.*.capacity.integer' => 'Each shift capacity must be a number.',
            'shifts.*.capacity.min' => 'Each shift capacity must be at least 1.',
            'shifts.*.startDate.date' => 'Each shift start date must be a valid date.',
            'shifts.*.endDate.date' => 'Each shift end date must be a valid date.',
        ];
    }

    /**
     * Normalize request payload.
     * Handles JSON strings from FormData and cleans empty shift rows.
     */
    private function normalizePayload(Request $request): array
    {
        $payload = $request->all();

        $jsonArrayFields = [
            'objectives',
            'modules',
            'requirements',
            'benefits',
            'skills',
            'tools',
            'outcomes',
            'shifts',
        ];

        foreach ($jsonArrayFields as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $decoded = json_decode($payload[$field], true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload[$field] = $decoded;
                }
            }
        }

        if (!isset($payload['shifts']) || $payload['shifts'] === null || $payload['shifts'] === '') {
            $payload['shifts'] = [];
        }

        if (is_array($payload['shifts'])) {
            $cleanShifts = [];

            foreach ($payload['shifts'] as $shift) {
                if (!is_array($shift)) {
                    continue;
                }

                $name = isset($shift['name']) ? trim((string) $shift['name']) : '';

                $isCompletelyEmpty =
                    $name === '' &&
                    empty($shift['capacity']) &&
                    empty($shift['startDate']) &&
                    empty($shift['endDate']) &&
                    !isset($shift['isFull']);

                if ($isCompletelyEmpty) {
                    continue;
                }

                $cleanShifts[] = [
                    'name' => $name,
                    'isFull' => $this->toBoolean($shift['isFull'] ?? false),
                    'capacity' => $this->toNullableInt($shift['capacity'] ?? null),
                    'startDate' => $this->emptyToNull($shift['startDate'] ?? null),
                    'endDate' => $this->emptyToNull($shift['endDate'] ?? null),
                ];
            }

            $payload['shifts'] = array_values($cleanShifts);
        }

        return $payload;
    }

    /**
     * Convert mixed value to boolean.
     */
    private function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        $value = strtolower((string) $value);

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Convert empty string to null.
     */
    private function emptyToNull($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }

    /**
     * Convert value to nullable integer.
     */
    private function toNullableInt($value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Save only fields that actually exist in the programs table.
     */
    private function filterOnlyExistingColumns(array $data): array
    {
        $tableColumns = Schema::getColumnListing((new Program())->getTable());

        return collect($data)
            ->only($tableColumns)
            ->toArray();
    }

    /**
     * Generate unique slug.
     */
    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Program::when($ignoreId, function ($query) use ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            })->where('slug', $slug)->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}