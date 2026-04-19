<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Intake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IntakeController extends Controller
{
    public function index()
    {
        $intakes = Intake::withCount('programs')
            ->latest()
            ->get()
            ->map(function ($intake) {
                return $this->formatIntake($intake);
            });

        return response()->json([
            'success' => true,
            'message' => 'Intakes retrieved successfully.',
            'data' => $intakes,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:intakes,name',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $intake = Intake::create([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : true,
        ]);

        $intake->loadCount('programs');

        return response()->json([
            'success' => true,
            'message' => 'Intake created successfully.',
            'data' => $this->formatIntake($intake),
        ], 201);
    }

    public function show(string $id)
    {
        $intake = Intake::with([
            'programs:id,intake_id,code,name,slug,badge,category,duration,level,format,status,instructor,students,price,start_date,end_date,image,intro,description,overview,icon_key,is_active'
        ])->withCount('programs')->find($id);

        if (!$intake) {
            return response()->json([
                'success' => false,
                'message' => 'Intake not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Intake retrieved successfully.',
            'data' => $this->formatIntake($intake, true),
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $intake = Intake::find($id);

        if (!$intake) {
            return response()->json([
                'success' => false,
                'message' => 'Intake not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('intakes', 'name')->ignore($intake->id),
            ],
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $intake->update([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $intake->is_active,
        ]);

        $intake->refresh()->loadCount('programs');

        return response()->json([
            'success' => true,
            'message' => 'Intake updated successfully.',
            'data' => $this->formatIntake($intake),
        ], 200);
    }

    public function destroy(string $id)
    {
        $intake = Intake::withCount('programs')->find($id);

        if (!$intake) {
            return response()->json([
                'success' => false,
                'message' => 'Intake not found.',
            ], 404);
        }

        if (($intake->programs_count ?? 0) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete intake with programs. Reassign or remove programs first.',
            ], 422);
        }

        $intake->delete();

        return response()->json([
            'success' => true,
            'message' => 'Intake deleted successfully.',
        ], 200);
    }

    private function formatIntake(Intake $intake, bool $withPrograms = false): array
    {
        $data = [
            'id' => $intake->id,
            'name' => $intake->name,
            'description' => $intake->description,
            'is_active' => (bool) $intake->is_active,
            'programs_count' => $intake->programs_count ?? 0,
            'created_at' => $intake->created_at,
            'updated_at' => $intake->updated_at,
        ];

        if ($withPrograms) {
            $data['programs'] = $intake->programs->map(function ($program) {
                return [
                    'id' => $program->id,
                    'intake_id' => $program->intake_id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'slug' => $program->slug,
                    'badge' => $program->badge,
                    'category' => $program->category,
                    'duration' => $program->duration,
                    'level' => $program->level,
                    'format' => $program->format,
                    'status' => $program->status,
                    'instructor' => $program->instructor,
                    'students' => $program->students,
                    'price' => $program->price,
                    'start_date' => $program->start_date,
                    'end_date' => $program->end_date,
                    'image' => $program->image,
                    'intro' => $program->intro,
                    'description' => $program->description,
                    'overview' => $program->overview,
                    'icon_key' => $program->icon_key,
                    'is_active' => (bool) $program->is_active,
                ];
            })->values();
        }

        return $data;
    }
}