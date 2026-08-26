<?php

namespace App\Http\Requests\Admin;

use App\Enums\BoatCharterStatus;
use App\Enums\BoatCharterType;
use App\Enums\GuideType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoatCharterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('dive-center.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'boat_unit_id' => ['required', 'integer', 'exists:boat_units,id'],
            'dive_package_id' => ['nullable', 'integer', 'exists:dive_packages,id'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'folio_id' => ['nullable', 'integer', 'exists:folios,id'],
            'trip_date' => ['required', 'date'],
            'destination' => ['required', 'string', 'max:150'],
            'charter_type' => ['required', Rule::enum(BoatCharterType::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'bbm_liters' => ['nullable', 'numeric', 'min:0'],
            'bbm_cost' => ['nullable', 'numeric', 'min:0'],
            'guide_type' => ['required', Rule::enum(GuideType::class)],
            'guide_fee' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::enum(BoatCharterStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
