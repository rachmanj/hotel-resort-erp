<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportBankStatementLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bankrec.import') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.statement_date' => ['required', 'date'],
            'lines.*.statement_amount' => ['required', 'numeric'],
            'lines.*.statement_line_ref' => ['nullable', 'string', 'max:100'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
