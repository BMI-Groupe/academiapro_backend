<?php

namespace App\Http\Requests;

use App\Rules\RequiresActiveSchoolYear;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Seuls le directeur et l'admin peuvent créer des devoirs
        return $this->user() && in_array($this->user()->role, ['admin', 'directeur']);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:Devoir,Examen,Composition,Interrogation'],
            'max_score' => ['required', 'numeric', 'min:0'],
            'passing_score' => ['required', 'numeric', 'min:0'],
            'total_score' => ['required', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'classroom_id' => ['required', 'exists:classrooms,id', new RequiresActiveSchoolYear()],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'school_id' => ['nullable', 'exists:schools,id'],
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
            'message' => 'Vous n\'êtes pas autorisé à créer des devoirs.',
            'data' => null
        ], 403));
    }
}
