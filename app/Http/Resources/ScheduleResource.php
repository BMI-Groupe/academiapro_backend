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
			'classroom_id' => $this->classroom_id,
			'subject_id' => $this->subject_id,
			'teacher_id' => $this->teacher_id,
			'school_year_id' => $this->school_year_id,
			'day_of_week' => $this->day_of_week,
			'start_time' => $this->start_time,
			'end_time' => $this->end_time,
			'room' => $this->room,
			'classroom' => $this->whenLoaded('classroom', function () {
				return [
					'id' => $this->classroom->id,
					'name' => $this->classroom->name,
					'code' => $this->classroom->code,
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
