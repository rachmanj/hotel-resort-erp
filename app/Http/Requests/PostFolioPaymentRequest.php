<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostFolioPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.payment') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ];
    }
}
