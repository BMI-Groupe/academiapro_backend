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
            'section_id' => [
                'nullable', 
                'exists:sections,id', 
                function ($attribute, $value, $fail) {
                    // Si section_id est fourni, vérifier qu'il y a une année scolaire active
                    if ($value) {
                        $activeYear = \App\Models\SchoolYear::where('is_active', true)->first();
                        if (!$activeYear) {
                            $fail('Une année scolaire active doit être définie avant de créer cette ressource.');
                        }
                    }
                }
            ],
            'classroom_id' => ['nullable'], // Alias pour compatibilité frontend, ignoré si section_id est présent
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'apply_to_all_sections' => ['nullable', 'boolean'], // Si true, créer pour toutes les sections
            'apply_to_all_subjects' => ['nullable', 'boolean'], // Si true, s'applique à toutes les matières
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
