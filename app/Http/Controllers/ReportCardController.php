<?php

namespace App\Http\Controllers;

use App\Models\ReportCard;
use App\Models\Student;
use App\Models\Grade;
use App\Models\ClassroomSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Responses\ApiResponse;

class ReportCardController extends Controller
{
    /**
     * List report cards for a student.
     */
    public function index(Request $request, $studentId)
    {
        $student = Student::findOrFail($studentId);
        
        $query = $student->reportCards()->with(['schoolYear', 'classroom']);

        if ($request->has('school_year_id')) {
            $query->where('school_year_id', $request->school_year_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    /**
     * Show a detailed report card.
     */
    public function show($id)
    {
        $reportCard = ReportCard::with(['student', 'schoolYear', 'classroom.school'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->buildReportCardData($reportCard)
        ]);
    }

    /**
     * Build the structured data for the report card view.
     */
    private function buildReportCardData(ReportCard $reportCard)
    {
        // 1. Get all grades for this report card's period context
        // Grades are linked to assignments which hold the school year and classroom info
        $query = Grade::where('student_id', $reportCard->student_id)
            ->whereHas('assignment', function($q) use ($reportCard) {
                $q->where('school_year_id', $reportCard->school_year_id)
                  ->where('classroom_id', $reportCard->classroom_id);
                  
                if ($reportCard->period) {
                    $q->where('period', $reportCard->period);
                }
            })
            ->with(['assignment.subject']); // Load assignment and subject

        $grades = $query->get();

        // 2. Group by subject
        $subjectsData = [];
        $gradesBySubject = $grades->groupBy(function($grade) {
            return $grade->assignment->subject_id;
        });

        foreach ($gradesBySubject as $subjectId => $subjectGrades) {
            if ($subjectGrades->isEmpty()) continue;

            $firstGrade = $subjectGrades->first();
            $subject = $firstGrade->assignment->subject;
            
            // Get coefficient
            $classroomSubject = ClassroomSubject::where('classroom_id', $reportCard->classroom_id)
                ->where('subject_id', $subjectId)
                ->where('school_year_id', $reportCard->school_year_id)
                ->first();
            
            $coefficient = $classroomSubject ? $classroomSubject->coefficient : 1;
            $studentAverage = $this->calculateSubjectAverage($subjectGrades);

            $classStats = $this->getClassStats($reportCard, $subjectId);

            $subjectsData[] = [
                'name' => $subject->name,
                'teacher' => 'Enseignant', 
                'coefficient' => $coefficient,
                'studentAverage' => $studentAverage,
                'classMin' => $classStats['min'],
                'classMax' => $classStats['max'],
                'classAverage' => $classStats['avg'],
                'appreciation' => 'Trimestre satisfaisant', 
            ];
        }

        // Check if classroom exists (might be hidden by scope or soft deleted)
        $classroomName = $reportCard->classroom ? $reportCard->classroom->name : 'Classe inconnue';
        $schoolName = $reportCard->classroom && $reportCard->classroom->school ? $reportCard->classroom->school->name : 'École';
        $schoolAddress = $reportCard->classroom && $reportCard->classroom->school ? $reportCard->classroom->school->address : '';
        $schoolPhone = $reportCard->classroom && $reportCard->classroom->school ? $reportCard->classroom->school->phone : '';

        return [
            'id' => $reportCard->id,
            'student' => [
                'firstName' => $reportCard->student->first_name,
                'lastName' => $reportCard->student->last_name,
                'matricule' => $reportCard->student->matricule,
            ],
            'school' => [
                'name' => $schoolName,
                'address' => $schoolAddress,
                'phone' => $schoolPhone,
            ],
            'schoolYear' => $reportCard->schoolYear->label,
            'period' => $reportCard->period ? ($reportCard->schoolYear->period_system === 'semester' ? 'Semestre ' . $reportCard->period : 'Trimestre ' . $reportCard->period) : 'Annuel',
            'classroom' => $classroomName,
            'subjects' => $subjectsData,
            'generalAverage' => $reportCard->average,
            'rank' => $reportCard->rank,
            'totalStudents' => 0, 
            'absences' => $reportCard->absences ?? 0,
            'mention' => $this->getMention($reportCard->average),
            'councilAppreciation' => $reportCard->comments ?? ''
        ];
    }

    public function update(Request $request, ReportCard $reportCard)
    {
        $validated = $request->validate([
            'absences' => 'nullable|integer|min:0',
            'comments' => 'nullable|string'
        ]);

        $reportCard->update($validated);

        return ApiResponse::sendResponse(true, [$reportCard], 'Bulletin mis à jour.', 200);
    }

    private function calculateSubjectAverage($grades) {
        if ($grades->isEmpty()) return 0;
        return round($grades->avg('score'), 2);
    }

    private function getClassStats($reportCard, $subjectId) {
        // Query grades VIA assignment to filter by year/classroom/period
        $query = Grade::whereHas('assignment', function($q) use ($reportCard, $subjectId) {
             $q->where('school_year_id', $reportCard->school_year_id)
               ->where('classroom_id', $reportCard->classroom_id)
               ->where('subject_id', $subjectId);
               
             if ($reportCard->period) {
                $q->where('period', $reportCard->period);
             }
        });

        // We need averages PER STUDENT first
        $allGrades = $query->get();
        // ... rest logic same

        // We need averages PER STUDENT first
        $allGrades = $query->get();
        $studentAverages = $allGrades->groupBy('student_id')->map(function($g) {
            return $g->avg('score');
        });

        if ($studentAverages->isEmpty()) {
            return ['min' => 0, 'max' => 0, 'avg' => 0];
        }

        return [
            'min' => round($studentAverages->min(), 2),
            'max' => round($studentAverages->max(), 2),
            'avg' => round($studentAverages->avg(), 2),
        ];
    }

    private function getMention($average) {
        if ($average >= 18) return 'Félicitations';
        if ($average >= 16) return 'Très Bien';
        if ($average >= 14) return 'Bien';
        if ($average >= 12) return 'Assez Bien';
        if ($average >= 10) return 'Passable';
        return 'Insuffisant';
    }

    public function generate(Request $request, $studentId)
    {
       // Implementation omitted for brevity, logic moved to Jobs
        return response()->json(['message' => 'Use artisan reports:recalculate for now']);
    }

    public function download($id)
    {
        return response()->json(['message' => 'PDF Not implemented yet']);
    }
}
