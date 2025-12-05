<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SchoolYear;
use App\Models\ClassroomSubject;
use App\Services\ClassroomSubjectService;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassroomSubjectController extends Controller
{
    public function __construct(private ClassroomSubjectService $service)
    {
    }

    /**
     * GET /classrooms/{classroom}/subjects?school_year_id=X
     * Obtenir le programme d'une classe pour une année
     */
    public function index(Request $request, Classroom $classroom)
    {
        $schoolYearId = $request->get('school_year_id') 
            ?? SchoolYear::where('is_active', true)->first()?->id;

        if (!$schoolYearId) {
            return ApiResponse::sendResponse(false, [], 'Aucune année scolaire active.', 422);
        }

        $subjects = $this->service->getClassroomProgram($classroom->id, $schoolYearId);
        return ApiResponse::sendResponse(true, [$subjects], 'Programme récupéré.', 200);
    }

    /**
     * POST /classrooms/{classroom}/subjects
     * Body: { subject_id, coefficient, school_year_id? }
     * Assigner une matière à une classe
     */
    public function store(Request $request, Classroom $classroom)
    {
        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'coefficient' => 'required|integer|min:1|max:10',
            'school_year_id' => 'nullable|exists:school_years,id',
        ]);

        $schoolYearId = $validated['school_year_id'] 
            ?? SchoolYear::where('is_active', true)->first()?->id;

        if (!$schoolYearId) {
            return ApiResponse::sendResponse(false, [], 'Aucune année scolaire active.', 422);
        }

        DB::beginTransaction();
        try {
            $cs = $this->service->assignSubject(
                $classroom->id,
                $validated['subject_id'],
                $schoolYearId,
                $validated['coefficient']
            );

            DB::commit();
            return ApiResponse::sendResponse(true, [$cs->load(['subject', 'schoolYear'])], 'Matière assignée.', 201);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    /**
     * PUT /classrooms/{classroom}/subjects/{subject}
     * Body: { coefficient, school_year_id? }
     * Mettre à jour le coefficient d'une matière
     */
    public function update(Request $request, Classroom $classroom, Subject $subject)
    {
        $validated = $request->validate([
            'coefficient' => 'required|integer|min:1|max:10',
            'school_year_id' => 'nullable|exists:school_years,id',
        ]);

        $schoolYearId = $validated['school_year_id'] 
            ?? SchoolYear::where('is_active', true)->first()?->id;

        if (!$schoolYearId) {
            return ApiResponse::sendResponse(false, [], 'Aucune année scolaire active.', 422);
        }

        DB::beginTransaction();
        try {
            $cs = $this->service->updateCoefficient(
                $classroom->id,
                $subject->id,
                $schoolYearId,
                $validated['coefficient']
            );

            if (!$cs) {
                return ApiResponse::sendResponse(false, [], 'Matière non trouvée pour cette année.', 404);
            }

            DB::commit();
            return ApiResponse::sendResponse(true, [$cs->load(['subject', 'schoolYear'])], 'Coefficient mis à jour.', 200);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    /**
     * DELETE /classrooms/{classroom}/subjects/{subject}?school_year_id=X
     * Retirer une matière d'une classe
     */
    public function destroy(Request $request, Classroom $classroom, Subject $subject)
    {
        $schoolYearId = $request->get('school_year_id') 
            ?? SchoolYear::where('is_active', true)->first()?->id;

        if (!$schoolYearId) {
            return ApiResponse::sendResponse(false, [], 'Aucune année scolaire active.', 422);
        }

        DB::beginTransaction();
        try {
            $deleted = $this->service->removeSubject(
                $classroom->id,
                $subject->id,
                $schoolYearId
            );

            if (!$deleted) {
                return ApiResponse::sendResponse(false, [], 'Matière non trouvée pour cette année.', 404);
            }

            DB::commit();
            return ApiResponse::sendResponse(true, [], 'Matière retirée.', 200);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    /**
     * POST /classrooms/{classroom}/subjects/copy
     * Body: { from_year_id, to_year_id }
     * Copier le programme d'une année à une autre
     */
    public function copy(Request $request, Classroom $classroom)
    {
        $validated = $request->validate([
            'from_year_id' => 'required|exists:school_years,id',
            'to_year_id' => 'required|exists:school_years,id',
        ]);

        DB::beginTransaction();
        try {
            $count = $this->service->copyProgramToNewYear(
                $classroom->id,
                $validated['from_year_id'],
                $validated['to_year_id']
            );

            DB::commit();
            return ApiResponse::sendResponse(
                true,
                ['count' => $count],
                "$count matière(s) copiée(s).",
                200
            );
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }
}
