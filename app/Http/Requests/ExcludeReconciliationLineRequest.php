<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExcludeReconciliationLineRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
