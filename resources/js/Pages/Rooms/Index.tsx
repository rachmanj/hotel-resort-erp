import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface RoomRow {
    id: number;
    number: string;
    status: string;
    status_label: string;
    status_color: string;
    room_type?: { id: number; name: string; code: string };
    floor?: { id: number; name: string; level: number };
    notes?: string | null;
}

interface RoomsIndexProps {
    rooms: Paginated<RoomRow>;
    statuses: Array<{ value: string; label: string; color: string }>;
    filters: { search?: string; status?: string };
}

export default function RoomsIndex({ rooms, statuses, filters }: RoomsIndexProps) {
    const columns: ProColumns<RoomRow>[] = [
        {
            title: 'Room',
            dataIndex: 'number',
            render: (_, record) => (
                <Link href={`/rooms/${record.id}`}>{record.number}</Link>
            ),
        },
        {
            title: 'Type',
            dataIndex: ['room_type', 'name'],
        },
        {
            title: 'Floor',
            dataIndex: ['floor', 'name'],
        },
        {
            title: 'Status',
            dataIndex: 'status_label',
            render: (_, record) => (
                <Tag color={record.status_color}>{record.status_label}</Tag>
            ),
            valueType: 'select',
            valueEnum: Object.fromEntries(
                statuses.map((s) => [s.value, { text: s.label }]),
            ),
        },
    ];

    return (
        <AuthenticatedLayout title="Rooms">
            <Head title="Rooms" />
            <ProTable<RoomRow>
                rowKey="id"
                columns={columns}
                dataSource={rooms.data}
                search={false}
                options={{ reload: false, density: true }}
                pagination={{
                    current: rooms.current_page,
                    pageSize: rooms.per_page,
                    total: rooms.total,
                    onChange: (page) =>
                        router.get('/rooms', { ...filters, page }, { preserveState: true }),
                }}
                toolBarRender={() => []}
            />
        </AuthenticatedLayout>
    );
}
