<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpaAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spa.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'spa_treatment_id' => ['required', 'exists:spa_treatments,id'],
            'spa_therapist_id' => ['required', 'exists:spa_therapists,id'],
            'scheduled_at' => ['required', 'date'],
            'guest_id' => ['nullable', 'exists:guests,id'],
            'reservation_id' => ['nullable', 'exists:reservations,id'],
            'charged_to_room' => ['boolean'],
        ];
    }
}
