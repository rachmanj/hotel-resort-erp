<?php

namespace App\Http\Requests;

use App\Models\RoomType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomTypeRequest extends FormRequest
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
        /** @var RoomType $roomType */
        $roomType = $this->route('room_type') ?? $this->route('roomType');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('room_types', 'code')->ignore($roomType->id)],
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
