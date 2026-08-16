import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Select, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface OrderRow {
    id: number;
    po_no: string;
    supplier: { id: number; name: string } | null;
    status: string;
    status_label: string;
    total_amount: number;
    ordered_at: string | null;
}

interface OrdersIndexProps {
    orders: { data: OrderRow[] };
    filters: Record<string, string>;
    statusOptions: Array<{ value: string; label: string }>;
}

export default function PurchaseOrdersIndex({ orders, filters, statusOptions }: OrdersIndexProps) {
    const columns: ProColumns<OrderRow>[] = [
        {
            title: 'PO #',
            dataIndex: 'po_no',
            render: (_, r) => <Link href={`/purchasing/orders/${r.id}`}>{r.po_no}</Link>,
        },
        { title: 'Supplier', render: (_, r) => r.supplier?.name ?? '–' },
        { title: 'Status', dataIndex: 'status_label', render: (_, r) => <Tag>{r.status_label}</Tag> },
        { title: 'Total', render: (_, r) => `Rp ${r.total_amount.toLocaleString('id-ID')}` },
        { title: 'Ordered', dataIndex: 'ordered_at' },
        {
            title: 'Actions',
            render: (_, r) =>
                r.status === 'sent' || r.status === 'partially_received' ? (
                    <Link href={`/purchasing/orders/${r.id}`}>
                        <Button size="small">Receive</Button>
                    </Link>
                ) : null,
        },
    ];

    return (
        <AuthenticatedLayout title="Purchase Orders">
            <Head title="Purchase Orders" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8, alignItems: 'center' }}>
                <Link href="/purchasing/orders/create">
                    <Button type="primary">New Order</Button>
                </Link>
                <Select allowClear placeholder="Status" style={{ width: 160 }} value={filters.status} options={statusOptions}
                    onChange={(v) => router.get('/purchasing/orders', { status: v }, { preserveState: true })} />
            </div>
            <ProTable rowKey="id" search={false} options={false} pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }} dataSource={orders.data} columns={columns} />
        </AuthenticatedLayout>
    );
}
