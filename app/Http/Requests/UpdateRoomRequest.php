<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
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
        /** @var Room $room */
        $room = $this->route('room');

        return [
            'number' => ['required', 'string', 'max:10', Rule::unique('rooms', 'number')->ignore($room->id)],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'floor_id' => ['nullable', 'integer', 'exists:floors,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
