<?php

namespace App\Rules;

use App\Models\SchoolYear;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RequiresActiveSchoolYear implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $activeYear = SchoolYear::where('is_active', true)->first();

        if (!$activeYear) {
            $fail('Une année scolaire active doit être définie avant de créer cette ressource.');
        }
    }
}
