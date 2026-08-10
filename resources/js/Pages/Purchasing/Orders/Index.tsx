import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Select, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface OrderRow {
    id: number;
    po_no: string;
    supplier: string | null;
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
        { title: 'PO #', dataIndex: 'po_no' },
        { title: 'Supplier', dataIndex: 'supplier' },
        { title: 'Status', dataIndex: 'status_label', render: (_, r) => <Tag>{r.status_label}</Tag> },
        { title: 'Total', render: (_, r) => `Rp ${r.total_amount.toLocaleString('id-ID')}` },
        { title: 'Ordered', dataIndex: 'ordered_at' },
        {
            title: 'Actions',
            render: (_, r) => r.status === 'sent' || r.status === 'partially_received' ? (
                <Button size="small" onClick={() => router.post(`/purchasing/orders/${r.id}/receive`)}>Mark Received</Button>
            ) : null,
        },
    ];

    return (
        <AuthenticatedLayout title="Purchase Orders">
            <Head title="Purchase Orders" />
            <Select allowClear placeholder="Status" style={{ width: 160, marginBottom: 16 }} value={filters.status} options={statusOptions}
                onChange={(v) => router.get('/purchasing/orders', { status: v }, { preserveState: true })} />
            <ProTable rowKey="id" search={false} options={false} pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }} dataSource={orders.data} columns={columns} />
        </AuthenticatedLayout>
    );
}
