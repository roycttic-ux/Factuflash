<?php

namespace App\Rules;

use App\Services\CuitValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCuit implements ValidationRule
{
    private bool $allowEmpty;

    public function __construct(bool $allowEmpty = true)
    {
        $this->allowEmpty = $allowEmpty;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->allowEmpty && ($value === null || trim((string) $value) === '')) {
            return;
        }

        if (!CuitValidator::isValid((string) $value)) {
            $fail('El CUIT ingresado no es válido (verificá los 11 dígitos y el dígito verificador).');
        }
    }
}
