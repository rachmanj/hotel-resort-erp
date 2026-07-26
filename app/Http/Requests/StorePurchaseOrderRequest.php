<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchasing.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purchase_requisition_id' => ['required', 'integer', 'exists:purchase_requisitions,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'expected_at' => ['nullable', 'date'],
            'items' => ['nullable', 'array'],
            'items.*.inventory_item_id' => ['required_with:items', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity_ordered' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
