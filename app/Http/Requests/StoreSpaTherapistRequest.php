<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpaTherapistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spa.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
