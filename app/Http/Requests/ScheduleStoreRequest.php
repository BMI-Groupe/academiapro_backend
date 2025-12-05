<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ScheduleStoreRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'classroom_id' => 'required|exists:classrooms,id',
			'subject_id' => 'required|exists:subjects,id',
			'teacher_id' => 'required|exists:teachers,id',
			'school_year_id' => 'required|exists:school_years,id',
			'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
			'start_time' => 'required|date_format:H:i',
			'end_time' => 'required|date_format:H:i|after:start_time',
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
