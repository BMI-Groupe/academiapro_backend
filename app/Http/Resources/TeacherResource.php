<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
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
			'user_id' => $this->user_id,
			'first_name' => $this->first_name,
			'last_name' => $this->last_name,
			'phone' => $this->phone,
			'email' => $this->email,
			'specialization' => $this->specialization,
			'birth_date' => $this->birth_date,
			'classrooms' => $this->whenLoaded('classroomSubjectTeachers', function () {
				return $this->classroomSubjectTeachers->map(function ($cst) {
					return $cst->classroomSubject && $cst->classroomSubject->classroom ? [
						'id' => $cst->classroomSubject->classroom->id,
						'name' => $cst->classroomSubject->classroom->name,
						'code' => $cst->classroomSubject->classroom->code,
					] : null;
				})->filter()->unique('id')->values();
			}),
			'subjects' => $this->whenLoaded('classroomSubjectTeachers', function () {
				return $this->classroomSubjectTeachers->map(function ($cst) {
					return $cst->classroomSubject && $cst->classroomSubject->subject ? [
						'id' => $cst->classroomSubject->subject->id,
						'name' => $cst->classroomSubject->subject->name,
						'code' => $cst->classroomSubject->subject->code,
						'coefficient' => $cst->classroomSubject->coefficient,
					] : null;
				})->filter()->unique('id')->values();
			}),
            'classroom_subject_teachers' => $this->whenLoaded('classroomSubjectTeachers'),
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
