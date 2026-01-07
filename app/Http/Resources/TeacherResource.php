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
			'classrooms' => $this->whenLoaded('sectionSubjectTeachers', function () {
				return $this->sectionSubjectTeachers->map(function ($sst) {
					return $sst->sectionSubject && $sst->sectionSubject->section ? [
						'id' => $sst->sectionSubject->section->id,
						'name' => $sst->sectionSubject->section->display_name ?? $sst->sectionSubject->section->name ?? ($sst->sectionSubject->section->classroomTemplate ? $sst->sectionSubject->section->classroomTemplate->name : null),
						'code' => $sst->sectionSubject->section->code,
					] : null;
				})->filter()->unique('id')->values();
			}),
			'sections' => $this->whenLoaded('sectionSubjectTeachers', function () {
				return $this->sectionSubjectTeachers->map(function ($sst) {
					return $sst->sectionSubject && $sst->sectionSubject->section ? [
						'id' => $sst->sectionSubject->section->id,
						'name' => $sst->sectionSubject->section->display_name ?? $sst->sectionSubject->section->name,
						'code' => $sst->sectionSubject->section->code,
						'classroom_template' => $sst->sectionSubject->section->classroomTemplate ? [
							'id' => $sst->sectionSubject->section->classroomTemplate->id,
							'name' => $sst->sectionSubject->section->classroomTemplate->name,
							'code' => $sst->sectionSubject->section->classroomTemplate->code,
						] : null,
					] : null;
				})->filter()->unique('id')->values();
			}),
			'subjects' => $this->whenLoaded('sectionSubjectTeachers', function () {
				return $this->sectionSubjectTeachers->map(function ($sst) {
					return $sst->sectionSubject && $sst->sectionSubject->subject ? [
						'id' => $sst->sectionSubject->subject->id,
						'name' => $sst->sectionSubject->subject->name,
						'code' => $sst->sectionSubject->subject->code,
						'coefficient' => $sst->sectionSubject->coefficient,
					] : null;
				})->filter()->unique('id')->values();
			}),
            'section_subject_teachers' => $this->whenLoaded('sectionSubjectTeachers'),
            'classroom_subject_teachers' => $this->whenLoaded('sectionSubjectTeachers'), // Alias pour compatibilité
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
