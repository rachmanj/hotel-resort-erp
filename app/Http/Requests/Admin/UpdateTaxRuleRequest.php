<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'applies_to' => ['required', 'string', 'in:all,room,fb,spa'],
            'is_compounding' => ['boolean'],
            'is_active' => ['boolean'],
            'order' => ['required', 'integer', 'min:0'],
        ];
    }
}
