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
        // Seul le directeur peut modifier des devoirs
        return $this->user() && $this->user()->role === 'directeur';
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
            'message' => 'Seul le directeur peut modifier des devoirs.',
            'data' => null
        ], 403));
    }
}
