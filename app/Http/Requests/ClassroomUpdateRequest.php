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
				Rule::unique('classrooms', 'code')
					->where(function ($query) {
						// Assuming the school_year_id is not changing during update, or is passed in request
						// If it's not passed, we should check against the existing one or the new one if provided.
						// Safest is to use the one in request if present, or from the model if not.
                        // But typically for update we might not change school_year.
                        // Let's assume request param if present.
                        $schoolYearId = request('school_year_id') ?? $this->route('classroom')->school_year_id;
						return $query->where('school_year_id', $schoolYearId);
					})
					->ignore($this->route('classroom')?->id),
			],
			'cycle' => 'sometimes|required|in:primaire,college,lycee',
			'level' => 'sometimes|required|string|max:50',
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


