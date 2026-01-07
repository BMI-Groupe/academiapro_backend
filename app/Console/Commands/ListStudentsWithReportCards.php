<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Models\ReportCard;
use App\Models\SchoolYear;

class ListStudentsWithReportCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:list-with-report-cards {--school-year-id= : Filter by school year ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all students who have report cards';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $schoolYearId = $this->option('school-year-id');
        
        // Get students with report cards
        $query = Student::whereHas('reportCards');
        
        if ($schoolYearId) {
            $query->whereHas('reportCards', function($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId);
            });
        }
        
        $students = $query->with(['reportCards.schoolYear', 'reportCards.classroom'])
            ->get();
        
        if ($students->isEmpty()) {
            $this->warn('Aucun élève avec des bulletins trouvé.');
            return 0;
        }
        
        $this->info("📊 Élèves avec des bulletins (" . $students->count() . " trouvés):\n");
        
        $tableData = [];
        
        foreach ($students as $student) {
            $reportCards = $student->reportCards;
            
            if ($schoolYearId) {
                $reportCards = $reportCards->where('school_year_id', $schoolYearId);
            }
            
            $reportCardsInfo = $reportCards->map(function($rc) {
                $periodLabel = $rc->period === null 
                    ? 'Annuel' 
                    : ($rc->schoolYear && $rc->schoolYear->period_system === 'semester' 
                        ? "Semestre {$rc->period}" 
                        : "Trimestre {$rc->period}");
                
                return sprintf(
                    "%s (Moy: %.2f, Rang: %s)",
                    $periodLabel,
                    $rc->average ?? 0,
                    $rc->rank ?? 'N/A'
                );
            })->implode(', ');
            
            $tableData[] = [
                'ID' => $student->id,
                'Nom' => $student->first_name . ' ' . $student->last_name,
                'Matricule' => $student->matricule,
                'Bulletins' => $reportCards->count(),
                'Détails' => $reportCardsInfo ?: 'Aucun détail'
            ];
        }
        
        $this->table(
            ['ID', 'Nom', 'Matricule', 'Nb Bulletins', 'Détails'],
            $tableData
        );
        
        // Summary
        $totalReportCards = ReportCard::when($schoolYearId, function($q) use ($schoolYearId) {
            $q->where('school_year_id', $schoolYearId);
        })->count();
        
        $this->info("\n📈 Résumé:");
        $this->info("   - Nombre d'élèves avec bulletins: " . $students->count());
        $this->info("   - Nombre total de bulletins: " . $totalReportCards);
        
        // List students without report cards but with grades
        $this->info("\n🔍 Vérification des élèves avec notes mais sans bulletins...");
        
        $studentsWithGradesButNoCards = Student::whereHas('grades')
            ->whereDoesntHave('reportCards')
            ->when($schoolYearId, function($q) use ($schoolYearId) {
                $q->whereHas('grades', function($gradeQuery) use ($schoolYearId) {
                    $gradeQuery->whereHas('assignment', function($assignmentQuery) use ($schoolYearId) {
                        $assignmentQuery->where('school_year_id', $schoolYearId);
                    });
                });
            })
            ->count();
        
        if ($studentsWithGradesButNoCards > 0) {
            $this->warn("   ⚠️  {$studentsWithGradesButNoCards} élève(s) ont des notes mais pas de bulletins.");
            $this->info("   💡 Exécutez la commande pour calculer automatiquement les bulletins manquants.");
        } else {
            $this->info("   ✅ Tous les élèves avec des notes ont des bulletins.");
        }
        
        return 0;
    }
}
