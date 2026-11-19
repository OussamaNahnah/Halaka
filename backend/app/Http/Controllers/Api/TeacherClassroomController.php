<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherAssignmentResource;
use App\Models\Group;
use App\Models\Student;
use App\Support\SchoolSync;
use Illuminate\Http\Request;

class TeacherClassroomController extends Controller
{
    public function groups(Request $request)
    {
        $last = $request->user()->teacher->sessions()->whereHas('group')->whereNotNull('group_id')->orderByDesc('timestamp')->orderByDesc('id')->value('group_id');
        $groups = Group::withCount('students')->orderBy('name')->get()->map(fn ($group) => ['id' => $group->id, 'name' => $group->name, 'students_count' => $group->students_count, 'suggested' => $group->id === $last]);

        return response()->json(['data' => $groups, 'meta' => SchoolSync::meta() + ['suggested_group_id' => $last]]);
    }

    public function students(int $group)
    {
        $group = Group::findOrFail($group);

        return response()->json(['data' => $group->students()->orderBy('name')->get(['students.id', 'students.name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name]), 'meta' => SchoolSync::meta()]);
    }

    public function assignments(int $student)
    {
        $student = Student::findOrFail($student);

        return TeacherAssignmentResource::collection($student->assignments()->with(['assessments.studentSession.session'])->orderBy('id')->get())->additional(['meta' => SchoolSync::meta()]);
    }

    public function surahs()
    {
        return response()->json(['data' => collect(config('quran'))->map(fn ($row, $id) => ['id' => $id, 'name' => $row['name'], 'verses' => $row['verses']])->values()]);
    }
}
