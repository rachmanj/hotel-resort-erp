<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportBankStatementFileRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:pdf,csv,xlsx', 'max:20480'],
            'profile_code' => ['nullable', 'string', 'in:bca_giro_pdf,mandiri_kopra_pdf'],
            'replace' => ['sometimes', 'boolean'],
        ];
    }
}
