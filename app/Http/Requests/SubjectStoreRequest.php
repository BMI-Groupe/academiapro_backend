<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SubjectStoreRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'name' => [
				'required',
				'string',
				'max:100',
				// Allow same name for different school years
				function ($attribute, $value, $fail) {
					$query = \App\Models\Subject::where('name', $value);
					
					// If school_year_id is provided, check uniqueness within that year
					if ($this->input('school_year_id')) {
						$query->where('school_year_id', $this->input('school_year_id'));
					} else {
						// If no school_year_id, check for subjects without school_year_id
						$query->whereNull('school_year_id');
					}
					
					if ($query->exists()) {
						$fail('Ce nom de matière existe déjà pour cette année scolaire.');
					}
				},
			],
			'code' => [
				'required',
				'string',
				'max:50',
				// Allow same code for different school years
				function ($attribute, $value, $fail) {
					$query = \App\Models\Subject::where('code', $value);
					
					// If school_year_id is provided, check uniqueness within that year
					if ($this->input('school_year_id')) {
						$query->where('school_year_id', $this->input('school_year_id'));
					} else {
						// If no school_year_id, check for subjects without school_year_id
						$query->whereNull('school_year_id');
					}
					
					if ($query->exists()) {
						$fail('Ce code de matière existe déjà pour cette année scolaire.');
					}
				},
			],
			'coefficient' => 'required|numeric|min:0|max:10',
            'school_id' => 'nullable|exists:schools,id',
            'school_year_id' => 'nullable|exists:school_years,id',
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
