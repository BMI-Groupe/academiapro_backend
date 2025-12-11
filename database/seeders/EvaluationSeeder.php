<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Assignment;
use App\Models\Grade;
use App\Models\SchoolYear;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;
use App\Models\EvaluationType;

class EvaluationSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📊 Démarrage du seeding des Évaluations et Notes...');

        // Récupérer une année scolaire active ou la première trouvée
        $schoolYear = SchoolYear::where('is_active', true)->first() ?? SchoolYear::latest()->first();

        if (!$schoolYear) {
            $this->command->error('Aucune année scolaire trouvée.');
            return;
        }

        $this->command->info("Année scolaire cible : {$schoolYear->label}");

        // Récupérer les classes de cette année (ou toutes si pas de lien strict année/classe dans le modèle direct)
        $classrooms = Classroom::where('school_id', '!=', null)->get();

        foreach ($classrooms as $classroom) {
            $this->command->info("Traitement de la classe : {$classroom->label} ({$classroom->code})");

            // Récupérer les élèves inscrits dans cette classe pour cette année
            // Si pas d'inscriptions, on en crée à la volée depuis le pool d'élèves orphelins
            $enrolledStudentIds = Enrollment::where('classroom_id', $classroom->id)
                ->where('school_year_id', $schoolYear->id)
                ->pluck('student_id');

            if ($enrolledStudentIds->count() < 5) {
                // Inscrire des élèves au hasard s'il n'y en a pas assez
                $this->command->warn("  -> Pas assez d'élèves, inscription automatique...");
                $studentsToEnroll = Student::whereDoesntHave('enrollments', function($q) use ($schoolYear) {
                    $q->where('school_year_id', $schoolYear->id);
                })->where('school_id', $classroom->school_id)->take(15)->get();

                foreach ($studentsToEnroll as $student) {
                    // Vérifier à nouveau si l'élève a été inscrit entre temps (dans une itération précédente)
                    $alreadyEnrolled = Enrollment::where('student_id', $student->id)
                        ->where('school_year_id', $schoolYear->id)
                        ->exists();
                    
                    if ($alreadyEnrolled) continue;

                    try {
                        Enrollment::firstOrCreate([
                            'student_id' => $student->id,
                            'classroom_id' => $classroom->id,
                            'school_year_id' => $schoolYear->id
                        ], [
                            'enrolled_at' => now(),
                        ]);
                    } catch (\Exception $e) {
                        $this->command->warn("Erreur inscription élève {$student->id}: " . $e->getMessage());
                    }
                }
                // Rafraichir la liste
                $enrolledStudentIds = Enrollment::where('classroom_id', $classroom->id)
                    ->where('school_year_id', $schoolYear->id)
                    ->pluck('student_id');
            }
            
            $this->command->info("  -> " . $enrolledStudentIds->count() . " élèves inscrits.");

            // Récupérer les matières de la classe
            // Si pas de matières liées directement, on prend toutes les matières de l'école
            $subjects = $classroom->subjects;
            if ($subjects->isEmpty()) {
                $subjects = Subject::where('school_id', $classroom->school_id)->get();
            }

            foreach ($subjects as $subject) {
                // Créer des devoirs pour chaque période (Trimestre 1, 2, 3)
                for ($period = 1; $period <= 3; $period++) {
                    // Vérifier s'il y a déjà des devoirs pour cette matière/période
                    $existingAssignmentsCount = Assignment::where('classroom_id', $classroom->id)
                        ->where('subject_id', $subject->id)
                        ->where('period', $period)
                        ->count();

                    if ($existingAssignmentsCount < 3) {
                        // Créer 2 devoirs et 1 examen par période
                        $types = ['devoir_surveille' => 2, 'examen_final' => 1];
                        
                        foreach ($types as $typeKey => $count) {
                            for ($i = 0; $i < $count; $i++) {
                                try {
                                    $assignment = Assignment::create([
                                        'title' => ucfirst(str_replace('_', ' ', $typeKey)) . " " . ($i + 1) . " - P$period",
                                        'description' => "Évaluation de la période $period",
                                        'type' => $typeKey, // devoir_surveille, examen_final...
                                        'max_score' => 20,
                                        'start_date' => now()->subDays(rand(1, 90)),
                                        'due_date' => now()->subDays(rand(1, 90)),
                                        'classroom_id' => $classroom->id,
                                        'subject_id' => $subject->id,
                                        'school_year_id' => $schoolYear->id,
                                        'school_id' => $classroom->school_id,
                                        'period' => $period, // IMPORTANT: Période 1, 2 ou 3
                                        'created_by' => null, // Avoid FK error
                                    ]);
                                } catch (\Exception $e) {
                                    $this->command->error("ERREUR ASSIGNMENT ({$subject->name}): " . $e->getMessage());
                                    continue;
                                }

                                // Créer les notes pour chaque élève
                                foreach ($enrolledStudentIds as $studentId) {
                                    // Générer une note réaliste (autour de 12/20 +/- variance)
                                    $score = min(20, max(0, 12 + rand(-5, 5) + (rand(0, 10) / 10)));
                                    
                                try {
                                    Grade::create([
                                        'student_id' => $studentId,
                                        'assignment_id' => $assignment->id,
                                        'score' => $score, // Correct column name
                                        'notes' => $score > 15 ? 'Très bien' : ($score < 8 ? 'À revoir' : 'Correct'), // Correct column name
                                        'graded_at' => now(),
                                        'graded_by' => null, // Avoid FK error
                                        'school_id' => $classroom->school_id,
                                    ]);
                                } catch (\Exception $e) {
                                    $this->command->error('ERREUR GRADE: ' . $e->getMessage());
                                }
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->command->info('✅ Seeding des évaluations et notes terminé !');
    }
}
