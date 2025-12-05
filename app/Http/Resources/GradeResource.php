<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
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
			'student_id' => $this->student_id,
			'subject_id' => $this->subject_id,
			'classroom_id' => $this->classroom_id,
			'teacher_id' => $this->teacher_id,
			'school_year_id' => $this->school_year_id,
			'score' => $this->score,
			'assignment_type' => $this->assignment_type,
			'notes' => $this->notes,
			'graded_at' => $this->graded_at?->format('Y-m-d H:i'),
			'student' => $this->whenLoaded('student', function () {
				return [
					'id' => $this->student->id,
					'first_name' => $this->student->first_name,
					'last_name' => $this->student->last_name,
					'matricule' => $this->student->matricule,
				];
			}),
			'subject' => $this->whenLoaded('subject', function () {
				return [
					'id' => $this->subject->id,
					'name' => $this->subject->name,
					'code' => $this->subject->code,
					'coefficient' => $this->subject->coefficient,
				];
			}),
			'classroom' => $this->whenLoaded('classroom', function () {
				return [
					'id' => $this->classroom->id,
					'name' => $this->classroom->name,
					'code' => $this->classroom->code,
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
					'start_date' => $this->schoolYear->start_date,
					'end_date' => $this->schoolYear->end_date,
				];
			}),
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
