<?php

namespace App\Services;

use App\Models\ClassroomSubject;
use App\Models\SchoolYear;

class ClassroomSubjectService
{
    /**
     * Assigner une matière à une classe pour une année donnée
     */
    public function assignSubject(
        int $classroomId,
        int $subjectId,
        int $schoolYearId,
        int $coefficient
    ): ClassroomSubject {
        return ClassroomSubject::updateOrCreate(
            [
                'classroom_id' => $classroomId,
                'subject_id' => $subjectId,
                'school_year_id' => $schoolYearId,
            ],
            ['coefficient' => $coefficient]
        );
    }

    /**
     * Obtenir le programme d'une classe pour une année
     */
    public function getClassroomProgram(int $classroomId, int $schoolYearId)
    {
        return ClassroomSubject::with(['subject', 'schoolYear'])
            ->where('classroom_id', $classroomId)
            ->where('school_year_id', $schoolYearId)
            ->get();
    }

    /**
     * Copier le programme d'une année à une autre
     */
    public function copyProgramToNewYear(
        int $classroomId,
        int $fromYearId,
        int $toYearId
    ): int {
        $subjects = ClassroomSubject::where('classroom_id', $classroomId)
            ->where('school_year_id', $fromYearId)
            ->get();

        $count = 0;
        foreach ($subjects as $subject) {
            ClassroomSubject::firstOrCreate(
                [
                    'classroom_id' => $classroomId,
                    'subject_id' => $subject->subject_id,
                    'school_year_id' => $toYearId,
                ],
                ['coefficient' => $subject->coefficient]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Retirer une matière d'une classe pour une année
     */
    public function removeSubject(
        int $classroomId,
        int $subjectId,
        int $schoolYearId
    ): bool {
        return ClassroomSubject::where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('school_year_id', $schoolYearId)
            ->delete() > 0;
    }

    /**
     * Mettre à jour le coefficient d'une matière
     */
    public function updateCoefficient(
        int $classroomId,
        int $subjectId,
        int $schoolYearId,
        int $coefficient
    ): ?ClassroomSubject {
        $cs = ClassroomSubject::where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('school_year_id', $schoolYearId)
            ->first();

        if ($cs) {
            $cs->update(['coefficient' => $coefficient]);
            return $cs->fresh();
        }

        return null;
    }
}
