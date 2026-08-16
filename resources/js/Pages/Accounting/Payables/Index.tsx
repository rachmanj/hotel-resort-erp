import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Select, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface SupplierInvoiceRow {
    id: number;
    invoice_no: string;
    supplier_name: string | null;
    invoice_date: string;
    due_date: string;
    total_amount: number;
    status: string;
    status_label: string;
}

interface PayablesIndexProps {
    invoices: { data: SupplierInvoiceRow[] };
    filters: Record<string, string>;
    statusOptions: Array<{ value: string; label: string }>;
}

const formatIdr = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

export default function PayablesIndex({ invoices, filters, statusOptions }: PayablesIndexProps) {
    const columns: ProColumns<SupplierInvoiceRow>[] = [
        { title: 'Invoice No', dataIndex: 'invoice_no', width: 140 },
        { title: 'Supplier', dataIndex: 'supplier_name' },
        { title: 'Date', dataIndex: 'invoice_date', width: 110 },
        { title: 'Due', dataIndex: 'due_date', width: 110 },
        { title: 'Total', render: (_, r) => formatIdr(r.total_amount), width: 140 },
        { title: 'Status', width: 140, render: (_, r) => <Tag>{r.status_label}</Tag> },
        {
            title: 'Action',
            width: 80,
            render: (_, r) => <Link href={`/accounting/payables/${r.id}`}>View</Link>,
        },
    ];

    return (
        <AuthenticatedLayout title="Accounts Payable">
            <Head title="Supplier Invoices" />
            <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between' }}>
                <Select
                    allowClear
                    placeholder="Status"
                    style={{ width: 180 }}
                    value={filters.status}
                    options={statusOptions}
                    onChange={(v) => router.get('/accounting/payables', { status: v }, { preserveState: true })}
                />
                <Link href="/accounting/payables/create">
                    <Button type="primary">New Supplier Invoice</Button>
                </Link>
            </div>
            <ProTable rowKey="id" search={false} options={false} pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }} dataSource={invoices.data} columns={columns} scroll={{ x: 'max-content' }} />
        </AuthenticatedLayout>
    );
}
