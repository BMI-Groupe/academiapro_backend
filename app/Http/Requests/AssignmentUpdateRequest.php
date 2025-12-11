<?php

namespace App\Http\Requests;

use App\Rules\RequiresActiveSchoolYear;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignmentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin et directeur peuvent modifier des devoirs
        return $this->user() && in_array($this->user()->role, ['admin', 'directeur']);
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'in:Devoir,Examen,Composition,Interrogation'],
            'max_score' => ['nullable', 'numeric', 'min:0'],
            'passing_score' => ['nullable', 'numeric', 'min:0'],
            'total_score' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'period' => ['nullable', 'integer', 'in:1,2,3'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Échec de validation.',
            'data' => $validator->errors()
        ], 422));
    }

    public function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Vous n\'êtes pas autorisé à modifier des devoirs.',
            'data' => null
        ], 403));
    }
}
