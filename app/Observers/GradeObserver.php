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
     * Dispatch report card calculation for the student.
     * Creates/updates a report card for the assignment with all grades for that student.
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
            $sectionId = $assignment->section_id;
            $period = $assignment->period;

            // Récupérer toutes les notes de cet élève pour ce devoir
            $allGrades = \App\Models\Grade::where('student_id', $student->id)
                ->where('assignment_id', $assignment->id)
                ->get();

            if ($allGrades->isEmpty()) {
                Log::warning("No grades found for student {$student->id} and assignment {$assignment->id}");
                return;
            }

            // Calculer la moyenne de toutes les notes pour ce devoir
            $average = round($allGrades->avg('score'), 2);

            // Créer ou mettre à jour le bulletin pour ce devoir
            \App\Models\ReportCard::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'school_year_id' => $schoolYearId,
                    'section_id' => $sectionId,
                    'assignment_id' => $assignment->id,
                ],
                [
                    'average' => $average,
                    'period' => $period,
                    'generated_at' => now(),
                ]
            );

            Log::info("Report card created/updated for assignment", [
                'student_id' => $student->id,
                'school_year_id' => $schoolYearId,
                'section_id' => $sectionId,
                'assignment_id' => $assignment->id,
                'average' => $average,
                'grades_count' => $allGrades->count()
            ]);

        } catch (\Exception $e) {
            Log::error('GradeObserver Error: ' . $e->getMessage(), [
                'grade_id' => $grade->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

