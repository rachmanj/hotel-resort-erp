<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rooms.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:room_types,code'],
            'bed_type' => ['nullable', 'string', 'in:king,twin'],
            'view' => ['nullable', 'string', 'in:gardenview,seaview'],
            'max_occupancy' => ['required', 'integer', 'min:1', 'max:20'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
