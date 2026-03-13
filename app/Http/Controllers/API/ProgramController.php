<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    private ?array $programTableColumns = null;

    public function index()
    {
        $programs = Program::with('users:id,name,email,phone')
            ->latest()
            ->get()
            ->map(function ($program) {
                return $this->formatProgram($program);
            });

        return response()->json([
            'success' => true,
            'message' => 'Programs retrieved successfully.',
            'data' => $programs,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->storeValidationRules());

        $this->validateShifts($validator, $request);
        $this->validateCurriculumCollection($validator, $request);

        if ($validator->fails()) {
            Log::warning('Program store validation failed', [
                'errors' => $validator->errors()->toArray(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $program = Program::create($this->buildProgramCreatePayload($data, $request));

        if (
            Schema::hasTable('program_user') &&
            array_key_exists('user_ids', $request->all())
        ) {
            $program->users()->sync($request->input('user_ids', []));
        }

        $program->load('users:id,name,email,phone');

        return response()->json([
            'success' => true,
            'message' => 'Program created successfully.',
            'data' => $this->formatProgram($program),
        ], 201);
    }

    public function show(string $id)
    {
        $program = Program::with('users:id,name,email,phone')->find($id);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Program retrieved successfully.',
            'data' => $this->formatProgram($program),
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $program = Program::with('users:id,name,email,phone')->find($id);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->updateValidationRules());

        $this->validateShifts($validator, $request);
        $this->validateCurriculumCollection($validator, $request);

        if ($validator->fails()) {
            Log::warning('Program update validation failed', [
                'program_id' => $id,
                'errors' => $validator->errors()->toArray(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $program->update($this->buildProgramUpdatePayload($program, $data, $request));

        if (
            Schema::hasTable('program_user') &&
            array_key_exists('user_ids', $request->all())
        ) {
            $program->users()->sync($request->input('user_ids', []));
        }

        $program->refresh()->load('users:id,name,email,phone');

        return response()->json([
            'success' => true,
            'message' => 'Program updated successfully.',
            'data' => $this->formatProgram($program),
        ], 200);
    }

    public function destroy(string $id)
    {
        $program = Program::find($id);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.',
            ], 404);
        }

        $program->delete();

        return response()->json([
            'success' => true,
            'message' => 'Program deleted successfully.',
        ], 200);
    }

    public function curriculumIndex(string $programId)
    {
        $program = Program::find($programId);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.',
            ], 404);
        }

        $curriculum = $this->prepareCurriculum($program->curriculum ?? []);

        return response()->json([
            'success' => true,
            'message' => 'Curriculum retrieved successfully.',
            'data' => [
                'curriculum' => $curriculum,
                'summary' => $this->makeCurriculumSummary($curriculum),
            ],
        ], 200);
    }

    public function curriculumStore(Request $request, string $programId)
    {
        $program = Program::find($programId);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'days' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'status' => ['nullable', Rule::in($this->curriculumStatuses())],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $current = $this->prepareCurriculum($program->curriculum ?? []);
        $normalizedTitle = mb_strtolower(trim($data['title']));

        foreach ($current as $item) {
            if (mb_strtolower(trim((string) ($item['title'] ?? ''))) === $normalizedTitle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module title must be unique in the same program.',
                ], 422);
            }
        }

        $newItem = [
            'id' => (string) Str::uuid(),
            'title' => trim($data['title']),
            'days' => (int) $data['days'],
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'status' => $data['status'] ?? 'Not Started',
        ];

        $current[] = $newItem;
        $prepared = $this->prepareCurriculum($current);

        $program->curriculum = $prepared;
        $program->save();

        return response()->json([
            'success' => true,
            'message' => 'Curriculum module added successfully.',
            'data' => [
                'item' => $newItem,
                'curriculum' => $prepared,
                'summary' => $this->makeCurriculumSummary($prepared),
            ],
        ], 201);
    }

    public function curriculumUpdate(Request $request, string $programId, string $curriculumId)
    {
        $program = Program::find($programId);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'days' => 'sometimes|required|integer|min:1',
            'description' => 'nullable|string',
            'status' => ['sometimes', 'required', Rule::in($this->curriculumStatuses())],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $curriculum = $this->prepareCurriculum($program->curriculum ?? []);
        $found = false;

        foreach ($curriculum as $index => $item) {
            if ((string) ($item['id'] ?? '') !== (string) $curriculumId) {
                continue;
            }

            $nextTitle = array_key_exists('title', $data)
                ? trim((string) $data['title'])
                : trim((string) ($item['title'] ?? ''));

            foreach ($curriculum as $otherIndex => $otherItem) {
                if ($otherIndex === $index) {
                    continue;
                }

                if (
                    mb_strtolower(trim((string) ($otherItem['title'] ?? ''))) ===
                    mb_strtolower($nextTitle)
                ) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Module title must be unique in the same program.',
                    ], 422);
                }
            }

            $curriculum[$index] = [
                'id' => (string) ($item['id'] ?? $curriculumId),
                'title' => $nextTitle,
                'days' => array_key_exists('days', $data)
                    ? (int) $data['days']
                    : (int) ($item['days'] ?? 0),
                'description' => array_key_exists('description', $data)
                    ? (!empty($data['description']) ? trim((string) $data['description']) : null)
                    : ($item['description'] ?? null),
                'status' => array_key_exists('status', $data)
                    ? $data['status']
                    : ($item['status'] ?? 'Not Started'),
            ];

            $found = true;
            break;
        }

        if (!$found) {
            return response()->json([
                'success' => false,
                'message' => 'Curriculum item not found.',
            ], 404);
        }

        $prepared = $this->prepareCurriculum($curriculum);

        $program->curriculum = $prepared;
        $program->save();

        $updatedItem = collect($prepared)->firstWhere('id', $curriculumId);

        return response()->json([
            'success' => true,
            'message' => 'Curriculum module updated successfully.',
            'data' => [
                'item' => $updatedItem,
                'curriculum' => $prepared,
                'summary' => $this->makeCurriculumSummary($prepared),
            ],
        ], 200);
    }

    public function curriculumDestroy(string $programId, string $curriculumId)
    {
        $program = Program::find($programId);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program not found.',
            ], 404);
        }

        $curriculum = $this->prepareCurriculum($program->curriculum ?? []);
        $beforeCount = count($curriculum);

        $curriculum = array_values(array_filter($curriculum, function ($item) use ($curriculumId) {
            return (string) ($item['id'] ?? '') !== (string) $curriculumId;
        }));

        if ($beforeCount === count($curriculum)) {
            return response()->json([
                'success' => false,
                'message' => 'Curriculum item not found.',
            ], 404);
        }

        $program->curriculum = $curriculum;
        $program->save();

        return response()->json([
            'success' => true,
            'message' => 'Curriculum module deleted successfully.',
            'data' => [
                'curriculum' => $curriculum,
                'summary' => $this->makeCurriculumSummary($curriculum),
            ],
        ], 200);
    }

    private function storeValidationRules(): array
    {
        return [
            'slug' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:255',
            'format' => 'nullable|string|max:255',
            'status' => 'nullable|in:Active,Draft,Archived',
            'instructor' => 'nullable|string|max:255',
            'students' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'image' => 'nullable|string',
            'intro' => 'nullable|string',
            'description' => 'nullable|string',
            'overview' => 'nullable|string',
            'icon_key' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'objectives' => 'nullable|array',
            'modules' => 'nullable|array',
            'curriculum' => 'nullable|array',
            'curriculum.*' => 'array',
            'curriculum.*.id' => 'nullable|string|max:255',
            'curriculum.*.title' => 'nullable|string|max:255',
            'curriculum.*.days' => 'nullable|integer|min:1',
            'curriculum.*.description' => 'nullable|string',
            'curriculum.*.status' => ['nullable', Rule::in($this->curriculumStatuses())],
            'skills' => 'nullable|array',
            'outcomes' => 'nullable|array',
            'tools' => 'nullable|array',
            'experience_levels' => 'nullable|array',

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

            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }

    private function updateValidationRules(): array
    {
        return [
            'slug' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:255',
            'format' => 'nullable|string|max:255',
            'status' => 'nullable|in:Active,Draft,Archived',
            'instructor' => 'nullable|string|max:255',
            'students' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'image' => 'nullable|string',
            'intro' => 'nullable|string',
            'description' => 'nullable|string',
            'overview' => 'nullable|string',
            'icon_key' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'objectives' => 'nullable|array',
            'modules' => 'nullable|array',
            'curriculum' => 'sometimes|array',
            'curriculum.*' => 'array',
            'curriculum.*.id' => 'nullable|string|max:255',
            'curriculum.*.title' => 'nullable|string|max:255',
            'curriculum.*.days' => 'nullable|integer|min:1',
            'curriculum.*.description' => 'nullable|string',
            'curriculum.*.status' => ['nullable', Rule::in($this->curriculumStatuses())],
            'skills' => 'nullable|array',
            'outcomes' => 'nullable|array',
            'tools' => 'nullable|array',
            'experience_levels' => 'nullable|array',

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

            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }

    private function curriculumStatuses(): array
    {
        return [
            'Not Started',
            'In Progress',
            'Completed',
            'On Hold',
        ];
    }

    private function activeApplicationStatuses(): array
    {
        return ['Pending', 'Reviewed', 'Accepted'];
    }

    private function getProgramTableColumns(): array
    {
        if ($this->programTableColumns !== null) {
            return $this->programTableColumns;
        }

        if (!Schema::hasTable('programs')) {
            $this->programTableColumns = [];
            return $this->programTableColumns;
        }

        $this->programTableColumns = Schema::getColumnListing('programs');

        return $this->programTableColumns;
    }

    private function programTableHasColumn(string $column): bool
    {
        return in_array($column, $this->getProgramTableColumns(), true);
    }

    private function setProgramColumnValue(array &$payload, string $column, $value): void
    {
        if ($this->programTableHasColumn($column)) {
            $payload[$column] = $value;
        }
    }

    private function buildProgramCreatePayload(array $data, Request $request): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = 'Untitled Program';
        }

        $payload = [];

        $this->setProgramColumnValue($payload, 'code', $this->generateProgramCode($name));
        $this->setProgramColumnValue($payload, 'slug', $data['slug'] ?? null);
        $this->setProgramColumnValue($payload, 'name', $name);
        $this->setProgramColumnValue($payload, 'badge', $data['badge'] ?? null);
        $this->setProgramColumnValue($payload, 'category', $data['category'] ?? null);
        $this->setProgramColumnValue($payload, 'duration', $data['duration'] ?? null);
        $this->setProgramColumnValue($payload, 'level', $data['level'] ?? null);
        $this->setProgramColumnValue($payload, 'format', $data['format'] ?? null);
        $this->setProgramColumnValue($payload, 'status', $data['status'] ?? 'Draft');
        $this->setProgramColumnValue($payload, 'instructor', $data['instructor'] ?? null);
        $this->setProgramColumnValue($payload, 'students', (int) ($data['students'] ?? 0));
        $this->setProgramColumnValue($payload, 'price', (float) ($data['price'] ?? 0));
        $this->setProgramColumnValue($payload, 'start_date', $data['start_date'] ?? null);
        $this->setProgramColumnValue($payload, 'end_date', $data['end_date'] ?? null);
        $this->setProgramColumnValue($payload, 'image', $data['image'] ?? null);
        $this->setProgramColumnValue($payload, 'intro', $data['intro'] ?? null);
        $this->setProgramColumnValue($payload, 'description', $data['description'] ?? null);
        $this->setProgramColumnValue($payload, 'overview', $data['overview'] ?? null);
        $this->setProgramColumnValue($payload, 'icon_key', $data['icon_key'] ?? null);
        $this->setProgramColumnValue($payload, 'is_active', array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true);
        $this->setProgramColumnValue($payload, 'objectives', $data['objectives'] ?? []);
        $this->setProgramColumnValue($payload, 'modules', $data['modules'] ?? []);
        $this->setProgramColumnValue($payload, 'curriculum', $this->prepareCurriculum($request->input('curriculum', [])));
        $this->setProgramColumnValue($payload, 'skills', $data['skills'] ?? []);
        $this->setProgramColumnValue($payload, 'outcomes', $data['outcomes'] ?? []);
        $this->setProgramColumnValue($payload, 'tools', $data['tools'] ?? []);
        $this->setProgramColumnValue($payload, 'experience_levels', $data['experience_levels'] ?? []);
        $this->setProgramColumnValue($payload, 'shifts', $this->prepareShifts($request->input('shifts', [])));

        return $payload;
    }

    private function buildProgramUpdatePayload(Program $program, array $data, Request $request): array
    {
        $name = array_key_exists('name', $data)
            ? trim((string) $data['name'])
            : (string) ($program->name ?? '');

        if ($name === '') {
            $name = (string) ($program->name ?? 'Untitled Program');
        }

        $incomingShifts = array_key_exists('shifts', $request->all())
            ? $this->prepareShifts($request->input('shifts', []))
            : ($program->shifts ?? []);

        $payload = [];

        $this->setProgramColumnValue($payload, 'slug', array_key_exists('slug', $data) ? $data['slug'] : $program->slug);
        $this->setProgramColumnValue($payload, 'name', $name);
        $this->setProgramColumnValue($payload, 'badge', array_key_exists('badge', $data) ? $data['badge'] : $program->badge);
        $this->setProgramColumnValue($payload, 'category', array_key_exists('category', $data) ? $data['category'] : $program->category);
        $this->setProgramColumnValue($payload, 'duration', array_key_exists('duration', $data) ? $data['duration'] : $program->duration);
        $this->setProgramColumnValue($payload, 'level', array_key_exists('level', $data) ? $data['level'] : $program->level);
        $this->setProgramColumnValue($payload, 'format', array_key_exists('format', $data) ? $data['format'] : $program->format);
        $this->setProgramColumnValue($payload, 'status', array_key_exists('status', $data) ? $data['status'] : $program->status);
        $this->setProgramColumnValue($payload, 'instructor', array_key_exists('instructor', $data) ? $data['instructor'] : $program->instructor);
        $this->setProgramColumnValue($payload, 'students', array_key_exists('students', $data) ? (int) $data['students'] : (int) ($program->students ?? 0));
        $this->setProgramColumnValue($payload, 'price', array_key_exists('price', $data) ? (float) $data['price'] : (float) ($program->price ?? 0));
        $this->setProgramColumnValue($payload, 'start_date', array_key_exists('start_date', $data) ? $data['start_date'] : $program->start_date);
        $this->setProgramColumnValue($payload, 'end_date', array_key_exists('end_date', $data) ? $data['end_date'] : $program->end_date);
        $this->setProgramColumnValue($payload, 'image', array_key_exists('image', $data) ? $data['image'] : $program->image);
        $this->setProgramColumnValue($payload, 'intro', array_key_exists('intro', $data) ? $data['intro'] : $program->intro);
        $this->setProgramColumnValue($payload, 'description', array_key_exists('description', $data) ? $data['description'] : $program->description);
        $this->setProgramColumnValue($payload, 'overview', array_key_exists('overview', $data) ? $data['overview'] : $program->overview);
        $this->setProgramColumnValue($payload, 'icon_key', array_key_exists('icon_key', $data) ? $data['icon_key'] : $program->icon_key);
        $this->setProgramColumnValue($payload, 'is_active', array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $program->is_active);
        $this->setProgramColumnValue($payload, 'objectives', array_key_exists('objectives', $data) ? $data['objectives'] : ($program->objectives ?? []));
        $this->setProgramColumnValue($payload, 'modules', array_key_exists('modules', $data) ? $data['modules'] : ($program->modules ?? []));
        $this->setProgramColumnValue(
            $payload,
            'curriculum',
            array_key_exists('curriculum', $request->all())
                ? $this->prepareCurriculum($request->input('curriculum', []))
                : ($program->curriculum ?? [])
        );
        $this->setProgramColumnValue($payload, 'skills', array_key_exists('skills', $data) ? $data['skills'] : ($program->skills ?? []));
        $this->setProgramColumnValue($payload, 'outcomes', array_key_exists('outcomes', $data) ? $data['outcomes'] : ($program->outcomes ?? []));
        $this->setProgramColumnValue($payload, 'tools', array_key_exists('tools', $data) ? $data['tools'] : ($program->tools ?? []));
        $this->setProgramColumnValue(
            $payload,
            'experience_levels',
            array_key_exists('experience_levels', $data)
                ? $data['experience_levels']
                : ($program->experience_levels ?? [])
        );
        $this->setProgramColumnValue($payload, 'shifts', $incomingShifts);

        return $payload;
    }

    private function normalizeIncomingCurriculum($curriculum): array
    {
        if (is_string($curriculum)) {
            $decoded = json_decode($curriculum, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $curriculum = $decoded;
            }
        }

        if (!is_array($curriculum)) {
            return [];
        }

        $normalized = [];

        foreach ($curriculum as $item) {
            if (!is_array($item)) {
                continue;
            }

            $status = trim((string) ($item['status'] ?? 'Not Started'));

            if (!in_array($status, $this->curriculumStatuses(), true)) {
                $status = 'Not Started';
            }

            $normalized[] = [
                'id' => $item['id'] ?? null,
                'title' => trim((string) ($item['title'] ?? $item['name'] ?? '')),
                'days' => (int) ($item['days'] ?? $item['day_count'] ?? 0),
                'description' => trim((string) ($item['description'] ?? '')),
                'status' => $status,
            ];
        }

        return $normalized;
    }

    private function validateCurriculumCollection($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            if (!array_key_exists('curriculum', $request->all())) {
                return;
            }

            $curriculum = $this->normalizeIncomingCurriculum($request->input('curriculum', []));
            $usedTitles = [];

            foreach ($curriculum as $index => $item) {
                $title = $item['title'];
                $days = (int) $item['days'];
                $description = $item['description'];
                $status = $item['status'];

                $hasAnyValue =
                    $title !== '' ||
                    $days > 0 ||
                    $description !== '' ||
                    $status !== '';

                if (!$hasAnyValue) {
                    continue;
                }

                if ($title === '') {
                    $validator->errors()->add("curriculum.$index.title", 'Module title is required.');
                }

                if ($days < 1) {
                    $validator->errors()->add("curriculum.$index.days", 'Days must be at least 1.');
                }

                if (!in_array($status, $this->curriculumStatuses(), true)) {
                    $validator->errors()->add("curriculum.$index.status", 'Invalid curriculum status.');
                }

                if ($title !== '') {
                    $lowerTitle = mb_strtolower($title);

                    if (in_array($lowerTitle, $usedTitles, true)) {
                        $validator->errors()->add("curriculum.$index.title", 'Module title must be unique in the same program.');
                    }

                    $usedTitles[] = $lowerTitle;
                }
            }
        });
    }

    private function prepareCurriculum($curriculum): array
    {
        $normalizedCurriculum = $this->normalizeIncomingCurriculum($curriculum);
        $prepared = [];

        foreach ($normalizedCurriculum as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $days = max((int) ($item['days'] ?? 0), 0);
            $description = trim((string) ($item['description'] ?? ''));
            $status = trim((string) ($item['status'] ?? 'Not Started'));

            if (!in_array($status, $this->curriculumStatuses(), true)) {
                $status = 'Not Started';
            }

            if ($title === '' && $days === 0 && $description === '') {
                continue;
            }

            $prepared[] = [
                'id' => !empty($item['id']) ? (string) $item['id'] : (string) Str::uuid(),
                'title' => $title,
                'days' => $days,
                'description' => $description !== '' ? $description : null,
                'status' => $status,
            ];
        }

        return array_values($prepared);
    }

    private function makeCurriculumSummary(array $curriculum): array
    {
        $totalDays = 0;
        $statusBreakdown = [
            'Not Started' => 0,
            'In Progress' => 0,
            'Completed' => 0,
            'On Hold' => 0,
        ];

        foreach ($curriculum as $item) {
            $totalDays += (int) ($item['days'] ?? 0);

            $status = $item['status'] ?? 'Not Started';
            if (array_key_exists($status, $statusBreakdown)) {
                $statusBreakdown[$status]++;
            }
        }

        return [
            'total_modules' => count($curriculum),
            'total_days' => $totalDays,
            'status_breakdown' => $statusBreakdown,
        ];
    }

    private function normalizeIncomingShifts($shifts): array
    {
        if (is_string($shifts)) {
            $decoded = json_decode($shifts, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $shifts = $decoded;
            }
        }

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

    private function getProgramShiftUsageCounts(int $programId): array
    {
        return ProgramApplication::query()
            ->where('program_id', $programId)
            ->whereNotNull('shift_id')
            ->whereIn('status', $this->activeApplicationStatuses())
            ->get(['shift_id'])
            ->groupBy(function ($application) {
                return (string) $application->shift_id;
            })
            ->map(function ($items) {
                return $items->count();
            })
            ->toArray();
    }

    private function syncPreparedShiftsWithApplications(int $programId, $shifts): array
    {
        $prepared = $this->prepareShifts($shifts);
        $usageCounts = $programId > 0 ? $this->getProgramShiftUsageCounts($programId) : [];

        $updated = [];

        foreach ($prepared as $shift) {
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

    private function formatProgram(Program $program): array
    {
        $data = $program->toArray();

        $shifts = $this->syncPreparedShiftsWithApplications((int) $program->id, $data['shifts'] ?? []);
        $curriculum = $this->prepareCurriculum($data['curriculum'] ?? []);

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
        $data['curriculum'] = $curriculum;

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

        $data['curriculum_summary'] = $this->makeCurriculumSummary($curriculum);

        $data['users'] = $program->relationLoaded('users')
            ? $program->users->map(function ($user) {
                return [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ];
            })->values()
            : [];

        $data['users_count'] = $program->relationLoaded('users')
            ? $program->users->count()
            : 0;

        return $data;
    }

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