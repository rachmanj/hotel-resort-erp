<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankReconciliationBalancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bankrec.reconcile') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statement_opening_balance' => ['nullable', 'numeric'],
            'statement_closing_balance' => ['nullable', 'numeric'],
            'book_opening_balance' => ['nullable', 'numeric'],
            'book_closing_balance' => ['nullable', 'numeric'],
            'statement_balance' => ['nullable', 'numeric'],
            'book_balance' => ['nullable', 'numeric'],
        ];
    }
}
