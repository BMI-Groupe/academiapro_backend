<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StudentUpdateRequest extends FormRequest
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
			'matricule' => [
				'sometimes',
				'required',
				'string',
				'max:50',
				Rule::unique('students', 'matricule')->ignore($this->route('student')?->id),
			],
			'birth_date' => 'nullable|date',
			'gender' => 'nullable|in:M,F',
			'parent_contact' => 'nullable|string|max:100',
			'address' => 'nullable|string|max:255',
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


