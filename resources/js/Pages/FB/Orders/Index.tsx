import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Select, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';

interface OrderRow {
    id: number;
    order_no: string;
    order_type: string;
    order_type_label: string;
    status: string;
    status_label: string;
    total_amount: number;
    charged_to_room: boolean;
    table: string | null;
    guest: string | null;
    opened_by: string | null;
    created_at: string;
}

interface OrdersIndexProps {
    orders: {
        data: OrderRow[];
        links: unknown;
        meta: unknown;
    };
    filters: { status?: string; order_type?: string };
    statusOptions: Array<{ value: string; label: string }>;
    typeOptions: Array<{ value: string; label: string }>;
}

const statusColors: Record<string, string> = {
    new: 'blue',
    preparing: 'orange',
    ready: 'green',
    served: 'default',
    cancelled: 'red',
};

export default function OrdersIndex({ orders, filters, statusOptions, typeOptions }: OrdersIndexProps) {
    const { can } = useAuth();

    const columns: ProColumns<OrderRow>[] = [
        {
            title: 'Order #',
            dataIndex: 'order_no',
            render: (_, record) => <Link href={`/fb/orders/${record.id}`}>{record.order_no}</Link>,
        },
        { title: 'Type', dataIndex: 'order_type_label' },
        {
            title: 'Status',
            dataIndex: 'status_label',
            render: (_, record) => <Tag color={statusColors[record.status]}>{record.status_label}</Tag>,
        },
        {
            title: 'Total',
            dataIndex: 'total_amount',
            render: (v) => `Rp ${Number(v).toLocaleString('id-ID')}`,
        },
        { title: 'Table', dataIndex: 'table', render: (v) => v ?? '–' },
        { title: 'Guest', dataIndex: 'guest', render: (v) => v ?? '–' },
        { title: 'Opened By', dataIndex: 'opened_by' },
        { title: 'Created', dataIndex: 'created_at' },
        {
            title: 'Room Charge',
            dataIndex: 'charged_to_room',
            render: (v) => (v ? <Tag color="purple">Yes</Tag> : '–'),
        },
    ];

    return (
        <AuthenticatedLayout title="F&B Orders">
            <Head title="Orders" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 12, flexWrap: 'wrap', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', gap: 8 }}>
                    <Select
                        allowClear
                        placeholder="Status"
                        style={{ width: 140 }}
                        value={filters.status}
                        options={statusOptions}
                        onChange={(v) => router.get('/fb/orders', { ...filters, status: v }, { preserveState: true })}
                    />
                    <Select
                        allowClear
                        placeholder="Type"
                        style={{ width: 140 }}
                        value={filters.order_type}
                        options={typeOptions}
                        onChange={(v) => router.get('/fb/orders', { ...filters, order_type: v }, { preserveState: true })}
                    />
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                    {can('fb.view') && (
                        <Link href="/fb/kds">
                            <Button>Kitchen Display</Button>
                        </Link>
                    )}
                    {can('fb.orders.create') && (
                        <Link href="/fb/orders/create">
                            <Button type="primary">New Order</Button>
                        </Link>
                    )}
                </div>
            </div>
            <ProTable<OrderRow>
                rowKey="id"
                search={false}
                options={false}
                dataSource={orders.data}
                columns={columns}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    total: (orders as { total?: number }).total,
                    current: (orders as { current_page?: number }).current_page,
                    pageSize: (orders as { per_page?: number }).per_page ?? 20,
                    onChange: (page) => router.get('/fb/orders', { ...filters, page }, { preserveState: true }),
                }}
            />
        </AuthenticatedLayout>
    );
}
