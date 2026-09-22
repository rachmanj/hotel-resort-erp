<?php

namespace App\Http\Requests;

use App\Enums\ProformaPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProformaPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('proforma.payment.record') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'method' => ['required', Rule::enum(ProformaPaymentMethod::class)],
            'received_from' => ['required', 'string', 'max:150'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
