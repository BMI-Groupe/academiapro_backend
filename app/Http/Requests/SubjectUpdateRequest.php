<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SubjectUpdateRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'name' => [
				'sometimes',
				'required',
				'string',
				'max:100',
				Rule::unique('subjects', 'name')->ignore($this->route('subject')?->id),
			],
			'code' => [
				'sometimes',
				'required',
				'string',
				'max:50',
				Rule::unique('subjects', 'code')->ignore($this->route('subject')?->id),
			],
			'coefficient' => 'required|numeric|min:0|max:10',
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
