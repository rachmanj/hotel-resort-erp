import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface HotelRow {
    id: number;
    code: string;
    name: string;
    currency: string;
    timezone: string;
    is_active: boolean;
}

interface HotelsIndexProps {
    hotels: Paginated<HotelRow>;
    filters: { search?: string };
}

export default function HotelsIndex({ hotels, filters }: HotelsIndexProps) {
    const columns: ProColumns<HotelRow>[] = [
        { title: 'Code', dataIndex: 'code' },
        { title: 'Name', dataIndex: 'name' },
        { title: 'Currency', dataIndex: 'currency' },
        { title: 'Timezone', dataIndex: 'timezone' },
        {
            title: 'Status',
            dataIndex: 'is_active',
            render: (_, record) => (
                <Tag color={record.is_active ? 'green' : 'red'}>
                    {record.is_active ? 'Active' : 'Inactive'}
                </Tag>
            ),
        },
        {
            title: 'Actions',
            valueType: 'option',
            render: (_, record) => [
                <Link key="edit" href={`/admin/hotels/${record.id}/edit`}>
                    Edit
                </Link>,
                <Link key="users" href={`/admin/hotels/${record.id}/users`}>
                    Users
                </Link>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Hotels">
            <Head title="Hotels" />
            <ProTable<HotelRow>
                rowKey="id"
                columns={columns}
                dataSource={hotels.data}
                search={false}
                toolBarRender={() => [
                    <Link key="create" href="/admin/hotels/create">
                        <Button type="primary">New Hotel</Button>
                    </Link>,
                ]}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: hotels.current_page,
                    pageSize: hotels.per_page,
                    total: hotels.total,
                    onChange: (page) =>
                        router.get('/admin/hotels', { ...filters, page }, { preserveState: true }),
                }}
            />
        </AuthenticatedLayout>
    );
}
