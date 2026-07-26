import { router } from '@inertiajs/react';
import { Select, Spin } from 'antd';
import { useCallback, useEffect, useState } from 'react';

interface GuestOption {
    id: number;
    full_name: string;
    phone?: string;
    id_number?: string;
    email?: string;
}

interface GuestSearchSelectProps {
    value?: number | null;
    onChange?: (guestId: number | null, guest?: GuestOption) => void;
    onNewGuest?: (name: string, phone: string) => void;
    newGuestName?: string;
    newGuestPhone?: string;
}

export default function GuestSearchSelect({
    value,
    onChange,
    onNewGuest,
    newGuestName = '',
    newGuestPhone = '',
}: GuestSearchSelectProps) {
    const [options, setOptions] = useState<GuestOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');

    const searchGuests = useCallback(async (query: string) => {
        if (query.length < 2) {
            setOptions([]);
            return;
        }

        setLoading(true);
        try {
            const response = await fetch(`/guests/search?q=${encodeURIComponent(query)}`);
            const data = (await response.json()) as GuestOption[];
            setOptions(data);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => searchGuests(search), 300);
        return () => clearTimeout(timer);
    }, [search, searchGuests]);

    const selectOptions = options.map((g) => ({
        value: g.id,
        label: `${g.full_name}${g.phone ? ` — ${g.phone}` : ''}`,
        guest: g,
    }));

    if (value === null && newGuestName) {
        selectOptions.unshift({
            value: -1,
            label: `New: ${newGuestName}`,
            guest: {
                id: -1,
                full_name: newGuestName,
                phone: newGuestPhone,
            },
        });
    }

    return (
        <Select
            showSearch
            allowClear
            placeholder="Search guest by name, phone, or ID"
            filterOption={false}
            onSearch={setSearch}
            notFoundContent={loading ? <Spin size="small" /> : 'Type to search...'}
            value={value ?? undefined}
            options={selectOptions}
            onChange={(selectedId, option) => {
                if (selectedId === -1) {
                    onChange?.(null);
                    return;
                }
                const guest = (option as { guest?: GuestOption })?.guest;
                onChange?.(selectedId as number, guest);
            }}
            dropdownRender={(menu) => (
                <>
                    {menu}
                    {search.length >= 2 && options.length === 0 && !loading && (
                        <div style={{ padding: 8 }}>
                            <button
                                type="button"
                                style={{
                                    background: 'none',
                                    border: 'none',
                                    color: '#1677ff',
                                    cursor: 'pointer',
                                    padding: 0,
                                }}
                                onClick={() => {
                                    const name = search;
                                    onNewGuest?.(name, '');
                                }}
                            >
                                Create new guest &quot;{search}&quot;
                            </button>
                        </div>
                    )}
                </>
            )}
        />
    );
}
