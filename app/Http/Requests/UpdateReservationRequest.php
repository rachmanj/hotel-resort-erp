<?php

namespace App\Http\Requests;

use App\Enums\DirectChannel;
use App\Enums\ReservationSource;
use App\Http\Requests\Concerns\ValidatesReservationMarketingFields;
use App\Models\Reservation;
use App\Rules\MarketingUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReservationRequest extends FormRequest
{
    use ValidatesReservationMarketingFields;

    public function authorize(): bool
    {
        return $this->user()?->can('reservations.edit') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCorporateAgentFromCompany();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reservation = $this->route('reservation');
        $legacySource = $reservation instanceof Reservation && $reservation->source->isLegacy()
            ? $reservation->source->value
            : null;

        $allowedSources = ReservationSource::categorisedValues();
        if ($legacySource !== null) {
            $allowedSources[] = $legacySource;
        }

        return [
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'guest' => ['nullable', 'array'],
            'guest.full_name' => ['nullable', 'string', 'max:150'],
            'guest.id_number' => ['nullable', 'string', 'max:50'],
            'guest.phone' => ['nullable', 'string', 'max:30'],
            'guest.email' => ['nullable', 'email', 'max:150'],
            'guest.address' => ['nullable', 'string'],
            'guest.nationality' => ['nullable', 'string', 'max:60'],
            'arrival_date' => ['required', 'date'],
            'departure_date' => ['required', 'date', 'after:arrival_date'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'adults' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'special_requests' => ['nullable', 'string'],
            'source' => ['sometimes', Rule::in($allowedSources)],
            'direct_channel' => [
                'nullable',
                Rule::enum(DirectChannel::class),
                Rule::requiredIf(fn () => $this->input('source') === ReservationSource::Direct->value),
                Rule::prohibitedIf(fn () => $this->input('source') !== ReservationSource::Direct->value
                    && $this->filled('direct_channel')),
            ],
            'marketing_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id', new MarketingUser],
            'is_marketing_non_agent' => ['sometimes', 'boolean'],
            'company_id' => [
                'nullable',
                'integer',
                'exists:companies,id',
                Rule::requiredIf(fn () => $this->input('source') === ReservationSource::Corporate->value),
            ],
            'agent_id' => [
                'nullable',
                'integer',
                'exists:agents,id',
                Rule::requiredIf(fn () => in_array($this->input('source'), [
                    ReservationSource::Ota->value,
                    ReservationSource::TravelAgent->value,
                    ReservationSource::Agent->value,
                ], true)),
            ],
            'ota_fee_id' => [
                'nullable',
                'integer',
                'exists:ota_fees,id',
                Rule::requiredIf(fn () => $this->input('source') === ReservationSource::Agent->value),
            ],
        ];
    }
}
