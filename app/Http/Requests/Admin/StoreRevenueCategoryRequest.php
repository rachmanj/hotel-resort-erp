<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRevenueCategoryRequest extends FormRequest
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
        $hotelId = session('current_hotel_id');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:revenue_categories,code,NULL,id,hotel_id,'.$hotelId,
            ],
            'name' => ['required', 'string', 'max:150'],
            'coa_account_code' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
