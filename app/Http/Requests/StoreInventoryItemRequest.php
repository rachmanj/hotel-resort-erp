<?php

namespace App\Http\Requests;

use App\Enums\InventoryCategory;
use App\Enums\InventoryUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::enum(InventoryCategory::class)],
            'unit' => ['required', Rule::enum(InventoryUnit::class)],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'location_type' => ['nullable', 'string', 'max:50'],
            'location_id' => ['nullable', 'integer'],
        ];
    }
}
