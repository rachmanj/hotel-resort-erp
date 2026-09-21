<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostFolioChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.post') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'revenue_category_id' => ['nullable', 'integer', 'exists:revenue_categories,id'],
            'dive_package_id' => ['nullable', 'integer', 'exists:dive_packages,id'],
        ];
    }
}
