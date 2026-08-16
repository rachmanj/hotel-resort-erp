import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, DatePicker, Input, Select, Space, Tag } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface ReservationRow {
    id: number;
    reservation_code: string;
    status: string;
    status_label: string;
    status_color: string;
    source: string;
    source_label: string;
    arrival_date: string;
    departure_date: string;
    adults: number;
    children: number;
    guest?: { id: number; full_name: string; phone?: string };
    agent?: { id: number; name: string } | null;
    rooms: Array<{ room_number?: string; room_type?: string; nightly_rate: string }>;
}

interface ReservationsIndexProps {
    reservations: Paginated<ReservationRow>;
    statuses: Array<{ value: string; label: string; color: string }>;
    sources: Array<{ value: string; label: string }>;
    filters: {
        status?: string;
        source?: string;
        date_from?: string;
        date_to?: string;
        guest_search?: string;
    };
}

export default function ReservationsIndex({
    reservations,
    statuses,
    sources,
    filters,
}: ReservationsIndexProps) {
    const [localFilters, setLocalFilters] = useState(filters);

    const applyFilters = () => {
        router.get('/reservations', localFilters, { preserveState: true });
    };

    const columns: ProColumns<ReservationRow>[] = [
        {
            title: 'Code',
            dataIndex: 'reservation_code',
            render: (_, record) => (
                <Link href={`/reservations/${record.id}`}>{record.reservation_code}</Link>
            ),
        },
        {
            title: 'Guest',
            dataIndex: ['guest', 'full_name'],
            render: (_, record) => record.guest?.full_name ?? '–',
        },
        {
            title: 'Agent',
            dataIndex: ['agent', 'name'],
            render: (_, record) => record.agent?.name ?? '–',
        },
        {
            title: 'Arrival',
            dataIndex: 'arrival_date',
        },
        {
            title: 'Departure',
            dataIndex: 'departure_date',
        },
        {
            title: 'Rooms',
            dataIndex: 'rooms',
            render: (_, record) =>
                record.rooms
                    .map((r) => `${r.room_number ?? '–'} (${r.room_type ?? ''})`)
                    .join(', '),
        },
        {
            title: 'Source',
            dataIndex: 'source_label',
        },
        {
            title: 'Status',
            dataIndex: 'status_label',
            render: (_, record) => (
                <Tag color={record.status_color}>{record.status_label}</Tag>
            ),
        },
        {
            title: 'Actions',
            valueType: 'option' as const,
            render: (_: unknown, record: ReservationRow) => [
                <Link key="edit" href={`/reservations/${record.id}/edit`}>
                    <Button type="link">Edit</Button>
                </Link>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Reservations">
            <Head title="Reservations" />
            <Space style={{ marginBottom: 16 }} wrap>
                <Input
                    placeholder="Search guest..."
                    value={localFilters.guest_search ?? ''}
                    onChange={(e) =>
                        setLocalFilters({ ...localFilters, guest_search: e.target.value })
                    }
                    style={{ width: 200 }}
                />
                <Select
                    allowClear
                    placeholder="Status"
                    value={localFilters.status}
                    onChange={(v) => setLocalFilters({ ...localFilters, status: v })}
                    options={statuses.map((s) => ({ value: s.value, label: s.label }))}
                    style={{ width: 160 }}
                />
                <Select
                    allowClear
                    placeholder="Source"
                    value={localFilters.source}
                    onChange={(v) => setLocalFilters({ ...localFilters, source: v })}
                    options={sources.map((s) => ({ value: s.value, label: s.label }))}
                    style={{ width: 140 }}
                />
                <DatePicker
                    placeholder="From"
                    value={localFilters.date_from ? dayjs(localFilters.date_from) : null}
                    onChange={(d) =>
                        setLocalFilters({
                            ...localFilters,
                            date_from: d?.format('YYYY-MM-DD'),
                        })
                    }
                />
                <DatePicker
                    placeholder="To"
                    value={localFilters.date_to ? dayjs(localFilters.date_to) : null}
                    onChange={(d) =>
                        setLocalFilters({
                            ...localFilters,
                            date_to: d?.format('YYYY-MM-DD'),
                        })
                    }
                />
                <Button onClick={applyFilters}>Filter</Button>
                <Link href="/reservations/create">
                    <Button type="primary">New Reservation</Button>
                </Link>
                <Link href="/reservations/calendar">
                    <Button>Calendar</Button>
                </Link>
            </Space>
            <ProTable<ReservationRow>
                rowKey="id"
                columns={columns}
                dataSource={reservations.data}
                search={false}
                options={false}
                expandable={{ childrenColumnName: 'rowChildren' }}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: reservations.current_page,
                    pageSize: reservations.per_page,
                    total: reservations.total,
                    onChange: (page) =>
                        router.get('/reservations', { ...filters, page }, { preserveState: true }),
                }}
                scroll={{ x: 'max-content' }}
                locale={{ emptyText: 'No reservations yet. Create the first reservation.' }}
            />
        </AuthenticatedLayout>
    );
}
