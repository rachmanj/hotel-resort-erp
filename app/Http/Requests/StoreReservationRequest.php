<?php

namespace App\Http\Requests;

use App\Enums\GuestIdType;
use App\Enums\ReservationSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservations.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'guest' => ['nullable', 'array'],
            'guest.full_name' => ['required_without:guest_id', 'string', 'max:150'],
            'guest.id_number' => ['nullable', 'string', 'max:50'],
            'guest.id_type' => ['nullable', Rule::enum(GuestIdType::class)],
            'guest.phone' => ['nullable', 'string', 'max:30'],
            'guest.email' => ['nullable', 'email', 'max:150'],
            'guest.address' => ['nullable', 'string'],
            'guest.nationality' => ['nullable', 'string', 'max:60'],
            'arrival_date' => ['required', 'date', 'after_or_equal:today'],
            'departure_date' => ['required', 'date', 'after:arrival_date'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'promotion_code' => ['nullable', 'string', 'max:30'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'adults' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'special_requests' => ['nullable', 'string'],
            'source' => ['sometimes', Rule::enum(ReservationSource::class)],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id', 'required_if:source,agent'],
            'ota_fee_id' => ['nullable', 'integer', 'exists:ota_fees,id', 'required_if:source,ota'],
        ];
    }
}
