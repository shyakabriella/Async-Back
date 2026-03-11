<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ProgramApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records.
     */
    public function index(Request $request)
    {
        $query = Attendance::with([
            'application',
            'program',
            'markedByUser',
        ])->latest();

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        if ($request->filled('program_application_id')) {
            $query->where('program_application_id', $request->program_application_id);
        }

        if ($request->filled('attendance_date')) {
            $query->whereDate('attendance_date', $request->attendance_date);
        }

        if ($request->filled('shift_ref')) {
            $query->where('shift_ref', $request->shift_ref);
        }

        $attendances = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Attendance records retrieved successfully.',
            'data' => $attendances,
        ], 200);
    }

    /**
     * Store a newly created attendance record.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'program_application_id' => 'required|exists:program_applications,id',
            'program_id' => 'nullable|exists:programs,id',
            'shift_ref' => 'nullable|string|max:255',
            'shift_name' => 'nullable|string|max:255',
            'attendance_date' => 'required|date',
            'status' => 'required|in:Present,Absent,Late,Excused,Not Marked',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $application = ProgramApplication::with([
            'program',
        ])->find($data['program_application_id']);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found.',
            ], 404);
        }

        $resolvedProgramId = $data['program_id'] ?? $this->resolveProgramId($application);
        $resolvedShiftRef = $data['shift_ref'] ?? $this->resolveShiftRef($application);
        $resolvedShiftName = $data['shift_name'] ?? $this->resolveShiftName($application);

        $attendance = Attendance::updateOrCreate(
            [
                'program_application_id' => $application->id,
                'attendance_date' => $data['attendance_date'],
                'shift_ref' => (string) ($resolvedShiftRef ?? ''),
            ],
            [
                'program_id' => $resolvedProgramId,
                'shift_name' => $resolvedShiftName,
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
                'marked_by' => auth()->id(),
            ]
        );

        $attendance->load([
            'application',
            'program',
            'markedByUser',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance saved successfully.',
            'data' => $attendance,
        ], 201);
    }

    /**
     * Display the specified attendance record.
     */
    public function show(string $id)
    {
        $attendance = Attendance::with([
            'application',
            'program',
            'markedByUser',
        ])->find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance record retrieved successfully.',
            'data' => $attendance,
        ], 200);
    }

    /**
     * Update the specified attendance record.
     */
    public function update(Request $request, string $id)
    {
        $attendance = Attendance::find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'program_id' => 'nullable|exists:programs,id',
            'shift_ref' => 'nullable|string|max:255',
            'shift_name' => 'nullable|string|max:255',
            'attendance_date' => 'nullable|date',
            'status' => 'nullable|in:Present,Absent,Late,Excused,Not Marked',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $attendance->update([
            'program_id' => $data['program_id'] ?? $attendance->program_id,
            'shift_ref' => array_key_exists('shift_ref', $data)
                ? (string) ($data['shift_ref'] ?? '')
                : $attendance->shift_ref,
            'shift_name' => array_key_exists('shift_name', $data)
                ? $data['shift_name']
                : $attendance->shift_name,
            'attendance_date' => $data['attendance_date'] ?? $attendance->attendance_date,
            'status' => $data['status'] ?? $attendance->status,
            'note' => array_key_exists('note', $data) ? $data['note'] : $attendance->note,
            'marked_by' => auth()->id(),
        ]);

        $attendance->load([
            'application',
            'program',
            'markedByUser',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance updated successfully.',
            'data' => $attendance,
        ], 200);
    }

    /**
     * Remove the specified attendance record.
     */
    public function destroy(string $id)
    {
        $attendance = Attendance::find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found.',
            ], 404);
        }

        $attendance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance deleted successfully.',
        ], 200);
    }

    /**
     * Resolve program id from application.
     */
    private function resolveProgramId(ProgramApplication $application): ?int
    {
        if (!empty($application->program_id)) {
            return (int) $application->program_id;
        }

        if ($application->relationLoaded('program') && $application->program) {
            return (int) $application->program->id;
        }

        return null;
    }

    /**
     * Resolve shift reference from application.
     */
    private function resolveShiftRef(ProgramApplication $application): string
    {
        if (!empty($application->shift_id)) {
            return (string) $application->shift_id;
        }

        if (!empty($application->shift_ref)) {
            return (string) $application->shift_ref;
        }

        if (!empty($application->shift_name)) {
            return (string) $application->shift_name;
        }

        $shift = $this->normalizePossibleArray($application->shift ?? null);

        if (is_array($shift)) {
            return (string) ($shift['id'] ?? $shift['ref'] ?? $shift['name'] ?? '');
        }

        if (is_string($application->shift ?? null)) {
            return (string) $application->shift;
        }

        return '';
    }

    /**
     * Resolve shift display name from application.
     */
    private function resolveShiftName(ProgramApplication $application): ?string
    {
        if (!empty($application->shift_name)) {
            return (string) $application->shift_name;
        }

        $shift = $this->normalizePossibleArray($application->shift ?? null);

        if (is_array($shift)) {
            return $shift['name'] ?? $shift['label'] ?? $shift['title'] ?? null;
        }

        if (is_string($application->shift ?? null) && trim((string) $application->shift) !== '') {
            return (string) $application->shift;
        }

        return null;
    }

    /**
     * Convert JSON string to array when possible.
     */
    private function normalizePossibleArray($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}