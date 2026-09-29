<?php

namespace App\Http\Requests\Concerns;

use App\Enums\AgentType;
use App\Enums\DirectChannel;
use App\Enums\ReservationSource;
use App\Models\Agent;
use App\Rules\MarketingUser;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesReservationMarketingFields
{
    /**
     * @return array<string, mixed>
     */
    protected function reservationMarketingFieldRules(bool $requireMarketingUser): array
    {
        return [
            'source' => ['sometimes', Rule::in(ReservationSource::categorisedValues())],
            'direct_channel' => [
                'nullable',
                Rule::enum(DirectChannel::class),
                Rule::requiredIf(fn () => $this->input('source') === ReservationSource::Direct->value),
                Rule::prohibitedIf(fn () => $this->input('source') !== ReservationSource::Direct->value
                    && $this->filled('direct_channel')),
            ],
            'marketing_user_id' => array_filter([
                $requireMarketingUser ? 'required' : 'sometimes',
                'integer',
                'exists:users,id',
                new MarketingUser,
            ]),
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
                ], true)),
            ],
        ];
    }

    protected function prepareCorporateAgentFromCompany(): void
    {
        if ($this->input('source') !== ReservationSource::Corporate->value) {
            return;
        }

        if ($this->filled('agent_id')) {
            return;
        }

        if (! $this->filled('company_id')) {
            return;
        }

        $agentId = Agent::query()
            ->where('company_id', $this->integer('company_id'))
            ->where('agent_type', AgentType::Corporate->value)
            ->where('is_active', true)
            ->value('id');

        if ($agentId !== null) {
            $this->merge(['agent_id' => $agentId]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $source = $this->input('source');
            $agentId = $this->input('agent_id');

            if ($source === ReservationSource::Corporate->value && ! $this->filled('agent_id')) {
                $validator->errors()->add('company_id', 'No active corporate agent is linked to this company.');

                return;
            }

            if ($agentId === null || $source === null) {
                return;
            }

            $agent = Agent::query()->find($agentId);

            if ($agent === null) {
                return;
            }

            $expectedType = match ($source) {
                ReservationSource::Ota->value => AgentType::Ota,
                ReservationSource::TravelAgent->value => AgentType::Travel,
                ReservationSource::Corporate->value => AgentType::Corporate,
                default => null,
            };

            if ($expectedType !== null && $agent->agent_type !== $expectedType) {
                $validator->errors()->add('agent_id', 'The selected agent does not match the reservation source category.');
            }
        });
    }
}
