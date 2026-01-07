<?php

namespace App\Services;

use App\Models\SectionSubject;
use App\Models\SchoolYear;

class ClassroomSubjectService
{
    /**
     * Assigner une matière à une section pour une année donnée
     */
    public function assignSubject(
        int $sectionId,
        int $subjectId,
        int $schoolYearId,
        int $coefficient
    ): SectionSubject {
        return SectionSubject::updateOrCreate(
            [
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'school_year_id' => $schoolYearId,
            ],
            ['coefficient' => $coefficient]
        );
    }

    /**
     * Obtenir le programme d'une section pour une année
     */
    public function getClassroomProgram(int $sectionId, int $schoolYearId)
    {
        return SectionSubject::with(['subject', 'schoolYear'])
            ->where('section_id', $sectionId)
            ->where('school_year_id', $schoolYearId)
            ->get();
    }

    /**
     * Copier le programme d'une année à une autre pour une section
     */
    public function copyProgramToNewYear(
        int $sectionId,
        int $fromYearId,
        int $toYearId
    ): int {
        // Trouver la section de destination (même template, année différente)
        $sourceSection = \App\Models\Section::findOrFail($sectionId);
        $targetSection = \App\Models\Section::where('classroom_template_id', $sourceSection->classroom_template_id)
            ->where('school_year_id', $toYearId)
            ->first();

        if (!$targetSection) {
            throw new \Exception("Section de destination non trouvée pour l'année {$toYearId}");
        }

        $subjects = SectionSubject::where('section_id', $sectionId)
            ->where('school_year_id', $fromYearId)
            ->get();

        $count = 0;
        foreach ($subjects as $subject) {
            SectionSubject::firstOrCreate(
                [
                    'section_id' => $targetSection->id,
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
     * Retirer une matière d'une section pour une année
     */
    public function removeSubject(
        int $sectionId,
        int $subjectId,
        int $schoolYearId
    ): bool {
        return SectionSubject::where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('school_year_id', $schoolYearId)
            ->delete() > 0;
    }

    /**
     * Mettre à jour le coefficient d'une matière
     */
    public function updateCoefficient(
        int $sectionId,
        int $subjectId,
        int $schoolYearId,
        int $coefficient
    ): ?SectionSubject {
        $cs = SectionSubject::where('section_id', $sectionId)
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
