<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Assignment;
use App\Http\Resources\StudentResource;
use Illuminate\Http\Request;
use App\Responses\ApiResponse;

class StudentDetailController extends Controller
{
    /**
     * Obtenir les détails complets d'un élève
     */
    public function show($id)
    {
        $student = Student::with([
            'enrollments.classroom',
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
            ->with(['classroom', 'schoolYear'])
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
}
