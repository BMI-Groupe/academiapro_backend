<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
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
			'classroom_id' => $this->section_id, // Alias pour compatibilité frontend
			'section_id' => $this->section_id,
			'subject_id' => $this->subject_id,
			'teacher_id' => $this->teacher_id,
			'school_year_id' => $this->school_year_id,
			'day_of_week' => $this->day_of_week,
			'start_time' => $this->start_time,
			'end_time' => $this->end_time,
			'room' => $this->room,
			'classroom' => $this->whenLoaded('section', function () {
				$section = $this->section;
				return [
					'id' => $section->id,
					'name' => $section->display_name ?? $section->name ?? ($section->classroomTemplate ? $section->classroomTemplate->name : null),
					'code' => $section->code,
				];
			}),
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
					] : null,
				];
			}),
			'subject' => $this->whenLoaded('subject', function () {
				return [
					'id' => $this->subject->id,
					'name' => $this->subject->name,
					'code' => $this->subject->code,
				];
			}),
			'teacher' => $this->whenLoaded('teacher', function () {
				return [
					'id' => $this->teacher->id,
					'first_name' => $this->teacher->first_name,
					'last_name' => $this->teacher->last_name,
				];
			}),
			'schoolYear' => $this->whenLoaded('schoolYear', function () {
				return [
					'id' => $this->schoolYear->id,
					'name' => $this->schoolYear->name,
				];
			}),
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
