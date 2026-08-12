import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Input, Select, Space, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface GroupRow {
    id: number;
    group_code: string;
    name: string;
    group_type_label: string;
    status: string;
    status_label: string;
    status_color: string;
    invoice_mode_label: string;
    arrival_date?: string | null;
    departure_date?: string | null;
    deposit_amount: number;
    room_count: number;
    pic_guest?: { full_name: string } | null;
    company?: { name: string } | null;
}

interface GroupsIndexProps {
    groups: Paginated<GroupRow>;
    statuses: Array<{ value: string; label: string; color: string }>;
    groupTypes: Array<{ value: string; label: string }>;
    filters: {
        status?: string;
        group_type?: string;
        date_from?: string;
        date_to?: string;
        search?: string;
    };
}

const formatIdr = (v: number) => `Rp ${v.toLocaleString('id-ID')}`;

export default function GroupsIndex({ groups, statuses, groupTypes, filters }: GroupsIndexProps) {
    const columns: ProColumns<GroupRow>[] = [
        {
            title: 'Code',
            dataIndex: 'group_code',
            render: (_, record) => <Link href={`/groups/${record.id}`}>{record.group_code}</Link>,
        },
        { title: 'Name', dataIndex: 'name' },
        { title: 'Type', dataIndex: 'group_type_label' },
        {
            title: 'PIC',
            dataIndex: ['pic_guest', 'full_name'],
            render: (v) => v ?? '—',
        },
        { title: 'Rooms', dataIndex: 'room_count' },
        {
            title: 'Status',
            dataIndex: 'status_label',
            render: (_, record) => <Tag color={record.status_color}>{record.status_label}</Tag>,
        },
        {
            title: 'Arrival',
            dataIndex: 'arrival_date',
            render: (v) => v ?? '—',
        },
        {
            title: 'Departure',
            dataIndex: 'departure_date',
            render: (v) => v ?? '—',
        },
        {
            title: 'Deposit',
            dataIndex: 'deposit_amount',
            render: formatIdr,
        },
        {
            title: 'Invoice',
            dataIndex: 'invoice_mode_label',
        },
    ];

    const applyFilters = (patch: Record<string, string | undefined>) => {
        router.get('/groups', { ...filters, ...patch, page: undefined }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout title="Group Bookings">
            <Head title="Group Bookings" />
            <Space style={{ marginBottom: 16 }} wrap>
                <Link href="/groups/create">
                    <Button type="primary">New Group</Button>
                </Link>
                <Input.Search
                    placeholder="Search code or name..."
                    defaultValue={filters.search}
                    onSearch={(value) => applyFilters({ search: value || undefined })}
                    style={{ width: 240 }}
                    allowClear
                />
                <Select
                    allowClear
                    placeholder="Status"
                    style={{ width: 180 }}
                    value={filters.status}
                    onChange={(value) => applyFilters({ status: value })}
                    options={statuses.map((s) => ({ value: s.value, label: s.label }))}
                />
                <Select
                    allowClear
                    placeholder="Type"
                    style={{ width: 200 }}
                    value={filters.group_type}
                    onChange={(value) => applyFilters({ group_type: value })}
                    options={groupTypes.map((t) => ({ value: t.value, label: t.label }))}
                />
            </Space>
            <ProTable<GroupRow>
                rowKey="id"
                search={false}
                options={false}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: groups.current_page,
                    pageSize: groups.per_page,
                    total: groups.total,
                    onChange: (page) => router.get('/groups', { ...filters, page }, { preserveState: true }),
                }}
                dataSource={groups.data}
                columns={columns}
            />
        </AuthenticatedLayout>
    );
}
