<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
{
	public function toArray(Request $request): array
	{
		// Cette resource peut être utilisée pour Section (via binding de route)
		$isSection = isset($this->classroomTemplate);
		
		return [
			'id' => $this->id,
			'name' => $this->display_name ?? $this->name ?? ($isSection ? $this->classroomTemplate->name : null),
			'code' => $this->code,
			'cycle' => $isSection ? $this->classroomTemplate->cycle : $this->cycle,
			'level' => $isSection ? $this->classroomTemplate->level : $this->level,
            'tuition_fee' => $this->effective_tuition_fee ?? $this->tuition_fee ?? ($isSection ? $this->classroomTemplate->tuition_fee : null),
            'school_year_id' => $this->school_year_id,
			'classroom_template_id' => $isSection ? $this->classroom_template_id : null,
			'classroom_template' => $this->whenLoaded('classroomTemplate', function () {
				return [
					'id' => $this->classroomTemplate->id,
					'name' => $this->classroomTemplate->name,
					'code' => $this->classroomTemplate->code,
					'cycle' => $this->classroomTemplate->cycle,
					'level' => $this->classroomTemplate->level,
				];
			}),
			'subjects' => $this->whenLoaded('subjects', function () {
				return $this->subjects->map(function ($subject) {
					return [
						'id' => $subject->id,
						'name' => $subject->name,
						'code' => $subject->code,
						'coefficient' => $subject->pivot->coefficient ?? null,
					];
				});
			}),
			'teachers' => $this->whenLoaded('teachers', function () {
				return $this->teachers->map(function ($teacher) {
					return [
						'id' => $teacher->id,
						'first_name' => $teacher->first_name,
						'last_name' => $teacher->last_name,
						'subject_id' => $teacher->pivot->subject_id ?? null,
					];
				});
			}),
			'students' => $this->whenLoaded('enrollments', function () {
				return $this->enrollments->map(function ($enrollment) {
					return [
						'student_id' => $enrollment->student_id,
						'school_year_id' => $enrollment->school_year_id,
						'enrolled_at' => $enrollment->enrolled_at,
						'status' => $enrollment->status,
					];
				});
			}),
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}


