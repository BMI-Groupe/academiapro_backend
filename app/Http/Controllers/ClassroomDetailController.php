<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Assignment;
use Illuminate\Http\Request;
use App\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class ClassroomDetailController extends Controller
{
    /**
     * Obtenir les détails d'une classe avec ses élèves
     */
    public function show($id, Request $request)
    {
        $schoolYearId = $request->query('school_year_id');
        
        // Le binding de route peut passer un Section ou un ID
        $section = $id instanceof Section ? $id : Section::with(['schoolYear', 'classroomTemplate'])->findOrFail($id);
        
        // S'assurer que les relations sont chargées
        if (!$section->relationLoaded('schoolYear')) {
            $section->load('schoolYear');
        }
        if (!$section->relationLoaded('classroomTemplate')) {
            $section->load('classroomTemplate');
        }

        // Si school_year_id n'est pas fourni, utiliser celui de la section
        if (!$schoolYearId) {
            $schoolYearId = $section->school_year_id;
        }

        // Élèves inscrits dans cette section pour l'année donnée
        $sectionId = $section->id;
        $enrollments = Enrollment::where('section_id', $sectionId)
            ->when($schoolYearId, function($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            })
            ->with(['student'])
            ->get();

        return ApiResponse::sendResponse(true, [
            'classroom' => $section, // Alias pour compatibilité
            'section' => $section,
            'students' => $enrollments->pluck('student'),
            'student_count' => $enrollments->count()
        ], 'Détails de la classe', 200);
    }

    /**
     * Obtenir le classement des élèves pour un examen spécifique
     */
    public function ranking($id, Request $request)
    {
        $assignmentId = $request->query('assignment_id');
        $schoolYearId = $request->query('school_year_id');

        // Le binding de route peut passer un Section ou un ID
        $section = $id instanceof Section ? $id : Section::findOrFail($id);
        $sectionId = $section->id;

        if ($assignmentId) {
            // Classement pour un examen spécifique
            return $this->rankingByAssignment($sectionId, $assignmentId);
        } else {
            // Classement général pour l'année
            if (!$schoolYearId) {
                $schoolYearId = $section->school_year_id;
            }
            return $this->generalRanking($sectionId, $schoolYearId);
        }
    }

    /**
     * Classement pour un examen spécifique
     */
    private function rankingByAssignment($sectionId, $assignmentId)
    {
        $assignment = Assignment::with(['subject', 'section'])->findOrFail($assignmentId);
        $schoolYearId = $assignment->school_year_id;

        // Vérifier que l'assignment appartient bien à la section
        if ($assignment->section_id != $sectionId) {
            return ApiResponse::sendResponse(false, [], 'Cet examen n\'appartient pas à cette classe.', 400);
        }

        // Récupérer toutes les notes pour cet examen, en s'assurant que les élèves sont bien dans la section
        $grades = Grade::where('assignment_id', $assignmentId)
            ->whereHas('student.enrollments', function($q) use ($sectionId, $schoolYearId) {
                $q->where('section_id', $sectionId)
                  ->where('school_year_id', $schoolYearId);
            })
            ->with(['student'])
            ->get();

        if ($grades->isEmpty()) {
            return ApiResponse::sendResponse(true, [
                'assignment' => $assignment,
                'ranking' => [],
                'average' => 0,
                'highest' => 0,
                'lowest' => 0,
                'student_count' => 0
            ], 'Aucune note enregistrée pour cet examen.', 200);
        }

        // Construire le classement
        $maxScore = $assignment->max_score ?? 20;
        
        $ranking = $grades->map(function($grade) use ($maxScore) {
            return [
                'student' => $grade->student,
                'score' => $grade->score,
                'max_score' => $maxScore,
                'percentage' => ($grade->score / $maxScore) * 100
            ];
        })
        ->sortByDesc('score')
        ->values()
        ->map(function($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });

        $scores = $ranking->pluck('score');

        return ApiResponse::sendResponse(true, [
            'assignment' => $assignment,
            'ranking' => $ranking,
            'average' => $scores->count() > 0 ? round($scores->avg(), 2) : 0,
            'highest' => $scores->count() > 0 ? $scores->max() : 0,
            'lowest' => $scores->count() > 0 ? $scores->min() : 0,
            'student_count' => $ranking->count()
        ], 'Classement de l\'examen', 200);
    }

    /**
     * Classement général pour l'année scolaire
     */
    private function generalRanking($sectionId, $schoolYearId)
    {
        // Récupérer tous les élèves de la section
        $enrollments = Enrollment::where('section_id', $sectionId)
            ->where('school_year_id', $schoolYearId)
            ->with(['student'])
            ->get();

        // Récupérer tous les assignments de la section pour cette année
        $assignmentIds = Assignment::where('section_id', $sectionId)
            ->where('school_year_id', $schoolYearId)
            ->pluck('id');

        if ($assignmentIds->isEmpty()) {
            return ApiResponse::sendResponse(true, [
                'ranking' => [],
                'class_average' => 0,
                'student_count' => 0
            ], 'Aucun examen/devoir pour cette classe et cette année.', 200);
        }

        // Récupérer la section avec ses matières pour calculer les moyennes pondérées
        $section = Section::with(['subjects' => function($q) use ($schoolYearId) {
            $q->where('section_subjects.school_year_id', $schoolYearId);
        }])->find($sectionId);

        $ranking = $enrollments->map(function($enrollment) use ($assignmentIds, $section) {
            $student = $enrollment->student;
            
            // Récupérer toutes les notes de l'élève pour les assignments de cette section
            $grades = Grade::where('student_id', $student->id)
                ->whereIn('assignment_id', $assignmentIds)
                ->with(['assignment.subject'])
                ->get();

            if ($grades->isEmpty()) {
                return [
                    'student' => $student,
                    'average' => 0,
                    'grade_count' => 0
                ];
            }

            // Calculer la moyenne pondérée par coefficient
            // Grouper les notes par matière
            $gradesBySubject = $grades->groupBy(function($grade) {
                return $grade->assignment->subject_id ?? 'unknown';
            });

            $totalWeightedScore = 0;
            $totalCoefficient = 0;

            foreach ($gradesBySubject as $subjectId => $subjectGrades) {
                // Calculer la moyenne de la matière
                $subjectAverage = $subjectGrades->avg('score');
                
                // Récupérer le coefficient de la matière
                $coefficient = 1; // Par défaut
                if ($section && $subjectId !== 'unknown') {
                    $sectionSubject = $section->subjects->firstWhere('id', $subjectId);
                    if ($sectionSubject && isset($sectionSubject->pivot->coefficient)) {
                        $coefficient = $sectionSubject->pivot->coefficient;
                    }
                }

                $totalWeightedScore += $subjectAverage * $coefficient;
                $totalCoefficient += $coefficient;
            }

            // Calculer la moyenne générale pondérée
            $averageScore = $totalCoefficient > 0 ? ($totalWeightedScore / $totalCoefficient) : 0;

            return [
                'student' => $student,
                'average' => round($averageScore, 2),
                'grade_count' => $grades->count()
            ];
        })
        ->filter(function($item) {
            // Inclure seulement les élèves qui ont au moins une note
            return $item['grade_count'] > 0;
        })
        ->sortByDesc('average')
        ->values()
        ->map(function($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });

        $averages = $ranking->pluck('average')->filter(function($avg) {
            return $avg > 0;
        });

        return ApiResponse::sendResponse(true, [
            'ranking' => $ranking,
            'class_average' => $averages->count() > 0 ? round($averages->avg(), 2) : 0,
            'student_count' => $ranking->count()
        ], 'Classement général de la classe', 200);
    }

    /**
     * Liste des examens de la classe
     */
    public function assignments($id, Request $request)
    {
        $schoolYearId = $request->query('school_year_id');

        // Le binding de route peut passer un Section ou un ID
        $section = $id instanceof Section ? $id : Section::findOrFail($id);
        $sectionId = $section->id;

        $assignments = Assignment::where('section_id', $sectionId)
            ->when($schoolYearId, function($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            })
            ->with(['subject', 'schoolYear'])
            ->withCount('grades')
            ->orderBy('due_date', 'desc')
            ->get();

        return ApiResponse::sendResponse(true, [$assignments], 'Examens de la classe', 200);
    }
}
