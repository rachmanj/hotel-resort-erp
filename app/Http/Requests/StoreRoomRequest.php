<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
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
            'number' => ['required', 'string', 'max:10', 'unique:rooms,number'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'floor_id' => ['nullable', 'integer', 'exists:floors,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
