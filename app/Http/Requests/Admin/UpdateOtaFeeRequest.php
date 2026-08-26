<?php

namespace App\Http\Requests\Admin;

use App\Enums\OtaFeeType;
use App\Models\OtaFee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOtaFeeRequest extends FormRequest
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
        /** @var OtaFee $otaFee */
        $otaFee = $this->route('ota_fee') ?? $this->route('otaFee');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:ota_fees,code,'.$otaFee->id.',id,hotel_id,'.$otaFee->hotel_id,
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
