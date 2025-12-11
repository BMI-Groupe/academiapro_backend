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
			'assignment_id' => $this->assignment_id,
			'score' => $this->score,
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
			'assignment' => $this->whenLoaded('assignment', function () {
				return [
					'id' => $this->assignment->id,
					'title' => $this->assignment->title,
					'type' => $this->assignment->type,
					'max_score' => $this->assignment->max_score,
					'passing_score' => $this->assignment->passing_score,
					'total_score' => $this->assignment->total_score,
					'classroom_id' => $this->assignment->classroom_id,
					'subject_id' => $this->assignment->subject_id,
					'school_year_id' => $this->assignment->school_year_id,
					'subject' => $this->when($this->assignment->relationLoaded('subject') && $this->assignment->subject, function () {
						return [
							'id' => $this->assignment->subject->id,
							'name' => $this->assignment->subject->name,
							'code' => $this->assignment->subject->code,
						];
					}),
					'classroom' => $this->when($this->assignment->relationLoaded('classroom') && $this->assignment->classroom, function () {
						return [
							'id' => $this->assignment->classroom->id,
							'name' => $this->assignment->classroom->name,
							'code' => $this->assignment->classroom->code,
						];
					}),
					'schoolYear' => $this->when($this->assignment->relationLoaded('schoolYear') && $this->assignment->schoolYear, function () {
						return [
							'id' => $this->assignment->schoolYear->id,
							'label' => $this->assignment->schoolYear->label,
						];
					}),
				];
			}),
			'grader' => $this->whenLoaded('grader', function () {
				return [
					'id' => $this->grader->id,
					'first_name' => $this->grader->first_name,
					'last_name' => $this->grader->last_name,
				];
			}),
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
