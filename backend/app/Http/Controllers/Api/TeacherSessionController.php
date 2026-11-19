<?php

namespace App\Http\Controllers\Api;

use App\Actions\SubmitTeacherSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSessionSubmissionRequest;
use App\Http\Resources\TeacherSessionResource;
use App\Support\SchoolSync;
use Illuminate\Http\Request;

class TeacherSessionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        return TeacherSessionResource::collection($request->user()->teacher->sessions()->with('group')->latest('timestamp')->latest('id')->paginate($request->integer('per_page', 20)))->additional(['meta' => SchoolSync::meta()]);
    }

    public function show(Request $request, int $session)
    {
        $record = $request->user()->teacher->sessions()->findOrFail($session);

        return (new TeacherSessionResource($record->load(['group', 'studentSessions.student', 'studentSessions.assessments.assignment'])))->additional(['meta' => SchoolSync::meta()]);
    }

    public function store(StoreSessionSubmissionRequest $request, SubmitTeacherSession $submit)
    {
        return $submit->execute($request->user()->teacher, $request->validated());
    }

    public function update(StoreSessionSubmissionRequest $request, int $session, SubmitTeacherSession $submit)
    {
        return $submit->execute($request->user()->teacher, $request->validated(), $session);
    }
}
