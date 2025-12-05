<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SchoolYearStoreRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'year_start' => 'required|numeric|digits:4|min:2000',
			'year_end' => 'required|numeric|digits:4|gt:year_start',
			'label' => 'required|string|max:100|unique:school_years,label',
			'is_active' => 'nullable|boolean',
			'start_date' => 'nullable|date',
			'end_date' => 'nullable|date|after_or_equal:start_date',
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
