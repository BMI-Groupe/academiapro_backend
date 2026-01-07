<?php

namespace App\Http\Controllers;

use App\Models\ClassroomTemplate;
use App\Models\Subject;
use App\Models\ClassroomTemplateSubject;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ClassroomTemplateSubjectController extends Controller
{
    /**
     * GET /classroom-templates/{template}/subjects?school_year_id=X
     * Obtenir les matières d'un template pour une année scolaire
     */
    public function index(Request $request, ClassroomTemplate $template)
    {
        $schoolYearId = $request->get('school_year_id');
        
        if (!$schoolYearId) {
            return ApiResponse::sendResponse(false, [], 'Une année scolaire doit être spécifiée.', 422);
        }

        $subjects = $template->templateSubjects()
            ->where('school_year_id', $schoolYearId)
            ->with('subject')
            ->get()
            ->map(function ($templateSubject) {
                return [
                    'id' => $templateSubject->id,
                    'subject_id' => $templateSubject->subject_id,
                    'school_year_id' => $templateSubject->school_year_id,
                    'coefficient' => $templateSubject->coefficient,
                    'subject' => $templateSubject->subject ? [
                        'id' => $templateSubject->subject->id,
                        'name' => $templateSubject->subject->name,
                        'code' => $templateSubject->subject->code,
                    ] : null,
                ];
            });
        return ApiResponse::sendResponse(true, [$subjects], 'Matières du template récupérées.', 200);
    }

    /**
     * POST /classroom-templates/{template}/subjects
     * Body: { subject_id, coefficient }
     * Assigner une matière à un template
     */
    public function store(Request $request, ClassroomTemplate $template)
    {
        if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
            return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
        }

        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'coefficient' => 'required|integer|min:1|max:10',
            'school_year_id' => 'required|exists:school_years,id',
        ]);

        DB::beginTransaction();
        try {
            $templateSubject = ClassroomTemplateSubject::updateOrCreate(
                [
                    'classroom_template_id' => $template->id,
                    'subject_id' => $validated['subject_id'],
                    'school_year_id' => $validated['school_year_id'],
                ],
                [
                    'coefficient' => $validated['coefficient'],
                ]
            );

            // Propager les changements aux sections existantes de ce template pour cette année
            $this->propagateToSections($template, $validated['subject_id'], $validated['coefficient'], $validated['school_year_id']);

            DB::commit();
            return ApiResponse::sendResponse(
                true,
                [$templateSubject->load('subject')],
                'Matière assignée au template. Les sections existantes ont été mises à jour.',
                201
            );
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    /**
     * PUT /classroom-templates/{template}/subjects/{subject}
     * Body: { coefficient }
     * Mettre à jour le coefficient d'une matière dans un template
     */
    public function update(Request $request, ClassroomTemplate $template, Subject $subject)
    {
        if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
            return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
        }

        $validated = $request->validate([
            'coefficient' => 'required|integer|min:1|max:10',
            'school_year_id' => 'required|exists:school_years,id',
        ]);

        DB::beginTransaction();
        try {
            $templateSubject = ClassroomTemplateSubject::where('classroom_template_id', $template->id)
                ->where('subject_id', $subject->id)
                ->where('school_year_id', $validated['school_year_id'])
                ->first();

            if (!$templateSubject) {
                return ApiResponse::sendResponse(false, [], 'Matière non trouvée pour ce template et cette année scolaire.', 404);
            }

            $templateSubject->update(['coefficient' => $validated['coefficient']]);

            // Propager les changements aux sections existantes pour cette année
            $this->propagateToSections($template, $subject->id, $validated['coefficient'], $validated['school_year_id']);

            DB::commit();
            return ApiResponse::sendResponse(
                true,
                [$templateSubject->load('subject')],
                'Coefficient mis à jour. Les sections existantes ont été mises à jour.',
                200
            );
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    /**
     * DELETE /classroom-templates/{template}/subjects/{subject}?school_year_id=X
     * Retirer une matière d'un template pour une année scolaire
     */
    public function destroy(Request $request, ClassroomTemplate $template, Subject $subject)
    {
        if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
            return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
        }

        $schoolYearId = $request->get('school_year_id');
        if (!$schoolYearId) {
            return ApiResponse::sendResponse(false, [], 'Une année scolaire doit être spécifiée.', 422);
        }

        DB::beginTransaction();
        try {
            $templateSubject = ClassroomTemplateSubject::where('classroom_template_id', $template->id)
                ->where('subject_id', $subject->id)
                ->where('school_year_id', $schoolYearId)
                ->first();

            if (!$templateSubject) {
                return ApiResponse::sendResponse(false, [], 'Matière non trouvée pour ce template et cette année scolaire.', 404);
            }

            // Note: On ne supprime pas automatiquement les matières des sections existantes
            // car elles peuvent avoir été modifiées manuellement. On supprime seulement du template.
            $templateSubject->delete();

            DB::commit();
            return ApiResponse::sendResponse(true, [], 'Matière retirée du template.', 200);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    /**
     * Propager les changements du template aux sections existantes pour une année donnée
     */
    private function propagateToSections(ClassroomTemplate $template, int $subjectId, int $coefficient, int $schoolYearId): void
    {
        // Récupérer toutes les sections de ce template pour cette année scolaire
        $sections = $template->sections()
            ->where('school_year_id', $schoolYearId)
            ->get();

        foreach ($sections as $section) {
            \App\Models\SectionSubject::updateOrCreate(
                [
                    'section_id' => $section->id,
                    'subject_id' => $subjectId,
                    'school_year_id' => $schoolYearId,
                ],
                [
                    'coefficient' => $coefficient,
                ]
            );
        }
    }
}
