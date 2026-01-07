<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ScheduleUpdateRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'section_id' => 'nullable|exists:sections,id',
			'classroom_id' => 'nullable|exists:sections,id', // Accepte classroom_id comme alias de section_id
			'subject_id' => 'nullable|exists:subjects,id',
			'teacher_id' => 'nullable|exists:teachers,id',
			'school_year_id' => 'nullable|exists:school_years,id',
			'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
			'start_time' => 'nullable|date_format:H:i',
			'end_time' => 'nullable|date_format:H:i|after:start_time',
			'room' => 'nullable|string|max:50',
		];
	}

	public function failedValidation(Validator $validator)
	{
		throw new HttpResponseException(response()->json([
			'success' => false,
			'message' => 'Echec de validation.',
			'data' => $validator->errors()
		], 422));
	}
}
