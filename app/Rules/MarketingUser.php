<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MarketingUser implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $user = User::query()->find($value);

        if ($user === null || ! $user->hasRole('marketing')) {
            $fail('The selected marketing person is invalid.');
        }
    }
}
