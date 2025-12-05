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
			'classrooms' => $this->whenLoaded('classrooms', function () {
				return $this->classrooms->map(function ($classroom) {
					return [
						'id' => $classroom->id,
						'name' => $classroom->name,
						'code' => $classroom->code,
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
