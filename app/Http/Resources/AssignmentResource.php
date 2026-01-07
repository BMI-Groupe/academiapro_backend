<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'max_score' => $this->max_score,
            'passing_score' => $this->passing_score,
            'total_score' => $this->total_score,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'classroom_id' => $this->section_id, // Alias pour compatibilité frontend
            'section_id' => $this->section_id,
            'classroom' => $this->whenLoaded('section', fn() => [
                'id' => $this->section->id,
                'name' => $this->section->display_name ?? $this->section->name ?? $this->section->classroomTemplate->name,
                'code' => $this->section->code,
            ]),
            'section' => $this->whenLoaded('section', function () {
                $section = $this->section;
                return [
                    'id' => $section->id,
                    'name' => $section->display_name ?? $section->name,
                    'code' => $section->code,
                    'classroom_template' => $section->relationLoaded('classroomTemplate') && $section->classroomTemplate ? [
                        'id' => $section->classroomTemplate->id,
                        'name' => $section->classroomTemplate->name,
                        'code' => $section->classroomTemplate->code,
                        'cycle' => $section->classroomTemplate->cycle,
                        'level' => $section->classroomTemplate->level,
                    ] : null,
                ];
            }),
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn() => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ]),
            'school_year_id' => $this->school_year_id,
            'school_year' => $this->whenLoaded('schoolYear', fn() => [
                'id' => $this->schoolYear->id,
                'label' => $this->schoolYear->label,
            ]),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
