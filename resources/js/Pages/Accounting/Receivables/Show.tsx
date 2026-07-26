import { Head, Link } from '@inertiajs/react';
import { Card, Descriptions, Table, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface FolioRow {
    id: number;
    folio_no: string;
    guest_name: string | null;
    balance: number;
}

interface ReceivablesShowProps {
    invoice: {
        id: number;
        invoice_no: string;
        company_name: string | null;
        period_start: string;
        period_end: string;
        total_amount: number;
        paid_amount: number;
        balance_due: number;
        original_currency_code: string | null;
        original_amount: number | null;
        status: string;
        status_label: string;
        due_date: string;
        issued_at: string;
        folios: FolioRow[];
    };
}

const formatIdr = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

export default function ReceivablesShow({ invoice }: ReceivablesShowProps) {
    return (
        <AuthenticatedLayout title={`AR Invoice ${invoice.invoice_no}`}>
            <Head title={invoice.invoice_no} />
            <Link href="/accounting/receivables" style={{ marginBottom: 16, display: 'inline-block' }}>
                ← Back to AR Invoices
            </Link>
            <Card>
                <Descriptions bordered column={2}>
                    <Descriptions.Item label="Invoice No">{invoice.invoice_no}</Descriptions.Item>
                    <Descriptions.Item label="Company">{invoice.company_name}</Descriptions.Item>
                    <Descriptions.Item label="Period">
                        {invoice.period_start} – {invoice.period_end}
                    </Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag>{invoice.status_label}</Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="Total">{formatIdr(invoice.total_amount)}</Descriptions.Item>
                    <Descriptions.Item label="Paid">{formatIdr(invoice.paid_amount)}</Descriptions.Item>
                    <Descriptions.Item label="Balance Due">{formatIdr(invoice.balance_due)}</Descriptions.Item>
                    <Descriptions.Item label="Due Date">{invoice.due_date}</Descriptions.Item>
                    {invoice.original_currency_code && (
                        <Descriptions.Item label="Original Amount">
                            {invoice.original_currency_code} {invoice.original_amount?.toLocaleString()}
                        </Descriptions.Item>
                    )}
                </Descriptions>
            </Card>
            <Card title="Linked Folios" style={{ marginTop: 16 }}>
                <Table
                    rowKey="id"
                    dataSource={invoice.folios}
                    pagination={false}
                    columns={[
                        { title: 'Folio No', dataIndex: 'folio_no' },
                        { title: 'Guest', dataIndex: 'guest_name' },
                        { title: 'Balance', render: (_, r) => formatIdr(r.balance) },
                    ]}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
