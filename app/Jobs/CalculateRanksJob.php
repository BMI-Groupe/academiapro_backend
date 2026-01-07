<?php

namespace App\Jobs;

use App\Models\ReportCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateRanksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $schoolYearId,
        public int $sectionId,
        public ?int $period = null // null = annual, 1/2/3 = trimester
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get all report cards for this section, school year, and term
            // Order by average descending
            $reportCards = ReportCard::where('school_year_id', $this->schoolYearId)
                ->where('section_id', $this->sectionId)
                ->where('period', $this->period)
                ->orderByDesc('average')
                ->get();

            if ($reportCards->isEmpty()) {
                Log::info("No report cards found for ranking", [
                    'school_year_id' => $this->schoolYearId,
                    'section_id' => $this->sectionId,
                    'period' => $this->period
                ]);
                return;
            }

            // Assign ranks
            $rank = 1;
            $previousAverage = null;
            $sameRankCount = 0;

            foreach ($reportCards as $index => $card) {
                // Handle ties: students with same average get same rank
                if ($previousAverage !== null && abs($card->average - $previousAverage) < 0.01) {
                    // Same average, same rank
                    $sameRankCount++;
                } else {
                    // Different average, new rank
                    $rank = $index + 1;
                    $sameRankCount = 0;
                }

                $card->update(['rank' => $rank]);
                $previousAverage = $card->average;
            }

            Log::info("Ranks calculated successfully", [
                'school_year_id' => $this->schoolYearId,
                'section_id' => $this->sectionId,
                'period' => $this->period,
                'total_students' => $reportCards->count()
            ]);

        } catch (\Exception $e) {
            Log::error('CalculateRanksJob Error: ' . $e->getMessage(), [
                'school_year_id' => $this->schoolYearId,
                'section_id' => $this->sectionId,
                'period' => $this->period,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CalculateRanksJob failed permanently', [
            'school_year_id' => $this->schoolYearId,
            'section_id' => $this->sectionId,
            'period' => $this->period,
            'error' => $exception->getMessage()
        ]);
    }
}


