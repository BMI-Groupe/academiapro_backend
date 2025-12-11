<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
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
        
        $classroom = Classroom::with(['schoolYear'])->findOrFail($id);

        // Si school_year_id n'est pas fourni, utiliser celui de la classe
        if (!$schoolYearId) {
            $schoolYearId = $classroom->school_year_id;
        }

        // Élèves inscrits dans cette classe pour l'année donnée
        $enrollments = Enrollment::where('classroom_id', $id)
            ->when($schoolYearId, function($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            })
            ->with(['student'])
            ->get();

        return ApiResponse::sendResponse(true, [
            'classroom' => $classroom,
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

        if ($assignmentId) {
            // Classement pour un examen spécifique
            return $this->rankingByAssignment($id, $assignmentId);
        } else {
            // Classement général pour l'année
            return $this->generalRanking($id, $schoolYearId);
        }
    }

    /**
     * Classement pour un examen spécifique
     */
    private function rankingByAssignment($classroomId, $assignmentId)
    {
        $assignment = Assignment::with(['subject'])->findOrFail($assignmentId);

        $grades = Grade::where('assignment_id', $assignmentId)
            ->whereHas('student.enrollments', function($q) use ($classroomId, $assignment) {
                $q->where('classroom_id', $classroomId)
                  ->where('school_year_id', $assignment->school_year_id);
            })
            ->with(['student'])
            ->orderBy('score', 'desc')
            ->get();

        $ranking = $grades->map(function($grade, $index) {
            return [
                'rank' => $index + 1,
                'student' => $grade->student,
                'score' => $grade->score,
                'max_score' => $grade->assignment->max_score ?? 20,
                'percentage' => ($grade->score / ($grade->assignment->max_score ?? 20)) * 100
            ];
        });

        return ApiResponse::sendResponse(true, [
            'assignment' => $assignment,
            'ranking' => $ranking,
            'average' => $grades->avg('score'),
            'highest' => $grades->max('score'),
            'lowest' => $grades->min('score')
        ], 'Classement de l\'examen', 200);
    }

    /**
     * Classement général pour l'année scolaire
     */
    private function generalRanking($classroomId, $schoolYearId)
    {
        // Récupérer tous les élèves de la classe
        $enrollments = Enrollment::where('classroom_id', $classroomId)
            ->where('school_year_id', $schoolYearId)
            ->with(['student'])
            ->get();

        $ranking = $enrollments->map(function($enrollment) use ($schoolYearId) {
            $student = $enrollment->student;
            
            // Calculer la moyenne générale de l'élève
            $averageScore = Grade::where('student_id', $student->id)
                ->whereHas('assignment', function($q) use ($schoolYearId) {
                    $q->where('school_year_id', $schoolYearId);
                })
                ->avg('score');

            return [
                'student' => $student,
                'average' => round($averageScore, 2),
                'grade_count' => Grade::where('student_id', $student->id)
                    ->whereHas('assignment', function($q) use ($schoolYearId) {
                        $q->where('school_year_id', $schoolYearId);
                    })
                    ->count()
            ];
        })
        ->sortByDesc('average')
        ->values()
        ->map(function($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });

        return ApiResponse::sendResponse(true, [
            'ranking' => $ranking,
            'class_average' => $ranking->avg('average'),
            'student_count' => $ranking->count()
        ], 'Classement général de la classe', 200);
    }

    /**
     * Liste des examens de la classe
     */
    public function assignments($id, Request $request)
    {
        $schoolYearId = $request->query('school_year_id');

        $assignments = Assignment::where('classroom_id', $id)
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
