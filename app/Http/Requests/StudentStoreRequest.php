<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StudentStoreRequest extends FormRequest
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
			'matricule' => 'required|string|max:50|unique:students,matricule',
			'birth_date' => 'nullable|date',
			'gender' => 'nullable|in:M,F',
			'parent_contact' => 'nullable|string|max:100',
			'address' => 'nullable|string|max:255',
			'classroom_id' => 'nullable|exists:classrooms,id',
			'school_year_id' => 'nullable|exists:school_years,id',
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


