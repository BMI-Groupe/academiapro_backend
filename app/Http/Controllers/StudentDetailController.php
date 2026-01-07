<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Assignment;
use App\Models\Section;
use App\Http\Resources\StudentResource;
use Illuminate\Http\Request;
use App\Responses\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentDetailController extends Controller
{
    /**
     * Obtenir les détails complets d'un élève
     */
    public function show($id)
    {
        $student = Student::with([
            'enrollments.section.classroomTemplate',
            'enrollments.schoolYear'
        ])->findOrFail($id);

        return ApiResponse::sendResponse(true, [new StudentResource($student)], 'Détails de l\'élève', 200);
    }

    /**
     * Obtenir l'historique des inscriptions d'un élève
     */
    public function enrollments($id)
    {
        $enrollments = Enrollment::where('student_id', $id)
            ->with(['section.classroomTemplate', 'schoolYear'])
            ->orderByRaw('CASE WHEN status = "active" THEN 0 WHEN status = "repeated" THEN 1 ELSE 2 END')
            ->orderBy('enrolled_at', 'desc')
            ->get();

        return ApiResponse::sendResponse(true, [$enrollments], 'Inscriptions de l\'élève', 200);
    }

    /**
     * Obtenir les notes d'un élève pour une année scolaire
     */
    public function grades($id, Request $request)
    {
        $schoolYearId = $request->query('school_year_id');
        
        $grades = Grade::where('student_id', $id)
            ->whereHas('assignment', function($q) use ($schoolYearId) {
                if ($schoolYearId) {
                    $q->where('school_year_id', $schoolYearId);
                }
            })
            ->with([
                'assignment.subject',
                'assignment.classroom',
                'assignment.schoolYear',
                'grader'
            ])
            ->orderBy('graded_at', 'desc')
            ->get();

        // Grouper par matière et calculer moyennes
        $gradesBySubject = $grades->groupBy('assignment.subject_id')->map(function($subjectGrades) {
            return [
                'subject' => $subjectGrades->first()->assignment->subject,
                'grades' => $subjectGrades,
                'average' => $subjectGrades->avg('score'),
                'count' => $subjectGrades->count()
            ];
        })->values();

        return ApiResponse::sendResponse(true, [
            'grades' => $grades,
            'by_subject' => $gradesBySubject,
            'overall_average' => $grades->avg('score')
        ], 'Notes de l\'élève', 200);
    }

    /**
     * Obtenir les examens d'un élève pour une année scolaire
     */
    public function assignments($id, Request $request)
    {
        $schoolYearId = $request->query('school_year_id');
        
        $assignments = Assignment::whereHas('grades', function($q) use ($id) {
                $q->where('student_id', $id);
            })
            ->when($schoolYearId, function($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            })
            ->with([
                'subject',
                'classroom',
                'schoolYear',
                'grades' => function($q) use ($id) {
                    $q->where('student_id', $id);
                }
            ])
            ->orderBy('due_date', 'desc')
            ->get();

        return ApiResponse::sendResponse(true, [$assignments], 'Examens de l\'élève', 200);
    }

    /**
     * Réaffecter un élève à une nouvelle classe pour une année scolaire
     */
    public function reassignClassroom(Request $request, $id)
    {
        if (!in_array(Auth::user()->role, ['admin', 'directeur'])) {
            return ApiResponse::sendResponse(false, [], 'Vous n\'êtes pas autorisé à effectuer cette action.', 403);
        }

        $request->validate([
            'classroom_id' => 'required|integer|exists:sections,id', // Alias pour compatibilité frontend
            'section_id' => 'nullable|integer|exists:sections,id',
            'school_year_id' => 'required|integer|exists:school_years,id',
            'current_enrollment_id' => 'nullable|integer|exists:enrollments,id',
            'current_school_year_id' => 'nullable|integer|exists:school_years,id',
        ]);

        DB::beginTransaction();
        try {
            $student = Student::findOrFail($id);
            $sectionId = $request->section_id ?? $request->classroom_id; // Support les deux pour compatibilité
            $section = Section::findOrFail($sectionId);

            // Récupérer l'inscription actuelle (celle qu'on modifie)
            $currentEnrollmentId = $request->input('current_enrollment_id');
            $currentEnrollment = $currentEnrollmentId 
                ? Enrollment::find($currentEnrollmentId)
                : Enrollment::where('student_id', $student->id)
                    ->where('school_year_id', $request->input('current_school_year_id'))
                    ->where('status', 'active')
                    ->first();

            // Vérifier si l'année scolaire change
            $isSameYear = $currentEnrollment && $currentEnrollment->school_year_id == $request->school_year_id;
            // Comparer la nouvelle section avec l'ancienne section de l'inscription actuelle
            $isSameClassAsCurrent = $currentEnrollment && $currentEnrollment->section_id == $sectionId;

            if ($isSameYear && $isSameClassAsCurrent) {
                // Aucune modification nécessaire
                DB::rollBack();
                return ApiResponse::sendResponse(false, [], 'L\'élève est déjà dans cette classe pour cette année scolaire.', 400);
            }

            if ($isSameYear) {
                // MÊME ANNÉE SCOLAIRE : Correction d'erreur d'affectation
                // Mettre à jour l'inscription existante
                if ($currentEnrollment) {
                    $currentEnrollment->update([
                        'section_id' => $sectionId,
                        'enrolled_at' => now(),
                        'status' => 'active',
                    ]);
                    $message = 'Élève réaffecté avec succès (correction d\'erreur d\'affectation).';
                } else {
                    // Si pas d'inscription trouvée, créer une nouvelle
                    Enrollment::create([
                        'student_id' => $student->id,
                        'section_id' => $sectionId,
                        'school_year_id' => $request->school_year_id,
                        'enrolled_at' => now(),
                        'status' => 'active',
                    ]);
                    $message = 'Élève inscrit dans la nouvelle classe avec succès.';
                }
            } else {
                // ANNÉE SCOLAIRE DIFFÉRENTE : Redoublement ou passage de classe
                // Marquer l'inscription actuelle comme 'completed' si elle existe et est active
                if ($currentEnrollment && $currentEnrollment->status === 'active') {
                    $currentEnrollment->update(['status' => 'completed']);
                }

                // Déterminer le statut de la nouvelle inscription
                // Si la nouvelle section a le même template que l'ancienne section = redoublement
                $isRedoublement = $currentEnrollment && $currentEnrollment->section 
                    && $currentEnrollment->section->classroom_template_id == $section->classroom_template_id;
                $newStatus = $isRedoublement ? 'repeated' : 'active';

                // Vérifier s'il existe déjà une inscription pour cette nouvelle année
                $existingEnrollmentForNewYear = Enrollment::where('student_id', $student->id)
                    ->where('school_year_id', $request->school_year_id)
                    ->where('status', 'active')
                    ->first();

                if ($existingEnrollmentForNewYear) {
                    // Si une inscription active existe déjà pour cette année, la mettre à jour
                    $existingEnrollmentForNewYear->update([
                        'section_id' => $sectionId,
                        'status' => $newStatus,
                        'enrolled_at' => now(),
                    ]);
                    $message = $isRedoublement 
                        ? 'Redoublement enregistré avec succès. L\'élève reste dans la même classe.'
                        : 'Passage de classe enregistré avec succès.';
                } else {
                    // Créer une nouvelle inscription
                    Enrollment::create([
                        'student_id' => $student->id,
                        'section_id' => $sectionId,
                        'school_year_id' => $request->school_year_id,
                        'enrolled_at' => now(),
                        'status' => $newStatus,
                    ]);
                    $message = $isRedoublement 
                        ? 'Redoublement enregistré avec succès. L\'élève reste dans la même classe.'
                        : 'Passage de classe enregistré avec succès.';
                }
            }

            // Synchroniser les matières de la nouvelle section (optionnel - ne bloque pas l'inscription si échoue)
            try {
                if (class_exists(\App\Models\StudentSubject::class)) {
                    $subjectIds = $section->subjects()->pluck('subjects.id')->toArray();
                    if (!empty($subjectIds)) {
                        $now = now();
                        $schoolYear = \App\Models\SchoolYear::find($request->school_year_id);
                        $schoolYearLabel = $schoolYear ? $schoolYear->label : null;

                        if ($schoolYearLabel) {
                            foreach ($subjectIds as $subjectId) {
                                \App\Models\StudentSubject::updateOrCreate(
                                    [
                                        'student_id' => $student->id,
                                        'subject_id' => $subjectId,
                                        'classroom_id' => $sectionId, // StudentSubject utilise encore classroom_id
                                        'school_year' => $schoolYearLabel,
                                    ],
                                    [
                                        'updated_at' => $now,
                                    ]
                                );
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas l'inscription
                \Log::warning('Erreur lors de la synchronisation des matières de l\'élève', [
                    'student_id' => $student->id,
                    'section_id' => $sectionId,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            // Recharger l'élève avec ses inscriptions
            $student->load(['enrollments.section.classroomTemplate', 'enrollments.schoolYear']);

            return ApiResponse::sendResponse(true, [new StudentResource($student)], $message, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            
            // Logger l'erreur complète pour le débogage
            \Log::error('Erreur lors de la réaffectation de l\'élève', [
                'student_id' => $id,
                'section_id' => $sectionId,
                'school_year_id' => $request->school_year_id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            
            // Message générique pour l'utilisateur (sans détails techniques)
            $errorMessage = 'Une erreur est survenue lors de la réaffectation de l\'élève. Veuillez réessayer.';
            
            // Message plus spécifique pour les erreurs de validation connues
            if (str_contains($th->getMessage(), 'not found') || str_contains($th->getMessage(), 'doesn\'t exist')) {
                $errorMessage = 'Une erreur de configuration est survenue. Veuillez contacter l\'administrateur.';
            } elseif (str_contains($th->getMessage(), 'foreign key') || str_contains($th->getMessage(), 'constraint')) {
                $errorMessage = 'Impossible de réaffecter l\'élève. Vérifiez que la classe et l\'année scolaire sont valides.';
            }
            
            return ApiResponse::sendResponse(false, [], $errorMessage, 500);
        }
    }
}
