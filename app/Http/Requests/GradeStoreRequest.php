<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GradeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'autorisation sera vérifiée dans le contrôleur via TeacherPermissionService
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'assignment_id' => 'required|exists:assignments,id',
            'score' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'graded_at' => 'nullable|date',
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
}
