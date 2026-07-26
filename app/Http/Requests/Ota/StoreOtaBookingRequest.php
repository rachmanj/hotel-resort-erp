<?php

namespace App\Http\Requests\Ota;

use Illuminate\Foundation\Http\FormRequest;

class StoreOtaBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hotel_code' => ['required', 'string', 'max:10'],
            'external_booking_id' => ['required', 'string', 'max:100'],
            'channel' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:new,modified,cancelled'],
            'guest' => ['required', 'array'],
            'guest.full_name' => ['required', 'string', 'max:255'],
            'guest.phone' => ['nullable', 'string', 'max:50'],
            'guest.email' => ['nullable', 'email', 'max:255'],
            'arrival_date' => ['required', 'date', 'after_or_equal:today'],
            'departure_date' => ['required', 'date', 'after:arrival_date'],
            'room_type_code' => ['required', 'string', 'max:20'],
            'adults' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
