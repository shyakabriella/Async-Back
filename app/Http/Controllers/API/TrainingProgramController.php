<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class TrainingProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $programs = DB::table('training_programs')
            ->orderByDesc('id')
            ->get()
            ->map(function ($program) {
                return $this->appendRelatedLists($program);
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Training programs retrieved successfully.',
            'data' => $programs,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->normalizeIncomingLists($request);

        $validator = Validator::make($request->all(), [
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
            'objectives.*' => 'nullable|string',

            'modules' => 'nullable|array',
            'modules.*' => 'nullable|string',

            'skills' => 'nullable|array',
            'skills.*' => 'nullable|string',

            'outcomes' => 'nullable|array',
            'outcomes.*' => 'nullable|string',

            'tools' => 'nullable|array',
            'tools.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $programId = DB::transaction(function () use ($request) {
            $programData = $this->extractProgramData($request, true);

            $programId = DB::table('training_programs')->insertGetId($programData);

            $this->syncChildItems('training_program_skills', $programId, $request->input('skills', []));
            $this->syncChildItems('training_program_outcomes', $programId, $request->input('outcomes', []));
            $this->syncChildItems('training_program_tools', $programId, $request->input('tools', []));

            return $programId;
        });

        $program = $this->findProgram($programId);

        return response()->json([
            'success' => true,
            'message' => 'Training program created successfully.',
            'data' => $program,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $program = $this->findProgram((int) $id);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Training program not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Training program retrieved successfully.',
            'data' => $program,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $existingProgram = DB::table('training_programs')->where('id', $id)->first();

        if (!$existingProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Training program not found.',
            ], 404);
        }

        $this->normalizeIncomingLists($request);

        $validator = Validator::make($request->all(), [
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
            'objectives.*' => 'nullable|string',

            'modules' => 'nullable|array',
            'modules.*' => 'nullable|string',

            'skills' => 'nullable|array',
            'skills.*' => 'nullable|string',

            'outcomes' => 'nullable|array',
            'outcomes.*' => 'nullable|string',

            'tools' => 'nullable|array',
            'tools.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($request, $id) {
            $programData = $this->extractProgramData($request, false);

            DB::table('training_programs')
                ->where('id', $id)
                ->update($programData);

            $this->syncChildItems('training_program_skills', (int) $id, $request->input('skills', []));
            $this->syncChildItems('training_program_outcomes', (int) $id, $request->input('outcomes', []));
            $this->syncChildItems('training_program_tools', (int) $id, $request->input('tools', []));
        });

        $program = $this->findProgram((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Training program updated successfully.',
            'data' => $program,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $program = DB::table('training_programs')->where('id', $id)->first();

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Training program not found.',
            ], 404);
        }

        DB::transaction(function () use ($id) {
            if (Schema::hasTable('training_program_skills')) {
                DB::table('training_program_skills')
                    ->where($this->getForeignKeyColumn('training_program_skills'), $id)
                    ->delete();
            }

            if (Schema::hasTable('training_program_outcomes')) {
                DB::table('training_program_outcomes')
                    ->where($this->getForeignKeyColumn('training_program_outcomes'), $id)
                    ->delete();
            }

            if (Schema::hasTable('training_program_tools')) {
                DB::table('training_program_tools')
                    ->where($this->getForeignKeyColumn('training_program_tools'), $id)
                    ->delete();
            }

            DB::table('training_programs')->where('id', $id)->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Training program deleted successfully.',
        ], 200);
    }

    /**
     * Normalize incoming list fields so controller accepts:
     * - arrays
     * - JSON strings
     * - newline separated text
     */
    private function normalizeIncomingLists(Request $request): void
    {
        $request->merge([
            'objectives' => $this->normalizeList($request->input('objectives')),
            'modules' => $this->normalizeList($request->input('modules')),
            'skills' => $this->normalizeList($request->input('skills')),
            'outcomes' => $this->normalizeList($request->input('outcomes')),
            'tools' => $this->normalizeList($request->input('tools')),
        ]);
    }

    /**
     * Build insert/update data for training_programs table.
     */
    private function extractProgramData(Request $request, bool $isCreate): array
    {
        $data = [];

        $possibleFields = [
            'slug',
            'name',
            'badge',
            'category',
            'duration',
            'level',
            'format',
            'status',
            'instructor',
            'students',
            'start_date',
            'end_date',
            'image',
            'intro',
            'description',
            'overview',
            'icon_key',
            'is_active',
            'objectives',
            'modules',
        ];

        foreach ($possibleFields as $field) {
            if (Schema::hasColumn('training_programs', $field)) {
                if ($field === 'students') {
                    $data[$field] = $request->input($field, 0);
                } elseif ($field === 'is_active') {
                    $data[$field] = $request->has($field)
                        ? $request->boolean($field)
                        : true;
                } else {
                    $data[$field] = $request->input($field);
                }
            }
        }

        if (Schema::hasColumn('training_programs', 'code')) {
            if ($isCreate) {
                $data['code'] = $request->input('code') ?: $this->generateProgramCode($request->input('name'));
            }
        }

        $now = now();

        if (Schema::hasColumn('training_programs', 'created_at') && $isCreate) {
            $data['created_at'] = $now;
        }

        if (Schema::hasColumn('training_programs', 'updated_at')) {
            $data['updated_at'] = $now;
        }

        return $data;
    }

    /**
     * Append skills/outcomes/tools arrays to a program object.
     */
    private function appendRelatedLists(object $program): array
    {
        $programArray = (array) $program;

        $programId = (int) $programArray['id'];

        $programArray['skills'] = $this->fetchChildItems('training_program_skills', $programId);
        $programArray['outcomes'] = $this->fetchChildItems('training_program_outcomes', $programId);
        $programArray['tools'] = $this->fetchChildItems('training_program_tools', $programId);

        return $programArray;
    }

    /**
     * Find one program with related lists.
     */
    private function findProgram(int $id): ?array
    {
        $program = DB::table('training_programs')->where('id', $id)->first();

        if (!$program) {
            return null;
        }

        return $this->appendRelatedLists($program);
    }

    /**
     * Save child list table items by replacing old values.
     */
    private function syncChildItems(string $table, int $programId, array $items): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $foreignKey = $this->getForeignKeyColumn($table);
        $valueColumn = $this->getValueColumn($table);

        if (!$foreignKey || !$valueColumn) {
            return;
        }

        DB::table($table)->where($foreignKey, $programId)->delete();

        $rows = [];
        $now = now();

        foreach ($items as $item) {
            $value = trim((string) $item);

            if ($value === '') {
                continue;
            }

            $row = [
                $foreignKey => $programId,
                $valueColumn => $value,
            ];

            if (Schema::hasColumn($table, 'created_at')) {
                $row['created_at'] = $now;
            }

            if (Schema::hasColumn($table, 'updated_at')) {
                $row['updated_at'] = $now;
            }

            $rows[] = $row;
        }

        if (!empty($rows)) {
            DB::table($table)->insert($rows);
        }
    }

    /**
     * Fetch child items as simple string array.
     */
    private function fetchChildItems(string $table, int $programId): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $foreignKey = $this->getForeignKeyColumn($table);
        $valueColumn = $this->getValueColumn($table);

        if (!$foreignKey || !$valueColumn) {
            return [];
        }

        return DB::table($table)
            ->where($foreignKey, $programId)
            ->pluck($valueColumn)
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Detect foreign key column in child tables.
     */
    private function getForeignKeyColumn(string $table): ?string
    {
        $candidates = ['training_program_id', 'program_id'];

        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Detect value column in child tables.
     */
    private function getValueColumn(string $table): ?string
    {
        $candidates = match ($table) {
            'training_program_skills' => ['skill', 'name', 'title', 'value'],
            'training_program_outcomes' => ['outcome', 'name', 'title', 'value'],
            'training_program_tools' => ['tool', 'name', 'title', 'value'],
            default => ['name', 'title', 'value'],
        };

        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Normalize list input from array / JSON / newline text.
     */
    private function normalizeList($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(function ($item) {
                return trim((string) $item);
            }, $value), function ($item) {
                return $item !== '';
            }));
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map(function ($item) {
                    return trim((string) $item);
                }, $decoded), function ($item) {
                    return $item !== '';
                }));
            }

            return array_values(array_filter(array_map(function ($item) {
                return trim($item);
            }, preg_split('/\r\n|\r|\n/', $trimmed)), function ($item) {
                return $item !== '';
            }));
        }

        return [];
    }

    /**
     * Generate code automatically if training_programs has a code column.
     * Example: Software Development => SD-2026-001
     */
    private function generateProgramCode(string $programName): string
    {
        $prefix = $this->makeProgramPrefix($programName);
        $year = now()->format('Y');
        $base = $prefix . '-' . $year . '-';

        $latestCode = DB::table('training_programs')
            ->where('code', 'like', $base . '%')
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
        } while (
            DB::table('training_programs')->where('code', $code)->exists()
        );

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

        return 'TP';
    }
}