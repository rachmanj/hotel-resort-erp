import { Select } from 'antd';

interface RatePlanOption {
    id: number;
    name: string;
    room_type_id: number;
    nightly_rate: string;
    rate_type: string;
    season?: { id: number; name: string } | null;
}

interface RateSelectorProps {
    ratePlans: RatePlanOption[];
    roomTypeId?: number | null;
    value?: number | null;
    onChange?: (ratePlanId: number | null, nightlyRate: number) => void;
    baseRate?: number;
}

export default function RateSelector({
    ratePlans,
    roomTypeId,
    value,
    onChange,
    baseRate = 0,
}: RateSelectorProps) {
    const filtered = ratePlans.filter((p) => p.room_type_id === roomTypeId);

    const options = filtered.map((p) => ({
        value: p.id,
        label: `${p.name} · Rp ${Number(p.nightly_rate).toLocaleString('id-ID')}`,
        nightly_rate: Number(p.nightly_rate),
    }));

    if (filtered.length === 0 && roomTypeId) {
        return (
            <div>
                Base rate: Rp {baseRate.toLocaleString('id-ID')} / night
                <input type="hidden" name="rate_plan_id" value="" />
            </div>
        );
    }

    return (
        <Select
            allowClear
            placeholder="Select rate plan"
            value={value ?? undefined}
            options={options}
            onChange={(id, option) => {
                const nightly = (option as { nightly_rate?: number })?.nightly_rate ?? baseRate;
                onChange?.(id ?? null, nightly);
            }}
            style={{ width: '100%' }}
        />
    );
}
