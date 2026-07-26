<?php

namespace App\Http\Requests\Admin;

use App\Enums\RatePlanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rates.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'name' => ['required', 'string', 'max:100'],
            'rate_type' => ['required', Rule::enum(RatePlanType::class)],
            'nightly_rate' => ['required', 'numeric', 'min:0'],
            'day_of_week_mask' => ['nullable', 'integer', 'min:0', 'max:127'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
