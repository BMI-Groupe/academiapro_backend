<?php

namespace App\Jobs;

use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateReportCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $studentId,
        public int $schoolYearId,
        public int $sectionId,
        public ?int $period = null // null = annual, 1/2 = semester, 1/2/3 = trimester
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            DB::beginTransaction();

            $student = Student::find($this->studentId);
            if (!$student) {
                Log::warning("Student {$this->studentId} not found for report card calculation");
                return;
            }

            // Get all grades for this student in this school year and section
            $gradesQuery = Grade::whereHas('assignment', function ($query) {
                $query->where('school_year_id', $this->schoolYearId)
                      ->where('section_id', $this->sectionId);
                
                // Filter by term if specified
                if ($this->period !== null) {
                    $query->where('period', $this->period);
                }
            })->where('student_id', $this->studentId);

            $grades = $gradesQuery->with(['assignment.subject'])->get();

            Log::info("CalculateReportCardJob: Checking grades", [
                'student_id' => $this->studentId,
                'school_year_id' => $this->schoolYearId,
                'section_id' => $this->sectionId,
                'period' => $this->period,
                'grades_count' => $grades->count()
            ]);

            if ($grades->isEmpty()) {
                Log::warning("No grades found for student {$this->studentId}, term {$this->period}", [
                    'student_id' => $this->studentId,
                    'school_year_id' => $this->schoolYearId,
                    'section_id' => $this->sectionId,
                    'period' => $this->period
                ]);
                DB::commit();
                return;
            }

            // Calculate average
            $average = $this->calculateWeightedAverage($grades);
            
            Log::info("CalculateReportCardJob: Average calculated", [
                'student_id' => $this->studentId,
                'period' => $this->period,
                'average' => $average
            ]);

            // Update or Create ReportCard
            $reportCard = ReportCard::updateOrCreate(
                [
                    'student_id' => $this->studentId,
                    'school_year_id' => $this->schoolYearId,
                    'section_id' => $this->sectionId,
                    'period' => $this->period,
                ],
                [
                    'average' => $average,
                    'generated_at' => now(),
                ]
            );

            DB::commit();
            
            Log::info("CalculateReportCardJob: Report card created/updated", [
                'report_card_id' => $reportCard->id,
                'student_id' => $this->studentId,
                'period' => $this->period,
                'average' => $average
            ]);

            // Trigger rank calculation for this term
            CalculateRanksJob::dispatch($this->schoolYearId, $this->sectionId, $this->period);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CalculateReportCardJob Error: ' . $e->getMessage(), [
                'student_id' => $this->studentId,
                'school_year_id' => $this->schoolYearId,
                'section_id' => $this->sectionId,
                'period' => $this->period,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Calculate weighted average based on subject coefficients.
     */
    protected function calculateWeightedAverage($grades): float
    {
        $totalWeightedScore = 0;
        $totalCoefficients = 0;

        // Group grades by subject
        $gradesBySubject = $grades->groupBy(function ($grade) {
            return $grade->assignment->subject_id;
        });

        foreach ($gradesBySubject as $subjectId => $subjectGrades) {
            // Calculate subject average (simple average of all assignments for this subject)
            $subjectAverage = $subjectGrades->avg('score');

            // Get coefficient for this subject in this section
            $sectionSubject = \App\Models\SectionSubject::where('section_id', $this->sectionId)
                ->where('subject_id', $subjectId)
                ->where('school_year_id', $this->schoolYearId)
                ->first();
            
            $coefficient = $sectionSubject ? $sectionSubject->coefficient : 1;

            $totalWeightedScore += $subjectAverage * $coefficient;
            $totalCoefficients += $coefficient;
        }

        return $totalCoefficients > 0 ? round($totalWeightedScore / $totalCoefficients, 2) : 0;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CalculateReportCardJob failed permanently', [
            'student_id' => $this->studentId,
            'school_year_id' => $this->schoolYearId,
            'section_id' => $this->sectionId,
            'period' => $this->period,
            'error' => $exception->getMessage()
        ]);
    }
}


