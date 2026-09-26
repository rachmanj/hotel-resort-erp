<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualMatchBankReconciliationRequest extends FormRequest
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
        if ($this->has('line_id')) {
            return [
                'line_id' => ['required', 'integer', 'exists:bank_reconciliation_lines,id'],
                'general_ledger_id' => ['required', 'integer', 'exists:general_ledger,id'],
            ];
        }

        return [
            'statement_line_ids' => ['required', 'array', 'min:1'],
            'statement_line_ids.*' => ['integer', 'exists:bank_reconciliation_lines,id'],
            'book_line_ids' => ['required', 'array', 'min:1'],
            'book_line_ids.*' => ['integer', 'exists:bank_reconciliation_book_lines,id'],
        ];
    }
}
