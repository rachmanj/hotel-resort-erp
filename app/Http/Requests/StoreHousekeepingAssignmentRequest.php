<?php

namespace App\Http\Requests;

use App\Enums\HousekeepingShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHousekeepingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('housekeeping.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'housekeeper_id' => ['required', 'exists:users,id'],
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['required', 'integer', 'exists:rooms,id'],
            'assignment_date' => ['required', 'date'],
            'shift' => ['required', Rule::enum(HousekeepingShift::class)],
        ];
    }
}
