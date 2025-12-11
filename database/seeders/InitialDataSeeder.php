<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\SchoolYear;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\ClassroomSubject;
use App\Models\Enrollment;

class InitialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This is the SINGLE source of truth for initial users.
     * 
     * Login credentials:
     * - Director: phone=600000001, password=password
     * - Teacher: phone=600000002, password=password
     */
    public function run(): void
    {
        // Create a default school
        $school = \App\Models\School::create([
            'name' => 'École Principale',
            'address' => '123 Rue de l\'École',
            'phone' => '600000000',
            'email' => 'contact@ecole-principale.com',
            'is_active' => true,
        ]);

        // Create active school year for this school
        $schoolYear = SchoolYear::create([
            'school_id' => $school->id,
            'year_start' => 2024,
            'year_end' => 2025,
            'label' => '2024-2025',
            'is_active' => true,
            'start_date' => '2024-09-01',
            'end_date' => '2025-06-30',
        ]);

        // Create a director user for this school
        $director = User::create([
            'name' => 'Directeur Principal',
            'email' => 'directeur@example.com',
            'phone' => '600000001',
            'password' => Hash::make('password'),
            'role' => User::ROLE_DIRECTOR,
            'school_id' => $school->id,
        ]);

        // Create an global admin user (no school_id needed, or can be null)
        $admin = User::create([
            'name' => 'Administrateur',
            'email' => 'admin@example.com',
            'phone' => '600000000',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        // Create a teacher user and teacher record
        $teacherUser = User::create([
            'name' => 'Jean Dupont',
            'email' => 'teacher1@example.com',
            'phone' => '600000002',
            'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER,
            'school_id' => $school->id,
        ]);

        Teacher::create([
            'school_id' => $school->id,
            'user_id' => $teacherUser->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone' => '600000002',
            'specialization' => 'Mathématiques',
            'birth_date' => '1980-05-20',
        ]);

        // Create sample classrooms
        $classroom6A = Classroom::create([
            'school_id' => $school->id,
            'name' => '6ème A',
            'code' => '6A',
            'cycle' => 'college',
            'level' => '6eme',
            'school_year_id' => $schoolYear->id,
            'tuition_fee' => 50000,
        ]);

        $classroom5B = Classroom::create([
            'school_id' => $school->id,
            'name' => '5ème B',
            'code' => '5B',
            'cycle' => 'college',
            'level' => '5eme',
            'school_year_id' => $schoolYear->id,
            'tuition_fee' => 55000,
        ]);

        // Create sample subjects
        $math = Subject::create([
            'school_id' => $school->id,
            'name' => 'Mathématiques',
            'code' => 'MATH',
        ]);

        $french = Subject::create([
            'school_id' => $school->id,
            'name' => 'Français',
            'code' => 'FR',
        ]);

        // Associate subjects with classrooms for the school year
        // Note: ClassroomSubject is a pivot, no direct school_id, it relies on classroom/subject school_id consistency
        ClassroomSubject::create([
            'classroom_id' => $classroom6A->id,
            'subject_id' => $math->id,
            'school_year_id' => $schoolYear->id,
            'coefficient' => 5,
        ]);

        ClassroomSubject::create([
            'classroom_id' => $classroom6A->id,
            'subject_id' => $french->id,
            'school_year_id' => $schoolYear->id,
            'coefficient' => 4,
        ]);

        ClassroomSubject::create([
            'classroom_id' => $classroom5B->id,
            'subject_id' => $math->id,
            'school_year_id' => $schoolYear->id,
            'coefficient' => 5,
        ]);

        ClassroomSubject::create([
            'classroom_id' => $classroom5B->id,
            'subject_id' => $french->id,
            'school_year_id' => $schoolYear->id,
            'coefficient' => 4,
        ]);

        // Create sample students
        $alice = Student::create([
            'school_id' => $school->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'matricule' => 'STU0001',
            'birth_date' => '2010-03-15',
            'gender' => 'F',
            'address' => 'Rue A, Ville',
        ]);

        $paul = Student::create([
            'school_id' => $school->id,
            'first_name' => 'Paul',
            'last_name' => 'Bernard',
            'matricule' => 'STU0002',
            'birth_date' => '2009-07-22',
            'gender' => 'M',
            'address' => 'Rue B, Ville',
        ]);

        // Enroll students in classrooms
        Enrollment::create([
            'student_id' => $alice->id,
            'classroom_id' => $classroom6A->id,
            'school_year_id' => $schoolYear->id,
            'enrolled_at' => '2024-09-05',
        ]);

        Enrollment::create([
            'student_id' => $paul->id,
            'classroom_id' => $classroom5B->id,
            'school_year_id' => $schoolYear->id,
            'enrolled_at' => '2024-09-06',
        ]);
    }
}
