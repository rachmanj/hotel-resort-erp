<?php

namespace App\Http\Requests\Admin;

use App\Enums\AgentType;
use App\Enums\CommissionBasis;
use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
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
        /** @var Agent $agent */
        $agent = $this->route('agent');

        return [
            'agent_type' => ['required', Rule::enum(AgentType::class)],
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('agents', 'code')
                    ->where(fn ($q) => $q->where('hotel_id', $agent->hotel_id))
                    ->ignore($agent->id),
            ],
            'channel_code' => ['nullable', 'string', 'max:30'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_basis' => ['required', Rule::enum(CommissionBasis::class)],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
