<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PaymentStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role, ['admin', 'directeur', 'secretaire']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'type' => 'required|string',
            'school_year_id' => 'nullable|exists:school_years,id',
            'school_id' => 'nullable|exists:schools,id',
            'notes' => 'nullable|string',
        ];
    }
}
