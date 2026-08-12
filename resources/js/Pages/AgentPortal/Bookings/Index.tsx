import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Select, Tag } from 'antd';
import AgentPortalLayout from '@/Layouts/AgentPortalLayout';
import type { Paginated } from '@/types';

interface BookingRow {
    id: number;
    reservation_code: string;
    guest_name?: string;
    guest_phone?: string;
    status: string;
    status_label: string;
    arrival_date?: string;
    departure_date?: string;
    room_number?: string;
    room_type?: string;
}

interface BookingsIndexProps {
    bookings: Paginated<BookingRow>;
    agent: { id: number; name: string; code: string };
    filters: { status?: string };
}

const statusColors: Record<string, string> = {
    confirmed: 'blue',
    checked_in: 'green',
    checked_out: 'default',
    cancelled: 'red',
    tentative: 'default',
};

export default function BookingsIndex({ bookings, agent, filters }: BookingsIndexProps) {
    const columns: ProColumns<BookingRow>[] = [
        { title: 'Code', dataIndex: 'reservation_code' },
        { title: 'Guest', dataIndex: 'guest_name' },
        { title: 'Phone', dataIndex: 'guest_phone', render: (v) => v ?? '—' },
        {
            title: 'Status',
            dataIndex: 'status_label',
            render: (_, r) => <Tag color={statusColors[r.status] ?? 'default'}>{r.status_label}</Tag>,
        },
        { title: 'Arrival', dataIndex: 'arrival_date' },
        { title: 'Departure', dataIndex: 'departure_date' },
        { title: 'Room', dataIndex: 'room_number', render: (v) => v ?? 'TBA' },
        { title: 'Room Type', dataIndex: 'room_type', render: (v) => v ?? '—' },
    ];

    return (
        <AgentPortalLayout title="My Bookings">
            <Head title="Agent Bookings" />
            <p style={{ marginBottom: 16, color: '#666' }}>
                Agent: <strong>{agent.name}</strong> ({agent.code})
            </p>
            <ProTable<BookingRow>
                rowKey="id"
                columns={columns}
                dataSource={bookings.data}
                search={false}
                toolBarRender={() => [
                    <Select
                        key="status"
                        allowClear
                        placeholder="Filter status"
                        style={{ width: 160 }}
                        value={filters.status}
                        onChange={(v) =>
                            router.get('/agent-portal/bookings', { status: v ?? undefined }, { preserveState: true })
                        }
                        options={[
                            { value: 'confirmed', label: 'Confirmed' },
                            { value: 'checked_in', label: 'Checked In' },
                            { value: 'checked_out', label: 'Checked Out' },
                        ]}
                    />,
                ]}
                pagination={{
                    current: bookings.current_page,
                    pageSize: bookings.per_page,
                    total: bookings.total,
                    onChange: (page) =>
                        router.get('/agent-portal/bookings', { ...filters, page }, { preserveState: true }),
                }}
            />
        </AgentPortalLayout>
    );
}
