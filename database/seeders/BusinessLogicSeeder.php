<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\ClassroomTemplate;
use App\Models\Section;
use App\Models\SectionSubject;
use App\Models\SectionSubjectTeacher;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class BusinessLogicSeeder extends Seeder
{
    public function run(): void
    {
        try {
            // 1. Create Active Year
            $year = SchoolYear::create([
                'year_start' => 2023,
                'year_end' => 2024,
                'label' => '2023-2024',
                'is_active' => true,
                'start_date' => '2023-09-01',
                'end_date' => '2024-06-30',
            ]);
            $this->command->info('Active Year Created: ' . $year->label);

            // 2. Create Classroom Template & Section & Subject
            $school = \App\Models\School::first();
            $template = ClassroomTemplate::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'code' => '6EME',
                ],
                [
                    'name' => '6ème',
                    'cycle' => 'college',
                    'level' => '6eme',
                    'tuition_fee' => 50000,
                    'is_active' => true,
                ]
            );

            $section = Section::create([
                'classroom_template_id' => $template->id,
                'school_year_id' => $year->id,
                'school_id' => $school->id,
                'name' => '6ème A',
                'code' => '6EME-A-2023-2024',
                'is_active' => true,
            ]);
            $this->command->info('Section Created: ' . $section->name);

            $math = Subject::create(['name' => 'Mathématiques', 'code' => 'MATH']);
            $fr = Subject::create(['name' => 'Français', 'code' => 'FR']);
            $this->command->info('Subjects Created');

            // 3. Assign Subject to Section (with coeff)
            $sectionMath = SectionSubject::create([
                'section_id' => $section->id,
                'subject_id' => $math->id,
                'school_year_id' => $year->id,
                'coefficient' => 5,
            ]);
            $sectionFr = SectionSubject::create([
                'section_id' => $section->id,
                'subject_id' => $fr->id,
                'school_year_id' => $year->id,
                'coefficient' => 4,
            ]);
            $this->command->info('Subjects Assigned to Section with Coefficients');

            // 4. Create Student & Enroll
            $studentUser = User::create([
                'name' => 'Jean Dupont',
                'email' => 'jean.dupont@example.com',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STUDENT, // Assuming constant exists or string 'student'
            ]);
            
            $student = Student::create([
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'matricule' => 'MAT123',
                'user_id' => $studentUser->id,
            ]);

            Enrollment::create([
                'student_id' => $student->id,
                'section_id' => $section->id,
                'school_year_id' => $year->id,
                'enrolled_at' => now(),
                'status' => 'active',
            ]);
            $this->command->info('Student Created and Enrolled');

            // 5. Create Teacher & Assign
            $teacherUser = User::create([
                'name' => 'Prof Math',
                'email' => 'prof.math@example.com',
                'password' => Hash::make('password'),
                'role' => User::ROLE_TEACHER,
            ]);

            $teacher = Teacher::create([
                'user_id' => $teacherUser->id,
                'first_name' => 'Prof',
                'last_name' => 'Math',
            ]);

            SectionSubjectTeacher::create([
                'section_subject_id' => $sectionMath->id,
                'teacher_id' => $teacher->id,
                'school_year_id' => $year->id,
            ]);
            $this->command->info('Teacher Created and Assigned to Math');

            // 6. Director creates Assignment
            $directorUser = User::create([
                'name' => 'Directeur',
                'email' => 'directeur@example.com',
                'password' => Hash::make('password'),
                'role' => User::ROLE_DIRECTOR,
            ]);

            $assignment = Assignment::create([
                'title' => 'Devoir Math 1',
                'type' => 'Devoir',
                'max_score' => 20,
                'passing_score' => 10,
                'total_score' => 20,
                'start_date' => now(),
                'due_date' => now()->addDays(7),
                'section_id' => $section->id,
                'school_year_id' => $year->id,
                'created_by' => $directorUser->id,
            ]);
            $this->command->info('Assignment Created');

            // 7. Teacher enters Grade
            // Verify permission (simulated)
            $isAssigned = $teacher->sectionSubjectTeachers()
                ->where('section_subject_id', $sectionMath->id)
                ->where('school_year_id', $year->id)
                ->exists();
            
            if ($isAssigned) {
                Grade::create([
                    'student_id' => $student->id,
                    'assignment_id' => $assignment->id,
                    'score' => 15, // 15/20
                    'graded_by' => $teacher->id,
                    'graded_at' => now(),
                ]);
                $this->command->info('Grade Entered: 15/20');
            } else {
                $this->command->error('Teacher not assigned!');
            }

            // 8. Verify ReportCard generation
            $reportCard = ReportCard::where('student_id', $student->id)
                ->where('school_year_id', $year->id)
                ->first();

            if ($reportCard) {
                $this->command->info('Report Card Generated. Average: ' . $reportCard->average);
                if ($reportCard->average == 15) { // Only one grade, coeff doesn't matter for single subject avg yet, but global avg logic
                     // Math Coeff 5. Score 15. Weighted = 75. Total Coeff = 5 (since only math has grades). Avg = 15.
                     $this->command->info('Verification SUCCESS');
                } else {
                     $this->command->warn('Verification Partial: Average calculation might need check.');
                }
            } else {
                $this->command->error('Report Card NOT Generated');
            }
        } catch (\Exception $e) {
            $this->command->error('Error: ' . $e->getMessage());
            $this->command->error('Trace: ' . $e->getTraceAsString());
        }
    }
}
