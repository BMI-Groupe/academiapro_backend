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
				Rule::unique('classrooms', 'code')->ignore($this->route('classroom')?->id),
			],
			'cycle' => 'sometimes|required|in:primaire,college,lycee',
			'level' => 'sometimes|required|string|max:50',
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


