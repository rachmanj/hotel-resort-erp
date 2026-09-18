<?php

namespace App\Http\Requests\Admin;

use App\Enums\AgentRateCategory;
use Illuminate\Foundation\Http\FormRequest;

class BulkStoreAgentTierRateRequest extends FormRequest
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
        $tierRules = collect(AgentRateCategory::cases())
            ->mapWithKeys(fn (AgentRateCategory $tier) => [
                "rates.*.{$tier->value}" => ['nullable', 'numeric', 'min:0'],
            ])
            ->all();

        return [
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'rates' => ['required', 'array'],
            'rates.*.room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            ...$tierRules,
        ];
    }
}
