<?php

namespace App\Observers;

use App\Models\Grade;
use App\Jobs\CalculateReportCardJob;
use Illuminate\Support\Facades\Log;

class GradeObserver
{
    /**
     * Handle the Grade "created" event.
     */
    public function created(Grade $grade): void
    {
        $this->dispatchReportCardCalculation($grade);
    }

    /**
     * Handle the Grade "updated" event.
     */
    public function updated(Grade $grade): void
    {
        $this->dispatchReportCardCalculation($grade);
    }

    /**
     * Handle the Grade "deleted" event.
     */
    public function deleted(Grade $grade): void
    {
        $this->dispatchReportCardCalculation($grade);
    }

    /**
     * Dispatch report card calculation jobs for the student.
     * Calculates both quarterly and annual report cards.
     */
    protected function dispatchReportCardCalculation(Grade $grade): void
    {
        try {
            $assignment = $grade->assignment;
            
            if (!$assignment) {
                Log::warning("Grade {$grade->id} has no assignment, skipping report card calculation");
                return;
            }

            $student = $grade->student;
            if (!$student) {
                Log::warning("Grade {$grade->id} has no student, skipping report card calculation");
                return;
            }

            $schoolYearId = $assignment->school_year_id;
            $classroomId = $assignment->classroom_id;
            $period = $assignment->period;

            // Calculate quarterly report card if assignment has a term
            if ($period !== null) {
                CalculateReportCardJob::dispatch(
                    $student->id,
                    $schoolYearId,
                    $classroomId,
                    $period
                )->onQueue('reports');
            }

            // Always calculate annual report card (term = null)
            CalculateReportCardJob::dispatch(
                $student->id,
                $schoolYearId,
                $classroomId,
                null // Annual
            )->onQueue('reports');

            Log::info("Report card calculation dispatched", [
                'student_id' => $student->id,
                'school_year_id' => $schoolYearId,
                'classroom_id' => $classroomId,
                'period' => $period
            ]);

        } catch (\Exception $e) {
            Log::error('GradeObserver Error: ' . $e->getMessage(), [
                'grade_id' => $grade->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

