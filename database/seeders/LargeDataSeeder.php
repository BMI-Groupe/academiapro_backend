<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\School;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\SchoolYear;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\ClassroomSubject;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\EvaluationType;
use App\Models\Assignment;
use App\Models\Grade;

class LargeDataSeeder extends Seeder
{
    public function run(): void
    {
        try {
            $this->command->info('🚀 Début du seeding complet enrichi...');

            // 1. ÉCOLES (3 écoles différentes)
            $schools = [];
            $schoolsData = [
                ['name' => 'Groupe Scolaire Excellence', 'address' => '123 Avenue de l\'Éducation, Dakar', 'phone' => '221 33 123 45 67', 'email' => 'contact@excellence.sn'],
                ['name' => 'Collège Privé La Réussite', 'address' => '45 Rue des Savoirs, Thiès', 'phone' => '221 33 987 65 43', 'email' => 'info@reussite.sn'],
                ['name' => 'Lycée Moderne Avenir', 'address' => '78 Boulevard du Progrès, Saint-Louis', 'phone' => '221 33 456 78 90', 'email' => 'contact@avenir.sn'],
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
                // 10 enseignants par école
                $teachers = Teacher::factory()->count(10)->create(['school_id' => $school->id]);
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
            $this->command->info('🎓 Génération pool de 900 élèves...');
            $studentPool = collect();
            $studentsPerSchool = 300; 
            
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
                $sy = SchoolYear::firstOrCreate(['label' => $conf['label']], [
                    'year_start' => $conf['start'], 'year_end' => $conf['end'], 'is_active' => $conf['active'],
                    'start_date' => $conf['d_start'], 'end_date' => $conf['d_end'],
                    'school_id' => $schools[0]->id, // Default school for year (wait, year has school_id?)
                    'period_system' => 'trimester',
                    'total_periods' => 3
                ]);

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

                // CLASSES
                $levels = [
                    '6eme' => ['cycle' => 'college', 'fee' => 50000, 'cnt' => 2],
                    '5eme' => ['cycle' => 'college', 'fee' => 55000, 'cnt' => 2],
                    '4eme' => ['cycle' => 'college', 'fee' => 60000, 'cnt' => 2],
                    '3eme' => ['cycle' => 'college', 'fee' => 65000, 'cnt' => 2],
                    '2nde' => ['cycle' => 'lycee', 'fee' => 80000, 'cnt' => 2],
                    '1ere' => ['cycle' => 'lycee', 'fee' => 85000, 'cnt' => 2],
                    'Tle'  => ['cycle' => 'lycee', 'fee' => 90000, 'cnt' => 2],
                ];

                $letters = ['A', 'B', 'C'];
                $schoolIndex = 0; // To distribute classrooms across schools
                
                foreach ($levels as $lName => $lConf) {
                    for ($i = 0; $i < $lConf['cnt']; $i++) {
                        // Distribute classrooms across schools
                        $currentSchool = $schools[$schoolIndex % count($schools)];
                        $schoolIndex++;
                        
                        $clsCode = substr($lName,0,3) . $letters[$i] . '-' . $sy->year_start;
                        $cls = Classroom::firstOrCreate(['code' => $clsCode], [
                            'name' => "$lName " . $letters[$i], 'cycle' => $lConf['cycle'], 'level' => $lName,
                            'tuition_fee' => $lConf['fee'], 'school_year_id' => $sy->id,
                            'school_id' => $currentSchool->id
                        ]);

                        // Get subjects for this classroom's school
                        $schoolSubjects = $allSubjects->where('school_id', $cls->school_id);
                        
                        // MATIÈRES & PROFS
                        $clsSubjects = [];
                        foreach ($schoolSubjects as $sub) {
                            // Get teachers from the same school
                            $schoolTeachers = $allTeachers->where('school_id', $cls->school_id);
                            $t = $schoolTeachers->random();
                            
                            ClassroomSubject::firstOrCreate(
                                ['classroom_id' => $cls->id, 'subject_id' => $sub->id, 'school_year_id' => $sy->id],
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
                                    'classroom_id' => $cls->id, 'school_year_id' => $sy->id, 'day_of_week' => $day, 'start_time' => '08:00:00'
                                ], [
                                    'subject_id' => $pair['s']->id, 'teacher_id' => $pair['t']->id, 'end_time' => '10:00:00', 'room' => 'S'.rand(1,20),
                                    'school_id' => $cls->school_id
                                ]);
                            }
                            if (rand(0,3)>0) {
                                $pair = $clsSubjects[array_rand($clsSubjects)];
                                Schedule::firstOrCreate([
                                    'classroom_id' => $cls->id, 'school_year_id' => $sy->id, 'day_of_week' => $day, 'start_time' => '10:00:00'
                                ], [
                                    'subject_id' => $pair['s']->id, 'teacher_id' => $pair['t']->id, 'end_time' => '12:00:00', 'room' => 'S'.rand(1,20),
                                    'school_id' => $cls->school_id
                                ]);
                            }
                            if ($day !== 'wednesday' && rand(0,3)>0) {
                                $pair = $clsSubjects[array_rand($clsSubjects)];
                                Schedule::firstOrCreate([
                                    'classroom_id' => $cls->id, 'school_year_id' => $sy->id, 'day_of_week' => $day, 'start_time' => '14:00:00'
                                ], [
                                    'subject_id' => $pair['s']->id, 'teacher_id' => $pair['t']->id, 'end_time' => '16:00:00', 'room' => 'S'.rand(1,20),
                                    'school_id' => $cls->school_id
                                ]);
                            }
                        }

                        // DEVOIRS/EXAMENS (3-5 par matière)
                        $assignments = [];
                        foreach ($clsSubjects as $pair) {
                            $nbAssignments = rand(3, 5);
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
                                    'classroom_id' => $cls->id,
                                    'subject_id' => $pair['s']->id,
                                    'school_year_id' => $sy->id,
                                    'school_id' => $cls->school_id,
                                    'period' => rand(1, 3), // Simulate trimesters
                                    'created_by' => $admin->id,
                                ]);
                                $assignments[] = ['assignment' => $assignment, 'teacher' => $pair['t']];
                            }
                        }

