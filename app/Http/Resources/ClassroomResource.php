<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
{
	public function toArray(Request $request): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'code' => $this->code,
			'cycle' => $this->cycle,
			'level' => $this->level,
            'tuition_fee' => $this->tuition_fee,
            'school_year_id' => $this->school_year_id,
			'subjects' => $this->whenLoaded('subjects', function () {
				return $this->subjects->map(function ($subject) {
					return [
						'id' => $subject->id,
						'name' => $subject->name,
						'code' => $subject->code,
						'coefficient' => $subject->coefficient,
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
						'school_year' => $enrollment->school_year,
						'enrolled_at' => $enrollment->enrolled_at,
					];
				});
			}),
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}


