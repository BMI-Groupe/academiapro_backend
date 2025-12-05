<?php

namespace App\Observers;

use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\SchoolYear;
use Illuminate\Support\Facades\Log;

class GradeObserver
{
    /**
     * Handle the Grade "created" event.
     */
    public function created(Grade $grade): void
    {
        $this->calculateStudentAverage($grade);
    }

    /**
     * Handle the Grade "updated" event.
     */
    public function updated(Grade $grade): void
    {
        $this->calculateStudentAverage($grade);
    }

    /**
     * Handle the Grade "deleted" event.
     */
    public function deleted(Grade $grade): void
    {
        $this->calculateStudentAverage($grade);
    }

    /**
     * Calculate student average and update ReportCard.
     */
    protected function calculateStudentAverage(Grade $grade): void
    {
        try {
            $student = $grade->student;
            $assignment = $grade->assignment;
            
            if (!$student || !$assignment) {
                return;
            }

            $schoolYearId = $assignment->school_year_id;
            $classroomId = $assignment->classroom_id;

            // Get all grades for this student in this school year
            $grades = Grade::whereHas('assignment', function ($query) use ($schoolYearId, $classroomId) {
                $query->where('school_year_id', $schoolYearId)
                      ->where('classroom_id', $classroomId);
            })->where('student_id', $student->id)->get();

            if ($grades->isEmpty()) {
                return;
            }

            $totalWeightedScore = 0;
            $totalCoefficients = 0;

            // Group grades by subject
            $gradesBySubject = $grades->groupBy(function ($grade) {
                return $grade->assignment->subject_id;
            });

            foreach ($gradesBySubject as $subjectId => $subjectGrades) {
                // Calculate subject average (simple average of assignments for now)
                $subjectAverage = $subjectGrades->avg('score'); // Assuming score is already normalized or consistent

                // Get coefficient for this subject in this class
                $classroomSubject = \App\Models\ClassroomSubject::where('classroom_id', $classroomId)
                    ->where('subject_id', $subjectId)
                    ->first();
                
                $coefficient = $classroomSubject ? $classroomSubject->coefficient : 1;

                $totalWeightedScore += $subjectAverage * $coefficient;
                $totalCoefficients += $coefficient;
            }

            $generalAverage = $totalCoefficients > 0 ? $totalWeightedScore / $totalCoefficients : 0;

            // Update or Create ReportCard
            ReportCard::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'school_year_id' => $schoolYearId,
                    'classroom_id' => $classroomId,
                ],
                [
                    'average' => $generalAverage,
                    'generated_at' => now(),
                ]
            );

            // Trigger Rank Calculation (could be a separate job to avoid performance issues)
            $this->calculateRanks($schoolYearId, $classroomId);
        } catch (\Exception $e) {
            Log::error('GradeObserver Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    protected function calculateRanks($schoolYearId, $classroomId)
    {
        $reportCards = ReportCard::where('school_year_id', $schoolYearId)
            ->where('classroom_id', $classroomId)
            ->orderByDesc('average')
            ->get();

        $rank = 1;
        foreach ($reportCards as $card) {
            $card->update(['rank' => $rank++]);
        }
    }
}
