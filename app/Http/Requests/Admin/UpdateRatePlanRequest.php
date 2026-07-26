<?php

namespace App\Http\Requests\Admin;

use App\Enums\RatePlanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRatePlanRequest extends FormRequest
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
            'room_type_id' => ['sometimes', 'integer', 'exists:room_types,id'],
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'name' => ['sometimes', 'string', 'max:100'],
            'rate_type' => ['sometimes', Rule::enum(RatePlanType::class)],
            'nightly_rate' => ['sometimes', 'numeric', 'min:0'],
            'day_of_week_mask' => ['nullable', 'integer', 'min:0', 'max:127'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
