<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherStudentAssessmentResource;
use App\Models\QuranAssessment;
use App\Models\Student;
use App\Models\StudentSession;
use App\Support\SchoolSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeacherStudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:100'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $search = trim($request->string('search')->toString());
        $students = Student::query()->select(['id', 'name'])
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->paginate($request->integer('per_page', 20));

        return response()->json(['data' => $students->items(), 'meta' => SchoolSync::meta() + ['current_page' => $students->currentPage(), 'last_page' => $students->lastPage(), 'total' => $students->total()]]);
    }

    public function summary(Request $request, int $student): JsonResponse
    {
        $student = Student::findOrFail($student);
        $attendance = $this->attendance($request, $student);
        $assessments = QuranAssessment::query()->whereIn('student_session_id', (clone $attendance)->select('id'));

        return response()->json(['data' => [
            'id' => $student->id,
            'name' => $student->name,
            'present_count' => (clone $attendance)->where('attendance_status', 'present')->count(),
            'absent_count' => (clone $attendance)->where('attendance_status', 'absent')->count(),
            'average_grade' => (clone $assessments)->avg('grade'),
            'assessment_count' => (clone $assessments)->count(),
            'pending_assignments_count' => $student->assignments()->where('is_passed', false)->count(),
        ], 'meta' => SchoolSync::meta()]);
    }

    public function assessments(Request $request, int $student): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $student = Student::findOrFail($student);
        $attendance = $this->attendance($request, $student);
        $rows = QuranAssessment::query()->whereIn('student_session_id', $attendance->select('id'))
            ->with(['assignment', 'studentSession.session'])
            ->join('student_sessions as roster', 'roster.id', '=', 'quran_assessments.student_session_id')
            ->join('teaching_sessions as lessons', 'lessons.id', '=', 'roster.session_id')
            ->select('quran_assessments.*')->orderByDesc('lessons.timestamp')->orderByDesc('quran_assessments.id')
            ->paginate($request->integer('per_page', 20));

        return TeacherStudentAssessmentResource::collection($rows)->additional(['meta' => SchoolSync::meta()]);
    }

    private function attendance(Request $request, Student $student): Builder
    {
        return StudentSession::query()->where('student_id', $student->id)
            ->whereHas('session', fn (Builder $query) => $query->where('teacher_id', $request->user()->teacher->id)->where('status', 'completed')->whereNull('deleted_at'));
    }
}
