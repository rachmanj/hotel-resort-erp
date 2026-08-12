<?php

namespace App\Http\Requests\Admin;

use App\Enums\AgentRateDiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('agents.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'nightly_rate' => ['nullable', 'numeric', 'min:0', 'required_without_all:discount_type,discount_value,rate_plan_id'],
            'discount_type' => ['nullable', Rule::enum(AgentRateDiscountType::class), 'required_with:discount_value'],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'required_with:discount_type'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
