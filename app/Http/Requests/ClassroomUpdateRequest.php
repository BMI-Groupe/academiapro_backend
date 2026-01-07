<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ClassroomUpdateRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'name' => 'sometimes|required|string|max:100',
			'code' => [
				'sometimes',
				'required',
				'string',
				'max:50',
				Rule::unique('sections', 'code')
					->where(function ($query) {
						$schoolYearId = request('school_year_id') ?? $this->route('section')?->school_year_id;
						$schoolId = request('school_id') ?? $this->route('section')?->school_id ?? auth()->user()?->school_id;
						return $query->where('school_year_id', $schoolYearId)
							->where('school_id', $schoolId);
					})
					->ignore($this->route('section')?->id),
			],
			'classroom_template_id' => 'sometimes|required|exists:classroom_templates,id',
            'tuition_fee' => 'sometimes|nullable|numeric|min:0',
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


