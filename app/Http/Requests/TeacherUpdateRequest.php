<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class TeacherUpdateRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'first_name' => 'sometimes|required|string|max:100',
			'last_name' => 'sometimes|required|string|max:100',
			'phone' => 'nullable|string|max:20',
			'email' => 'nullable|email|max:255|unique:teachers,email,' . $this->route('teacher'),
			'specialization' => 'nullable|string|max:100',
			'birth_date' => 'nullable|date',
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
