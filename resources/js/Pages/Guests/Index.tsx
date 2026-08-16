import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Input, Space, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface GuestRow {
    id: number;
    full_name: string;
    phone?: string;
    email?: string;
    id_number?: string;
    vip_tier: string;
    vip_tier_label: string;
    is_blacklisted: boolean;
}

interface GuestsIndexProps {
    guests: Paginated<GuestRow>;
    filters: { search?: string };
}

export default function GuestsIndex({ guests, filters }: GuestsIndexProps) {
    const columns: ProColumns<GuestRow>[] = [
        { title: 'Name', dataIndex: 'full_name' },
        { title: 'Phone', dataIndex: 'phone', render: (v) => v ?? '–' },
        { title: 'Email', dataIndex: 'email', render: (v) => v ?? '–' },
        { title: 'ID Number', dataIndex: 'id_number', render: (v) => v ?? '–' },
        {
            title: 'VIP',
            dataIndex: 'vip_tier_label',
            render: (_, record) =>
                record.vip_tier !== 'none' ? (
                    <Tag color="gold">{record.vip_tier_label}</Tag>
                ) : (
                    '–'
                ),
        },
        {
            title: 'Status',
            dataIndex: 'is_blacklisted',
            render: (v) => (v ? <Tag color="red">Blacklisted</Tag> : <Tag color="green">Active</Tag>),
        },
        {
            title: 'Actions',
            render: (_, record) => (
                <Link href={`/guests/${record.id}`}>
                    <Button size="small">View</Button>
                </Link>
            ),
        },
    ];

    return (
        <AuthenticatedLayout title="Guests">
            <Head title="Guests" />
            <Space style={{ marginBottom: 16 }}>
                <Link href="/guests/create">
                    <Button type="primary">New Guest</Button>
                </Link>
                <Input.Search
                    placeholder="Search name, phone, ID..."
                    defaultValue={filters.search}
                    onSearch={(value) => router.get('/guests', { search: value || undefined }, { preserveState: true })}
                    style={{ width: 280 }}
                    allowClear
                />
            </Space>
            <ProTable<GuestRow>
                rowKey="id"
                search={false}
                options={false}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: guests.current_page,
                    pageSize: guests.per_page,
                    total: guests.total,
                    onChange: (page) => router.get('/guests', { ...filters, page }, { preserveState: true }),
                }}
                dataSource={guests.data}
                columns={columns}
                scroll={{ x: 'max-content' }}
            />
        </AuthenticatedLayout>
    );
}