                        // ÉLÈVES (20 par classe) - from the same school
                        $schoolStudents = $studentPool->where('school_id', $cls->school_id);
                        $batch = $schoolStudents->slice($studentIndex, 20);
                        $studentIndex += 20;
                        if ($studentIndex >= $schoolStudents->count() - 20) $studentIndex = 0;

                        foreach ($batch as $stu) {
                            // Check if student is already enrolled this year (any classroom)
                            $existingEnrollment = Enrollment::where('student_id', $stu->id)
                                ->where('school_year_id', $sy->id)
                                ->exists();
                            
                            if ($existingEnrollment) continue;

                            // INSCRIPTION
                            Enrollment::firstOrCreate(
                                ['student_id' => $stu->id, 'classroom_id' => $cls->id, 'school_year_id' => $sy->id],
                                ['status' => 'active', 'enrolled_at' => $sy->start_date, 'school_id' => $cls->school_id]
                            );
                            
                            // PAIEMENTS
                            $nbPay = rand(1,3);
                            $total = $lConf['fee'];
                            $paid = 0;
                            for($k=0; $k<$nbPay; $k++) {
                                $amt = ceil($total / 3);
                                if ($paid + $amt > $total) $amt = $total - $paid;
                                if ($amt <= 0) break;
                                
                                Payment::create([
                                    'student_id' => $stu->id, 'school_year_id' => $sy->id, 'user_id' => $admin->id,
                                    'amount' => $amt, 'payment_date' => fake()->dateTimeBetween($sy->start_date, $sy->end_date)->format('Y-m-d'),
                                    'type' => 'TUITION', 'reference' => 'PAY-' . uniqid() . rand(100,9999),
                                    'school_id' => $cls->school_id, // Add school_id
                                ]);
                                $paid += $amt;
                            }

                            // NOTES (pour chaque devoir, 70% des élèves ont une note)
                            foreach ($assignments as $aData) {
                                if (rand(0, 10) > 3) { // 70% de chance d'avoir une note
                                    Grade::create([
                                        'student_id' => $stu->id,
                                        'assignment_id' => $aData['assignment']->id,
                                        'score' => fake()->randomFloat(2, 0, 20), // Note sur 20
                                        'notes' => fake()->optional(0.3)->sentence(), // 30% ont un commentaire
                                        'graded_by' => $aData['teacher']->id,
                                        'graded_at' => now(),
                                        'school_id' => $cls->school_id, // Add school_id
                                    ]);
                                }
                            }
                        }
                    }
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
