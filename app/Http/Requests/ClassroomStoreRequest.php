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
				Rule::unique('sections', 'code')->where(function ($query) {
					$schoolId = request('school_id') ?? auth()->user()?->school_id;
					$query->where('school_year_id', request('school_year_id'));
					if ($schoolId) {
						$query->where('school_id', $schoolId);
					} else {
						$query->whereNull('school_id');
					}
					return $query;
				}),
			],
			'classroom_template_id' => 'nullable|exists:classroom_templates,id', // Optionnel - le repository le créera si nécessaire
			'level' => 'required|string|max:50', // Requis pour créer le template automatiquement
			'cycle' => 'required|in:primaire,college,lycee', // Requis pour créer le template automatiquement
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


