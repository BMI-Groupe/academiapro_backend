<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SchoolYearUpdateRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		$schoolYear = $this->route('school_year');
		$schoolYearId = $schoolYear->id ?? null;
		
		$schoolId = $schoolYear->school_id ?? $this->input('school_id');
		if (!$schoolId && auth()->check()) {
			$schoolId = auth()->user()->school_id;
		}

		return [
			'year_start' => 'nullable|numeric|digits:4|min:2000',
			'year_end' => 'nullable|numeric|digits:4|gt:year_start',
			'label' => [
				'nullable',
				'string',
				'max:100',
				Rule::unique('school_years', 'label')
					->ignore($schoolYearId)
					->where(function ($query) use ($schoolId) {
						return $query->where('school_id', $schoolId);
					}),
			],
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
