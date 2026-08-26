<?php

namespace App\Http\Requests\Admin;

use App\Enums\OtaFeeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOtaFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ota-fees.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hotelId = session('current_hotel_id');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:ota_fees,code,NULL,id,hotel_id,'.$hotelId,
            ],
            'name' => ['required', 'string', 'max:150'],
            'fee_type' => ['required', Rule::enum(OtaFeeType::class)],
            'base_fee_pct' => ['nullable', 'numeric', 'min:0', 'required_if:fee_type,percent'],
            'variable_fee_pct' => ['nullable', 'numeric', 'min:0'],
            'flat_fee_per_room_night' => ['nullable', 'numeric', 'min:0', 'required_if:fee_type,flat'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
