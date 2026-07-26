<?php

namespace App\Http\Requests;

use App\Enums\InventoryCategory;
use App\Enums\InventoryUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'category' => ['sometimes', 'required', Rule::enum(InventoryCategory::class)],
            'unit' => ['sometimes', 'required', Rule::enum(InventoryUnit::class)],
            'reorder_level' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'location_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'location_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
