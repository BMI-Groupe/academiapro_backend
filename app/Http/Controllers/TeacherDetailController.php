<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\SectionSubjectTeacher;
use App\Models\SectionSubject;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SchoolYear;
use App\Http\Resources\TeacherResource;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TeacherDetailController extends Controller
{
    /**
     * Obtenir les détails d'un enseignant avec son historique d'affectations
     */
    public function show($id)
    {
        $teacher = Teacher::with([
            'sectionSubjectTeachers.sectionSubject.section.classroomTemplate',
            'sectionSubjectTeachers.sectionSubject.subject',
            'sectionSubjectTeachers.schoolYear',
            'user'
        ])->findOrFail($id);

        return ApiResponse::sendResponse(true, [new TeacherResource($teacher)], 'Détails de l\'enseignant', 200);
    }

    /**
     * Obtenir toutes les affectations d'un enseignant (groupées par année scolaire)
     */
    public function assignments($id)
    {
        $teacher = Teacher::findOrFail($id);

        $assignments = SectionSubjectTeacher::where('teacher_id', $id)
            ->with([
                'sectionSubject.section.classroomTemplate',
                'sectionSubject.section.schoolYear',
                'sectionSubject.subject',
                'schoolYear'
            ])
            ->orderBy('school_year_id', 'desc')
            ->get();

        // Grouper par année scolaire
        $groupedByYear = $assignments->groupBy('school_year_id')->map(function ($yearAssignments, $yearId) {
            $schoolYear = $yearAssignments->first()->schoolYear;
            
            return [
                'school_year_id' => $yearId,
                'school_year' => $schoolYear ? [
                    'id' => $schoolYear->id,
                    'label' => $schoolYear->label,
                    'start_date' => $schoolYear->start_date,
                    'end_date' => $schoolYear->end_date,
                    'is_active' => $schoolYear->is_active,
                ] : null,
                'assignments' => $yearAssignments->map(function ($assignment) {
                    $sectionSubject = $assignment->sectionSubject;
                    $section = $sectionSubject->section ?? null;
                    $subject = $sectionSubject->subject ?? null;
                    
                    return [
                        'id' => $assignment->id,
                        'section_subject_id' => $assignment->section_subject_id,
                        'section' => $section ? [
                            'id' => $section->id,
                            'name' => $section->display_name ?? $section->name,
                            'code' => $section->code,
                            'classroom_template' => $section->classroomTemplate ? [
                                'id' => $section->classroomTemplate->id,
                                'name' => $section->classroomTemplate->name,
                                'level' => $section->classroomTemplate->level,
                                'cycle' => $section->classroomTemplate->cycle,
                            ] : null,
                        ] : null,
                        'subject' => $subject ? [
                            'id' => $subject->id,
                            'name' => $subject->name,
                            'code' => $subject->code,
                        ] : null,
                        'coefficient' => $sectionSubject->coefficient ?? null,
                        'created_at' => $assignment->created_at,
                    ];
                }),
            ];
        })->values();

        return ApiResponse::sendResponse(true, [$groupedByYear], 'Affectations de l\'enseignant', 200);
    }

    /**
     * Affecter un enseignant à une section et une matière pour une année scolaire
     */
    public function assignSectionSubject(Request $request, $id)
    {
        if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
            return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
        }

        $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'school_year_id' => 'required|integer|exists:school_years,id',
        ]);

        DB::beginTransaction();
        try {
            $teacher = Teacher::findOrFail($id);
            $sectionId = $request->section_id;
            $subjectId = $request->subject_id;
            $schoolYearId = $request->school_year_id;

            // Vérifier que la matière est bien assignée à la section pour cette année
            $sectionSubject = SectionSubject::where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->where('school_year_id', $schoolYearId)
                ->first();

            if (!$sectionSubject) {
                return ApiResponse::sendResponse(false, [], 'Cette matière n\'est pas assignée à cette section pour cette année scolaire.', 422);
            }

            // Vérifier si l'affectation existe déjà
            $existing = SectionSubjectTeacher::where('section_subject_id', $sectionSubject->id)
                ->where('teacher_id', $teacher->id)
                ->where('school_year_id', $schoolYearId)
                ->first();

            if ($existing) {
                return ApiResponse::sendResponse(false, [], 'Cet enseignant est déjà affecté à cette section et matière pour cette année scolaire.', 422);
            }

            // Créer l'affectation
            $assignment = SectionSubjectTeacher::create([
                'section_subject_id' => $sectionSubject->id,
                'teacher_id' => $teacher->id,
                'school_year_id' => $schoolYearId,
            ]);

            DB::commit();

            // Recharger les données
            $teacher->load([
                'sectionSubjectTeachers.sectionSubject.section.classroomTemplate',
                'sectionSubjectTeachers.sectionSubject.subject',
                'sectionSubjectTeachers.schoolYear'
            ]);

            return ApiResponse::sendResponse(true, [new TeacherResource($teacher)], 'Enseignant affecté avec succès.', 200);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    /**
     * Réaffecter un enseignant (changer de section/matière pour une année)
     */
    public function reassignSectionSubject(Request $request, $id)
    {
        if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
            return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
        }

        $request->validate([
            'current_assignment_id' => 'required|integer|exists:section_subject_teachers,id',
            'section_id' => 'required|integer|exists:sections,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'school_year_id' => 'required|integer|exists:school_years,id',
        ]);

        DB::beginTransaction();
        try {
            $teacher = Teacher::findOrFail($id);
            $currentAssignment = SectionSubjectTeacher::findOrFail($request->current_assignment_id);

            // Vérifier que l'affectation appartient bien à cet enseignant
            if ($currentAssignment->teacher_id !== $teacher->id) {
                return ApiResponse::sendResponse(false, [], 'Cette affectation n\'appartient pas à cet enseignant.', 403);
            }

            $sectionId = $request->section_id;
            $subjectId = $request->subject_id;
            $schoolYearId = $request->school_year_id;

            // Vérifier que la matière est bien assignée à la section pour cette année
            $sectionSubject = SectionSubject::where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->where('school_year_id', $schoolYearId)
                ->first();

            if (!$sectionSubject) {
                return ApiResponse::sendResponse(false, [], 'Cette matière n\'est pas assignée à cette section pour cette année scolaire.', 422);
            }

            // Si c'est la même année scolaire, mettre à jour l'affectation
            if ($currentAssignment->school_year_id === $schoolYearId) {
                // Vérifier si une autre affectation existe déjà pour cette nouvelle combinaison
                $existing = SectionSubjectTeacher::where('section_subject_id', $sectionSubject->id)
                    ->where('teacher_id', $teacher->id)
                    ->where('school_year_id', $schoolYearId)
                    ->where('id', '!=', $currentAssignment->id)
                    ->first();

                if ($existing) {
                    return ApiResponse::sendResponse(false, [], 'Cet enseignant est déjà affecté à cette section et matière pour cette année scolaire.', 422);
                }

                // Mettre à jour l'affectation existante
                $currentAssignment->update([
                    'section_subject_id' => $sectionSubject->id,
                ]);
            } else {
                // Année scolaire différente : créer une nouvelle affectation
                // Vérifier si une affectation existe déjà
                $existing = SectionSubjectTeacher::where('section_subject_id', $sectionSubject->id)
                    ->where('teacher_id', $teacher->id)
                    ->where('school_year_id', $schoolYearId)
                    ->first();

                if ($existing) {
                    return ApiResponse::sendResponse(false, [], 'Cet enseignant est déjà affecté à cette section et matière pour cette année scolaire.', 422);
                }

                // Créer la nouvelle affectation
                SectionSubjectTeacher::create([
                    'section_subject_id' => $sectionSubject->id,
                    'teacher_id' => $teacher->id,
                    'school_year_id' => $schoolYearId,
                ]);
            }

            DB::commit();

            // Recharger les données
            $teacher->load([
                'sectionSubjectTeachers.sectionSubject.section.classroomTemplate',
                'sectionSubjectTeachers.sectionSubject.subject',
                'sectionSubjectTeachers.schoolYear'
            ]);

            return ApiResponse::sendResponse(true, [new TeacherResource($teacher)], 'Enseignant réaffecté avec succès.', 200);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    /**
     * Retirer une affectation d'un enseignant
     */
    public function removeAssignment(Request $request, $id, $assignmentId)
    {
        if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
            return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
        }

        DB::beginTransaction();
        try {
            $teacher = Teacher::findOrFail($id);
            $assignment = SectionSubjectTeacher::findOrFail($assignmentId);

            // Vérifier que l'affectation appartient bien à cet enseignant
            if ($assignment->teacher_id !== $teacher->id) {
                return ApiResponse::sendResponse(false, [], 'Cette affectation n\'appartient pas à cet enseignant.', 403);
            }

            $assignment->delete();

            DB::commit();

            // Recharger les données
            $teacher->load([
                'sectionSubjectTeachers.sectionSubject.section.classroomTemplate',
                'sectionSubjectTeachers.sectionSubject.subject',
                'sectionSubjectTeachers.schoolYear'
            ]);

            return ApiResponse::sendResponse(true, [new TeacherResource($teacher)], 'Affectation retirée avec succès.', 200);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }
}

