<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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
			'first_name' => $this->first_name,
			'last_name' => $this->last_name,
			'matricule' => $this->matricule,
			'birth_date' => $this->birth_date,
			'gender' => $this->gender,
			'parent_contact' => $this->parent_contact,
			'address' => $this->address,
			'enrollments' => $this->whenLoaded('enrollments', function () {
				return $this->enrollments->map(function ($enrollment) {
					$section = $enrollment->section;
					return [
						'id' => $enrollment->id,
						'student_id' => $enrollment->student_id,
						'classroom_id' => $enrollment->section_id, // Alias pour compatibilité frontend
						'section_id' => $enrollment->section_id,
						'school_year_id' => $enrollment->school_year_id,
						'enrolled_at' => $enrollment->enrolled_at,
						'status' => $enrollment->status,
						'classroom' => $section ? [
							'id' => $section->id,
							'name' => $section->display_name ?? $section->name ?? ($section->classroomTemplate ? $section->classroomTemplate->name : 'Classe inconnue'),
							'code' => $section->code,
							'level' => $section->classroomTemplate ? $section->classroomTemplate->level : null,
							'cycle' => $section->classroomTemplate ? $section->classroomTemplate->cycle : null,
						] : null,
						'section' => $section ? [
							'id' => $section->id,
							'name' => $section->display_name ?? $section->name,
							'code' => $section->code,
							'classroom_template' => $section->classroomTemplate ? [
								'id' => $section->classroomTemplate->id,
								'name' => $section->classroomTemplate->name,
								'code' => $section->classroomTemplate->code,
								'level' => $section->classroomTemplate->level,
								'cycle' => $section->classroomTemplate->cycle,
							] : null,
						] : null,
						'school_year' => $enrollment->schoolYear ? [
							'id' => $enrollment->schoolYear->id,
							'label' => $enrollment->schoolYear->label,
							'start_date' => $enrollment->schoolYear->start_date,
							'end_date' => $enrollment->schoolYear->end_date,
							'is_active' => $enrollment->schoolYear->is_active,
						] : null,
					];
				});
			}),
			'subjects' => $this->whenLoaded('enrollments', function () {
				// Get subjects from the current enrollment (active year) or all enrollments?
				// For now, let's just flatten all subjects from all enrollments to be safe,
				// or ideally filter by active year if we had it handy.
				// Let's assume the repository loads all, and we map them.
				return $this->enrollments->flatMap(function ($enrollment) {
					return $enrollment->section ? $enrollment->section->subjects->map(function ($subject) use ($enrollment) {
						return [
							'id' => $subject->id,
							'name' => $subject->name,
							'code' => $subject->code,
							'classroom_id' => $enrollment->section_id, // Alias pour compatibilité
							'section_id' => $enrollment->section_id,
							'school_year_id' => $enrollment->school_year_id,
						];
					}) : [];
				});
			}),
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}


