<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\ClassroomSubjectTeacher;
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

            // 2. Create Class & Subject
            $classroom = Classroom::create([
                'name' => '6ème A',
                'code' => '6A',
                'cycle' => 'college',
                'level' => '6eme',
            ]);
            $this->command->info('Classroom Created: ' . $classroom->name);

            $math = Subject::create(['name' => 'Mathématiques', 'code' => 'MATH']);
            $fr = Subject::create(['name' => 'Français', 'code' => 'FR']);
            $this->command->info('Subjects Created');

            // 3. Assign Subject to Class (with coeff)
            $clsMath = ClassroomSubject::create([
                'classroom_id' => $classroom->id,
                'subject_id' => $math->id,
                'coefficient' => 5,
            ]);
            $clsFr = ClassroomSubject::create([
                'classroom_id' => $classroom->id,
                'subject_id' => $fr->id,
                'coefficient' => 4,
            ]);
            $this->command->info('Subjects Assigned to Class with Coefficients');

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
                'classroom_id' => $classroom->id,
                'school_year_id' => $year->id,
                'enrolled_at' => now(),
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

            ClassroomSubjectTeacher::create([
                'classroom_subject_id' => $clsMath->id,
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
                'classroom_id' => $classroom->id,
                'school_year_id' => $year->id,
                'created_by' => $directorUser->id,
            ]);
            $this->command->info('Assignment Created');

            // 7. Teacher enters Grade
            // Verify permission (simulated)
            $isAssigned = $teacher->classroomSubjectTeachers()
                ->where('classroom_subject_id', $clsMath->id)
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
