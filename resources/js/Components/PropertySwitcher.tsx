import { router } from '@inertiajs/react';
import { Select } from 'antd';
import type { Hotel } from '@/types';

interface PropertySwitcherProps {
    currentHotel: Hotel | null;
    availableHotels: Hotel[];
}

export default function PropertySwitcher({ currentHotel, availableHotels }: PropertySwitcherProps) {
    if (availableHotels.length <= 1) {
        return null;
    }

    const handleChange = (hotelId: number) => {
        router.post(
            '/hotel-context/switch',
            { hotel_id: hotelId },
            {
                preserveScroll: true,
                onSuccess: () => router.reload({ only: ['currentHotel', 'availableHotels'] }),
            },
        );
    };

    return (
        <Select
            value={currentHotel?.id}
            onChange={handleChange}
            style={{ minWidth: 200 }}
            options={availableHotels.map((hotel) => ({
                value: hotel.id,
                label: hotel.name,
            }))}
        />
    );
}
