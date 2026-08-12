<?php

namespace App\Http\Requests;

use App\Enums\GroupInvoiceMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateGroupInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.invoice') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['sometimes', Rule::enum(GroupInvoiceMode::class)],
            'folio_ids' => ['required_if:mode,split', 'array'],
            'folio_ids.*' => ['integer', 'exists:folios,id'],
        ];
    }
}
