<?php

namespace App\Console\Commands;

use App\Models\Grade;
use App\Jobs\CalculateReportCardJob;
use Illuminate\Console\Command;

class RecalculateReportCardsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:recalculate 
                            {--school-year= : School year ID to recalculate}
                            {--classroom= : Classroom ID to recalculate}
                            {--period= : Period to recalculate (1, 2, 3, or leave empty for all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate all report cards for students';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting report card recalculation...');

        $schoolYearId = $this->option('school-year');
        $classroomId = $this->option('classroom');
        $period = $this->option('period');

        // Get all unique combinations of student, school year, classroom
        $query = Grade::query()
            ->with(['assignment', 'student'])
            ->select('student_id', 'assignment_id')
            ->distinct();

        if ($schoolYearId) {
            $query->whereHas('assignment', function ($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            });
        }

        if ($classroomId) {
            $query->whereHas('assignment', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId);
            });
        }

        if ($period !== null) {
            $query->whereHas('assignment', function ($q) use ($period) {
                $q->where('period', $period);
            });
        }

        $grades = $query->get();

        $combinations = collect();
        foreach ($grades as $grade) {
            $assignment = $grade->assignment;
            if (!$assignment) continue;

            $key = "{$grade->student_id}-{$assignment->school_year_id}-{$assignment->classroom_id}-{$assignment->period}";
            
            if (!$combinations->has($key)) {
                $combinations->put($key, [
                    'student_id' => $grade->student_id,
                    'school_year_id' => $assignment->school_year_id,
                    'classroom_id' => $assignment->classroom_id,
                    'period' => $assignment->period,
                ]);
            }

            // Also add annual calculation
            $annualKey = "{$grade->student_id}-{$assignment->school_year_id}-{$assignment->classroom_id}-null";
            if (!$combinations->has($annualKey)) {
                $combinations->put($annualKey, [
                    'student_id' => $grade->student_id,
                    'school_year_id' => $assignment->school_year_id,
                    'classroom_id' => $assignment->classroom_id,
                    'period' => null,
                ]);
            }
        }

        $this->info("Found {$combinations->count()} report cards to recalculate");

        $bar = $this->output->createProgressBar($combinations->count());
        $bar->start();

        foreach ($combinations as $combo) {
            CalculateReportCardJob::dispatch(
                $combo['student_id'],
                $combo['school_year_id'],
                $combo['classroom_id'],
                $combo['period']
            )->onQueue('reports');

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✅ Report card recalculation jobs dispatched!');
        $this->info('💡 Run: php artisan queue:work --queue=reports to process them');

        return Command::SUCCESS;
    }
}


