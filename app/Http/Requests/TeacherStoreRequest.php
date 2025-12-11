<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class TeacherStoreRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'first_name' => 'required|string|max:100',
			'last_name' => 'required|string|max:100',
			'phone' => 'nullable|string|max:20',
			'email' => 'nullable|email|max:255|unique:teachers,email',
			'specialization' => 'nullable|string|max:100',
			'birth_date' => 'nullable|date',
            'school_id' => 'nullable|exists:schools,id',
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
