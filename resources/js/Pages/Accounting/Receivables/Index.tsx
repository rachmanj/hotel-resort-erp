import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Select, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface ArInvoiceRow {
    id: number;
    invoice_no: string;
    company_name: string | null;
    period_start: string;
    period_end: string;
    total_amount: number;
    paid_amount: number;
    balance_due: number;
    status: string;
    status_label: string;
    due_date: string;
}

interface ReceivablesIndexProps {
    invoices: { data: ArInvoiceRow[] };
    filters: Record<string, string>;
    statusOptions: Array<{ value: string; label: string }>;
}

const statusColors: Record<string, string> = {
    open: 'blue',
    partially_paid: 'processing',
    paid: 'green',
    overdue: 'red',
    void: 'default',
};

const formatIdr = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

export default function ReceivablesIndex({ invoices, filters, statusOptions }: ReceivablesIndexProps) {
    const columns: ProColumns<ArInvoiceRow>[] = [
        { title: 'Invoice No', dataIndex: 'invoice_no', width: 160 },
        { title: 'Company', dataIndex: 'company_name' },
        { title: 'Period', render: (_, r) => `${r.period_start} – ${r.period_end}`, width: 200 },
        { title: 'Total', render: (_, r) => formatIdr(r.total_amount), width: 140 },
        { title: 'Balance', render: (_, r) => formatIdr(r.balance_due), width: 140 },
        { title: 'Due', dataIndex: 'due_date', width: 110 },
        {
            title: 'Status',
            width: 130,
            render: (_, r) => <Tag color={statusColors[r.status]}>{r.status_label}</Tag>,
        },
        {
            title: 'Action',
            width: 80,
            render: (_, r) => <Link href={`/accounting/receivables/${r.id}`}>View</Link>,
        },
    ];

    return (
        <AuthenticatedLayout title="Accounts Receivable">
            <Head title="AR Invoices" />
            <div style={{ marginBottom: 16 }}>
                <Select
                    allowClear
                    placeholder="Status"
                    style={{ width: 180 }}
                    value={filters.status}
                    options={statusOptions}
                    onChange={(v) => router.get('/accounting/receivables', { status: v }, { preserveState: true })}
                />
            </div>
            <ProTable rowKey="id" search={false} options={false} dataSource={invoices.data} columns={columns} />
        </AuthenticatedLayout>
    );
}
