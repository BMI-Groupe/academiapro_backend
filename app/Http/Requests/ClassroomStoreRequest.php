<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ClassroomStoreRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'name' => 'required|string|max:100',
			'code' => [
				'required',
				'string',
				'max:50',
				Rule::unique('classrooms')->where(function ($query) {
					return $query->where('school_year_id', request('school_year_id'));
				}),
			],
			'cycle' => 'required|in:primaire,college,lycee',
			'level' => 'required|string|max:50',
			'tuition_fee' => 'nullable|numeric|min:0',
			'school_year_id' => 'required|exists:school_years,id',
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


