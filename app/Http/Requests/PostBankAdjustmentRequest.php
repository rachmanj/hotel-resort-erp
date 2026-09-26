<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostBankAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bankrec.adjust') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statement_line_id' => ['required', 'integer', 'exists:bank_reconciliation_lines,id'],
            'counter_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'description' => ['required', 'string', 'max:500'],
        ];
    }
}
