<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
            'description' => ['sometimes', 'required', 'string'],
            'cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
