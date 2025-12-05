<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users with role 'directeur' can create accounts
        return auth()->check() && auth()->user()->role === 'directeur';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // user account fields
            'name' => 'required|string|max:64|min:4|unique:users',
            'email' => 'nullable|email|max:150|min:4|unique:users',
            'password' => 'required|string|min:8',
            'passwordConfirm' => 'required|same:password',
            'role' => 'required|string|in:enseignant,directeur',

            // teacher-specific fields
            'first_name' => 'required|string|max:64',
            'last_name' => 'required|string|max:64',
            'phone' => 'required|string|max:20',
            'profession' => 'required|string|max:128',
            'birth_date' => 'required|date',
        ];
    }


    public function messages()
    {
        return [
            'name.required' => 'Le nom d\'utilisateur est requis',
            'name.min' => 'Le nom d\'utilisateur doit contenir au moins 3 characters',
            'name.max' => 'Le nom d\'utilisateur doit contenir au plus 255 characters',
            'name.unique' => 'Cet nom d\'utilisateur existe déjà !',
            'email.unique' => 'Cet email exist déjà',
            'password.required' => 'Le mot de passe est requis',
            'passwordConfirm.same' => 'les deux mots de passe sont différent',
            'role.required' => 'Le rôle est requis',

            'first_name.required' => 'Le prénom est requis',
            'last_name.required' => 'Le nom est requis',
            'phone.required' => 'Le numéro de téléphone est requis',
            'profession.required' => 'La profession est requise',
            'birth_date.required' => 'La date de naissance est requise',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'   => false,
            'message'   => 'Echec de validation.',
            'data'      => $validator->errors()
        ]));
    }
}
