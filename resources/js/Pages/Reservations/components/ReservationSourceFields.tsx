import { Checkbox, Form, Select } from 'antd';
import { useMemo } from 'react';

export interface AgentOption {
    value: number;
    label: string;
    code: string;
    agent_type: string;
    company_id?: number | null;
}

export interface ReservationSourceFormData {
    source: string;
    agent_id: number | null;
    company_id: number | null;
    direct_channel: string | null;
    marketing_user_id: number | null;
    is_marketing_non_agent: boolean;
}

interface ReservationSourceFieldsProps {
    data: ReservationSourceFormData;
    sources: Array<{ value: string; label: string }>;
    directChannels: Array<{ value: string; label: string }>;
    marketingUsers: Array<{ value: number; label: string }>;
    companies: Array<{ id: number; name: string }>;
    agents: AgentOption[];
    legacySource?: boolean;
    legacySourceLabel?: string;
    onChange: (patch: Partial<ReservationSourceFormData>) => void;
}

export default function ReservationSourceFields({
    data,
    sources,
    directChannels,
    marketingUsers,
    companies,
    agents,
    legacySource = false,
    legacySourceLabel,
    onChange,
}: ReservationSourceFieldsProps) {
    const sourceOptions = useMemo(() => {
        if (!legacySource || !legacySourceLabel) {
            return sources.map((s) => ({ value: s.value, label: s.label }));
        }

        const hasCurrent = sources.some((s) => s.value === data.source);

        if (hasCurrent) {
            return sources.map((s) => ({ value: s.value, label: s.label }));
        }

        return [
            { value: data.source, label: legacySourceLabel },
            ...sources.map((s) => ({ value: s.value, label: s.label })),
        ];
    }, [sources, legacySource, legacySourceLabel, data.source]);

    const otaAgents = useMemo(
        () => agents.filter((a) => a.agent_type === 'ota'),
        [agents],
    );
    const travelAgents = useMemo(
        () => agents.filter((a) => a.agent_type === 'travel'),
        [agents],
    );

    const isLegacyAgentSource = data.source === 'agent';

    const showMarketingNonAgent =
        data.source === 'direct' || data.source === 'walkin';

    const onSourceChange = (source: string) => {
        onChange({
            source,
            agent_id: null,
            company_id: null,
            direct_channel: null,
            is_marketing_non_agent: false,
        });
    };

    return (
        <>
            <Form.Item label="Source" required>
                <Select
                    value={data.source}
                    onChange={onSourceChange}
                    options={sourceOptions}
                />
            </Form.Item>

            {data.source === 'ota' && (
                <Form.Item label="OTA" required>
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="Select OTA"
                        value={data.agent_id}
                        onChange={(v) => onChange({ agent_id: v ?? null })}
                        options={otaAgents}
                    />
                </Form.Item>
            )}

            {(data.source === 'travel_agent' || isLegacyAgentSource) && (
                <Form.Item label="Travel Agent" required>
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="Select travel agent"
                        value={data.agent_id}
                        onChange={(v) => onChange({ agent_id: v ?? null })}
                        options={travelAgents}
                    />
                </Form.Item>
            )}

            {data.source === 'corporate' && (
                <Form.Item label="Company" required>
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="Select company"
                        value={data.company_id}
                        onChange={(v) => onChange({ company_id: v ?? null, agent_id: null })}
                        options={companies.map((c) => ({ value: c.id, label: c.name }))}
                    />
                </Form.Item>
            )}

            {data.source === 'direct' && (
                <Form.Item label="Direct channel" required>
                    <Checkbox.Group
                        value={data.direct_channel ? [data.direct_channel] : []}
                        onChange={(checked) => {
                            const last = checked[checked.length - 1] ?? null;
                            onChange({ direct_channel: last });
                        }}
                        options={directChannels.map((c) => ({
                            label: c.label,
                            value: c.value,
                        }))}
                    />
                </Form.Item>
            )}

            <Form.Item label="Marketing" required>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="Select marketing person"
                    value={data.marketing_user_id}
                    onChange={(v) => onChange({ marketing_user_id: v ?? null })}
                    options={marketingUsers}
                />
            </Form.Item>

            {showMarketingNonAgent && (
                <Form.Item>
                    <Checkbox
                        checked={data.is_marketing_non_agent}
                        onChange={(e) =>
                            onChange({ is_marketing_non_agent: e.target.checked })
                        }
                    >
                        Marketing (non agent)
                    </Checkbox>
                </Form.Item>
            )}
        </>
    );
}
