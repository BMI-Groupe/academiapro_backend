<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
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
			'name' => $this->name,
			'code' => $this->code,
			'coefficient' => $this->coefficient,
			'school_year_id' => $this->school_year_id,
			'school_year' => $this->whenLoaded('schoolYear', function () {
				return $this->schoolYear ? [
					'id' => $this->schoolYear->id,
					'label' => $this->schoolYear->label,
					'year_start' => $this->schoolYear->year_start,
					'year_end' => $this->schoolYear->year_end,
				] : null;
			}),
			'classrooms' => $this->whenLoaded('sections', function () {
				return $this->sections->map(function ($section) {
					return [
						'id' => $section->id,
						'name' => $section->display_name ?? $section->name ?? ($section->classroomTemplate ? $section->classroomTemplate->name : null),
						'code' => $section->code,
					];
				});
			}),
			'sections' => $this->whenLoaded('sections', function () {
				return $this->sections->map(function ($section) {
					return [
						'id' => $section->id,
						'name' => $section->display_name ?? $section->name,
						'code' => $section->code,
						'classroom_template' => $section->classroomTemplate ? [
							'id' => $section->classroomTemplate->id,
							'name' => $section->classroomTemplate->name,
							'code' => $section->classroomTemplate->code,
						] : null,
					];
				});
			}),
			'teachers' => $this->whenLoaded('teachers', function () {
				return $this->teachers->map(function ($teacher) {
					return [
						'id' => $teacher->id,
						'first_name' => $teacher->first_name,
						'last_name' => $teacher->last_name,
						'specialization' => $teacher->specialization,
					];
				});
			}),
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
