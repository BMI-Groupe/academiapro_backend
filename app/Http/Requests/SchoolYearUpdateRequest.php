<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SchoolYearUpdateRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		$schoolYearId = $this->route('school_year')->id ?? null;

		return [
			'year_start' => 'nullable|numeric|digits:4|min:2000',
			'year_end' => 'nullable|numeric|digits:4|gt:year_start',
			'label' => 'nullable|string|max:100|unique:school_years,label,' . $schoolYearId,
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
