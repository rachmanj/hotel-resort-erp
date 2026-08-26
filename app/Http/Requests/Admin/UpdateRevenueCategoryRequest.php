<?php

namespace App\Http\Requests\Admin;

use App\Models\RevenueCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRevenueCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('revenue-categories.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var RevenueCategory $revenueCategory */
        $revenueCategory = $this->route('revenue_category') ?? $this->route('revenueCategory');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:revenue_categories,code,'.$revenueCategory->id.',id,hotel_id,'.$revenueCategory->hotel_id,
            ],
            'name' => ['required', 'string', 'max:150'],
            'coa_account_code' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
