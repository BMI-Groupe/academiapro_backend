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
        // Create active school year
        $schoolYear = SchoolYear::create([
            'year_start' => 2024,
            'year_end' => 2025,
            'label' => '2024-2025',
            'is_active' => true,
            'start_date' => '2024-09-01',
            'end_date' => '2025-06-30',
        ]);

        // Create a director user
        $director = User::create([
            'name' => 'Directeur Principal',
            'email' => 'directeur@example.com',
            'phone' => '600000001',
            'password' => Hash::make('password'),
            'role' => User::ROLE_DIRECTOR,
        ]);

        // Create a teacher user and teacher record
        $teacherUser = User::create([
            'name' => 'Jean Dupont',
            'email' => 'teacher1@example.com',
            'phone' => '600000002',
            'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER,
        ]);

        Teacher::create([
            'user_id' => $teacherUser->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone' => '600000002',
            'specialization' => 'Mathématiques',
            'birth_date' => '1980-05-20',
        ]);

        // Create sample classrooms
        $classroom6A = Classroom::create([
            'name' => '6ème A',
            'code' => '6A',
            'cycle' => 'college',
            'level' => '6eme',
        ]);

        $classroom5B = Classroom::create([
            'name' => '5ème B',
            'code' => '5B',
            'cycle' => 'college',
            'level' => '5eme',
        ]);

        // Create sample subjects
        $math = Subject::create([
            'name' => 'Mathématiques',
            'code' => 'MATH',
        ]);

        $french = Subject::create([
            'name' => 'Français',
            'code' => 'FR',
        ]);

        // Associate subjects with classrooms for the school year
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
        Student::create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'matricule' => 'STU0001',
            'birth_date' => '2010-03-15',
            'gender' => 'F',
            'address' => 'Rue A, Ville',
        ]);

        Student::create([
            'first_name' => 'Paul',
            'last_name' => 'Bernard',
            'matricule' => 'STU0002',
            'birth_date' => '2009-07-22',
            'gender' => 'M',
            'address' => 'Rue B, Ville',
        ]);
    }
}
