<?php

namespace App\Http\Requests;

use App\Enums\ReservationSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddGroupReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('groups.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'arrival_date' => ['required_without:reservation_id', 'nullable', 'date'],
            'departure_date' => ['required_without:reservation_id', 'nullable', 'date', 'after:arrival_date'],
            'room_type_id' => ['required_without:reservation_id', 'nullable', 'integer', 'exists:room_types,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'adults' => ['sometimes', 'integer', 'min:1'],
            'children' => ['sometimes', 'integer', 'min:0'],
            'special_requests' => ['nullable', 'string'],
            'source' => ['sometimes', Rule::enum(ReservationSource::class)],
        ];
    }
}
