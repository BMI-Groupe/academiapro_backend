<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\School;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\SchoolYear;
use App\Models\ClassroomTemplate;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SectionSubject;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\EvaluationType;
use App\Models\Assignment;
use App\Models\Grade;
use App\Jobs\CalculateReportCardJob;

class LargeDataSeeder extends Seeder
{
    public function run(): void
    {
        try {
            $this->command->info('🚀 Début du seeding complet enrichi...');

            // 1. ÉCOLES (1 école - réduit de 3 à 1)
            $schools = [];
            $schoolsData = [
                ['name' => 'Groupe Scolaire Excellence', 'address' => '123 Avenue de l\'Éducation, Dakar', 'phone' => '221 33 123 45 67', 'email' => 'contact@excellence.sn'],
            ];

            foreach ($schoolsData as $sData) {
                $schools[] = School::firstOrCreate(['name' => $sData['name']], array_merge($sData, ['is_active' => true]));
            }
            $this->command->info("✅ " . count($schools) . " écoles créées");

            // 2. UTILISATEURS
            $admin = User::firstOrCreate(['email' => 'admin@example.com'], [
                'name' => 'Administrateur', 'phone' => '600000000', 'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN,
            ]);
            User::firstOrCreate(['email' => 'directeur@example.com'], [
                'name' => 'Directeur', 'phone' => '600000001', 'password' => Hash::make('password'), 'role' => User::ROLE_DIRECTOR,
            ]);

            // 3. ENSEIGNANTS & MATIÈRES (par école)
            $this->command->info('👨‍🏫 Génération enseignants/matières...');
            $allTeachers = collect();
            $allSubjects = collect();
            
            $subjectsData = [
                ['Mathématiques','MATH'],['Français','FR'],['Anglais','ANG'],['Histoire-Géo','HG'],
                ['SVT','SVT'],['Physique-Chimie','PC'],['Philosophie','PHILO'],['EPS','EPS'],
                ['Informatique','INFO'],['Espagnol','ESP']
            ];
            
            foreach ($schools as $school) {
                // 3 enseignants par école (réduit de 10 à 3)
                $teachers = Teacher::factory()->count(3)->create(['school_id' => $school->id]);
                $allTeachers = $allTeachers->merge($teachers);
                
                // Matières pour cette école
                foreach ($subjectsData as $s) {
                    $subject = Subject::firstOrCreate(
                        ['code' => $s[1], 'school_id' => $school->id],
                        ['name' => $s[0], 'school_year_id' => null] // Will be set per year
                    );
                    $allSubjects->push($subject);
                }
            }

            // 4. POOL D'ÉLÈVES (répartis entre les écoles)
            $this->command->info('🎓 Génération pool de 75 élèves...');
            $studentPool = collect();
            $studentsPerSchool = 75; // Réduit de 300 à 75 
            
            foreach ($schools as $school) {
                $students = Student::factory()->count($studentsPerSchool)->create(['school_id' => $school->id]);
                $studentPool = $studentPool->merge($students);
            }
            $studentIndex = 0;

            // 5. ANNÉES SCOLAIRES
            $yearsConfig = [
                ['label' => '2023-2024', 'start' => 2023, 'end' => 2024, 'active' => false, 'd_start' => '2023-09-01', 'd_end' => '2024-06-30'],
                ['label' => '2024-2025', 'start' => 2024, 'end' => 2025, 'active' => true,  'd_start' => '2024-09-01', 'd_end' => '2025-06-30'],
                ['label' => '2025-2026', 'start' => 2025, 'end' => 2026, 'active' => false, 'd_start' => '2025-09-01', 'd_end' => '2026-06-30'],
            ];

            foreach ($yearsConfig as $conf) {
                $this->command->info("📅 Année {$conf['label']}...");
                // Chercher par label d'abord, puis par school_id + year_start pour éviter les doublons
                $sy = SchoolYear::where('label', $conf['label'])
                    ->orWhere(function($q) use ($conf, $schools) {
                        $q->where('school_id', $schools[0]->id)
                          ->where('year_start', $conf['start'])
                          ->where('year_end', $conf['end']);
                    })
                    ->first();
                
                if (!$sy) {
                    $sy = SchoolYear::create([
                        'year_start' => $conf['start'], 
                        'year_end' => $conf['end'], 
                        'is_active' => $conf['active'],
                        'start_date' => $conf['d_start'], 
                        'end_date' => $conf['d_end'],
                        'school_id' => $schools[0]->id,
                        'label' => $conf['label'],
                        'period_system' => 'trimester',
                        'total_periods' => 3
                    ]);
                } else {
                    // Mettre à jour si l'année existe mais avec un mauvais label ou des données manquantes
                    $updateData = [];
                    if ($sy->label !== $conf['label'] && (empty($sy->label) || $sy->label === 'Année scolaire')) {
                        $updateData['label'] = $conf['label'];
                    }
                    if (!$sy->start_date) {
                        $updateData['start_date'] = $conf['d_start'];
                    }
                    if (!$sy->end_date) {
                        $updateData['end_date'] = $conf['d_end'];
                    }
                    if (!isset($sy->period_system)) {
                        $updateData['period_system'] = 'trimester';
                        $updateData['total_periods'] = 3;
                    }
                    if (!empty($updateData)) {
                        $sy->update($updateData);
                        $this->command->info("   ✓ Année mise à jour: {$conf['label']}");
                    }
                }

                // TYPES D'ÉVALUATION pour cette année
                $evalTypes = [];
                $evalTypesData = [
                    ['name' => 'Contrôle Continu', 'weight' => 0.30],
                    ['name' => 'Devoir Surveillé', 'weight' => 0.30],
                    ['name' => 'Examen Final', 'weight' => 0.40],
                ];
                foreach ($evalTypesData as $etData) {
                    $evalTypes[] = EvaluationType::firstOrCreate(
                        ['name' => $etData['name'], 'school_year_id' => $sy->id],
                        $etData
                    );
                }

                // CLASSES (réduit de 2 sections à 1 par niveau)
                $levels = [
                    '6eme' => ['cycle' => 'college', 'fee' => 50000, 'cnt' => 1],
                    '5eme' => ['cycle' => 'college', 'fee' => 55000, 'cnt' => 1],
                    '4eme' => ['cycle' => 'college', 'fee' => 60000, 'cnt' => 1],
                    '3eme' => ['cycle' => 'college', 'fee' => 65000, 'cnt' => 1],
                    '2nde' => ['cycle' => 'lycee', 'fee' => 80000, 'cnt' => 1],
                    '1ere' => ['cycle' => 'lycee', 'fee' => 85000, 'cnt' => 1],
                    'Tle'  => ['cycle' => 'lycee', 'fee' => 90000, 'cnt' => 1],
                ];

                $letters = ['A', 'B', 'C'];
                $schoolIndex = 0; // To distribute classrooms across schools
                
                foreach ($levels as $lName => $lConf) {
                    for ($i = 0; $i < $lConf['cnt']; $i++) {
                        // Distribute classrooms across schools
                        $currentSchool = $schools[$schoolIndex % count($schools)];
                        $schoolIndex++;
                        
                        // Create or get template
                        $templateCode = strtoupper(substr($lName, 0, 3));
                        $template = ClassroomTemplate::firstOrCreate(
                            ['code' => $templateCode, 'school_id' => $currentSchool->id],
                            [
                                'name' => $lName,
                                'cycle' => $lConf['cycle'],
                                'level' => $lName,
                                'tuition_fee' => $lConf['fee'],
                                'is_active' => true,
                            ]
                        );

                        // Create section for this year
                        $sectionCode = $templateCode . '-' . $letters[$i] . '-' . $sy->label;
                        $section = Section::firstOrCreate(
                            ['code' => $sectionCode, 'school_year_id' => $sy->id, 'school_id' => $currentSchool->id],
                            [
                                'classroom_template_id' => $template->id,
                                'name' => "$lName " . $letters[$i],
                                'tuition_fee' => $lConf['fee'],
                                'is_active' => true,
                            ]
                        );

                        // Get subjects for this section's school
                        $schoolSubjects = $allSubjects->where('school_id', $currentSchool->id);
                        
                        // MATIÈRES & PROFS
                        $clsSubjects = [];
                        foreach ($schoolSubjects as $sub) {
                            // Get teachers from the same school
                            $schoolTeachers = $allTeachers->where('school_id', $currentSchool->id);
                            $t = $schoolTeachers->random();
                            
                            SectionSubject::firstOrCreate(
                                ['section_id' => $section->id, 'subject_id' => $sub->id, 'school_year_id' => $sy->id],
                                ['coefficient' => rand(1,4)]
                            );
                            $clsSubjects[] = ['s' => $sub, 't' => $t];
                        }

                        // EMPLOIS DU TEMPS
                        $days = ['monday','tuesday','wednesday','thursday','friday'];
                        foreach ($days as $day) {
                            if (rand(0,3)>0) {
                                $pair = $clsSubjects[array_rand($clsSubjects)];
                                Schedule::firstOrCreate([
                                    'section_id' => $section->id, 'school_year_id' => $sy->id, 'day_of_week' => $day, 'start_time' => '08:00:00'
                                ], [
                                    'subject_id' => $pair['s']->id, 'teacher_id' => $pair['t']->id, 'end_time' => '10:00:00', 'room' => 'S'.rand(1,20),
                                    'school_id' => $currentSchool->id
                                ]);
                            }
                            if (rand(0,3)>0) {
                                $pair = $clsSubjects[array_rand($clsSubjects)];
                                Schedule::firstOrCreate([
                                    'section_id' => $section->id, 'school_year_id' => $sy->id, 'day_of_week' => $day, 'start_time' => '10:00:00'
                                ], [
                                    'subject_id' => $pair['s']->id, 'teacher_id' => $pair['t']->id, 'end_time' => '12:00:00', 'room' => 'S'.rand(1,20),
                                    'school_id' => $currentSchool->id
                                ]);
                            }
                            if ($day !== 'wednesday' && rand(0,3)>0) {
                                $pair = $clsSubjects[array_rand($clsSubjects)];
                                Schedule::firstOrCreate([
                                    'section_id' => $section->id, 'school_year_id' => $sy->id, 'day_of_week' => $day, 'start_time' => '14:00:00'
                                ], [
                                    'subject_id' => $pair['s']->id, 'teacher_id' => $pair['t']->id, 'end_time' => '16:00:00', 'room' => 'S'.rand(1,20),
                                    'school_id' => $currentSchool->id
                                ]);
                            }
                        }

                        // DEVOIRS/EXAMENS (1-2 par matière - réduit de 3-5)
                        $assignments = [];
                        foreach ($clsSubjects as $pair) {
                            $nbAssignments = rand(1, 2);
                            for ($a = 0; $a < $nbAssignments; $a++) {
                                $evalType = $evalTypes[array_rand($evalTypes)];
                                $assignment = Assignment::create([
                                    'title' => $evalType->name . ' ' . ($a + 1) . ' - ' . $pair['s']->name,
                                    'description' => 'Évaluation de ' . $pair['s']->name,
                                    'type' => strtolower(str_replace(' ', '_', $evalType->name)),
                                    'max_score' => 20,
                                    'passing_score' => 10,
                                    'total_score' => 20,
                                    'start_date' => fake()->dateTimeBetween($sy->start_date, $sy->end_date)->format('Y-m-d'),
                                    'due_date' => fake()->dateTimeBetween($sy->start_date, $sy->end_date)->format('Y-m-d'),
                                    'section_id' => $section->id,
                                    'subject_id' => $pair['s']->id,
                                    'school_year_id' => $sy->id,
                                    'school_id' => $currentSchool->id,
                                    'period' => rand(1, 3), // Simulate trimesters
                                    'created_by' => $admin->id,
                                ]);
                                $assignments[] = ['assignment' => $assignment, 'teacher' => $pair['t']];
                            }
                        }

                        // ÉLÈVES (5 par section - réduit de 20) - from the same school
                        $schoolStudents = $studentPool->where('school_id', $currentSchool->id);
                        $batch = $schoolStudents->slice($studentIndex, 5);
                        $studentIndex += 5;
                        if ($studentIndex >= $schoolStudents->count() - 5) $studentIndex = 0;

                        foreach ($batch as $stu) {
                            // Check if student is already enrolled this year (any section)
                            $existingEnrollment = Enrollment::where('student_id', $stu->id)
                                ->where('school_year_id', $sy->id)
                                ->exists();
                            
                            if ($existingEnrollment) continue;

                            // INSCRIPTION
                            Enrollment::firstOrCreate(
                                ['student_id' => $stu->id, 'section_id' => $section->id, 'school_year_id' => $sy->id],
                                ['enrolled_at' => $sy->start_date, 'status' => 'active']
                            );
                            
                            // PAIEMENTS (réduit de 1-3 à 1-2 paiements)
                            $nbPay = rand(1,2);
                            $total = $lConf['fee'];
                            $paid = 0;
                            for($k=0; $k<$nbPay; $k++) {
                                $amt = ceil($total / 2);
                                if ($paid + $amt > $total) $amt = $total - $paid;
                                if ($amt <= 0) break;
                                
                                Payment::create([
                                    'student_id' => $stu->id, 'school_year_id' => $sy->id, 'user_id' => $admin->id,
                                    'amount' => $amt, 'payment_date' => fake()->dateTimeBetween($sy->start_date, $sy->end_date)->format('Y-m-d'),
                                    'type' => 'TUITION', 'reference' => 'PAY-' . uniqid() . rand(100,9999),
                                    'school_id' => $currentSchool->id, // Add school_id
                                ]);
                                $paid += $amt;
                            }

                            // NOTES (pour chaque devoir, 80% des élèves ont une note - augmenté pour avoir plus de données avec moins d'élèves)
                            $gradesCreated = false;
                            $periodsWithGrades = []; // Track which periods have grades
                            
                            foreach ($assignments as $aData) {
                                if (rand(0, 10) > 2) { // 80% de chance d'avoir une note
                                    Grade::create([
                                        'student_id' => $stu->id,
                                        'assignment_id' => $aData['assignment']->id,
                                        'score' => fake()->randomFloat(2, 0, 20), // Note sur 20
                                        'notes' => fake()->optional(0.3)->sentence(), // 30% ont un commentaire
                                        'graded_by' => $aData['teacher']->id,
                                        'graded_at' => now(),
                                        'school_id' => $currentSchool->id, // Add school_id
                                    ]);
                                    $gradesCreated = true;
                                    // Track which period this assignment belongs to
                                    $period = $aData['assignment']->period;
                                    if ($period && !in_array($period, $periodsWithGrades)) {
                                        $periodsWithGrades[] = $period;
                                    }
                                }
                            }
                            
                            // Générer les bulletins pour cet élève si des notes ont été créées
                            if ($gradesCreated) {
                                // Générer les bulletins trimestriels uniquement pour les périodes qui ont des notes
                                foreach ($periodsWithGrades as $period) {
                                    try {
                                        CalculateReportCardJob::dispatchSync(
                                            $stu->id,
                                            $sy->id,
                                            $section->id,
                                            $period
                                        );
                                    } catch (\Exception $e) {
                                        $this->command->warn("   Erreur génération bulletin trimestre {$period} pour élève {$stu->id}: " . $e->getMessage());
                                    }
                                }
                                
                                // Toujours générer le bulletin annuel s'il y a des notes
                                try {
                                    CalculateReportCardJob::dispatchSync(
                                        $stu->id,
                                        $sy->id,
                                        $section->id,
                                        null // Annual
                                    );
                                } catch (\Exception $e) {
                                    $this->command->warn("   Erreur génération bulletin annuel pour élève {$stu->id}: " . $e->getMessage());
                                }
                            }
                        }
                    }
                }
            }
            
            // Nettoyer les années scolaires avec des labels invalides ou sans données
            $this->command->info('🧹 Nettoyage des années scolaires invalides...');
            $invalidYears = SchoolYear::where(function($q) {
                $q->where('label', 'Année scolaire')
                  ->orWhere('label', '')
                  ->orWhereNull('label');
            })->get();
            
            foreach ($invalidYears as $invalidYear) {
                // Vérifier si l'année a des données
                $hasData = Enrollment::where('school_year_id', $invalidYear->id)->exists() ||
                          Payment::where('school_year_id', $invalidYear->id)->exists() ||
                          Assignment::where('school_year_id', $invalidYear->id)->exists();
                
                if (!$hasData) {
                    // Supprimer l'année si elle n'a pas de données
                    $this->command->warn("   Suppression de l'année invalide: {$invalidYear->label} (ID: {$invalidYear->id})");
                    $invalidYear->delete();
                } else {
                    // Corriger le label si l'année a des données
                    $correctLabel = $invalidYear->year_start . '-' . $invalidYear->year_end;
                    $invalidYear->update(['label' => $correctLabel]);
                    $this->command->info("   ✓ Label corrigé: {$correctLabel} (ID: {$invalidYear->id})");
                }
            }
            
            $this->command->info('✅ Seeding complet terminé avec succès!');
            $this->command->info('📊 Connexion: Admin 600000000 / password');
            
        } catch (\Exception $e) {
            $this->command->error("❌ ERREUR: " . $e->getMessage());
            file_put_contents(base_path('seeder_crash_log.txt'), $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}
