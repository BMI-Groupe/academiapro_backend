<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\Assignment;
use App\Models\ClassroomSubjectTeacher;

class TeacherPermissionService
{
    /**
     * Vérifie si un enseignant peut noter un devoir.
     * 
     * @param Teacher $teacher
     * @param Assignment $assignment
     * @return bool
     */
    public function canGradeAssignment(Teacher $teacher, Assignment $assignment): bool
    {
        // Vérifier que l'enseignant est assigné à la classe et à la matière pour l'année scolaire du devoir
        $hasAssignment = ClassroomSubjectTeacher::whereHas('classroomSubject', function ($query) use ($assignment) {
            $query->where('classroom_id', $assignment->classroom_id)
                  ->where('subject_id', $assignment->subject_id);
        })
        ->where('teacher_id', $teacher->id)
        ->where('school_year_id', $assignment->school_year_id)
        ->exists();

        return $hasAssignment;
    }

    /**
     * Vérifie si un enseignant peut enseigner une matière dans une classe pour une année donnée.
     * 
     * @param Teacher $teacher
     * @param int $classroomId
     * @param int $subjectId
     * @param int $schoolYearId
     * @return bool
     */
    public function canTeach(Teacher $teacher, int $classroomId, int $subjectId, int $schoolYearId): bool
    {
        return ClassroomSubjectTeacher::whereHas('classroomSubject', function ($query) use ($classroomId, $subjectId) {
            $query->where('classroom_id', $classroomId)
                  ->where('subject_id', $subjectId);
        })
        ->where('teacher_id', $teacher->id)
        ->where('school_year_id', $schoolYearId)
        ->exists();
    }
}
