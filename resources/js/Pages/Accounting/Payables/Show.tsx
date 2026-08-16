import { Head, Link, router } from '@inertiajs/react';
import { Button, Card, Descriptions, Table, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface PayablesShowProps {
    invoice: {
        id: number;
        invoice_no: string;
        supplier_name: string | null;
        purchase_order_no: string | null;
        invoice_date: string;
        due_date: string;
        subtotal: number;
        tax_amount: number;
        withholding_tax_amount: number;
        total_amount: number;
        status: string;
        status_label: string;
        lines: Array<{
            description: string;
            account_code: string | null;
            account_name: string | null;
            quantity: number;
            unit_cost: number;
            amount: number;
        }>;
    };
}

const formatIdr = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

export default function PayablesShow({ invoice }: PayablesShowProps) {
    const canApprove = invoice.status === 'pending_approval';

    return (
        <AuthenticatedLayout title={`Supplier Invoice ${invoice.invoice_no}`}>
            <Head title={invoice.invoice_no} />
            <Link href="/accounting/payables" style={{ marginBottom: 16, display: 'inline-block' }}>
                ← Back
            </Link>
            <Card
                extra={
                    canApprove && (
                        <Button
                            type="primary"
                            onClick={() => router.post(`/accounting/payables/${invoice.id}/approve`)}
                        >
                            Approve & Post to GL
                        </Button>
                    )
                }
            >
                <Descriptions bordered column={2}>
                    <Descriptions.Item label="Invoice No">{invoice.invoice_no}</Descriptions.Item>
                    <Descriptions.Item label="Supplier">{invoice.supplier_name}</Descriptions.Item>
                    <Descriptions.Item label="PO">{invoice.purchase_order_no ?? '–'}</Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag>{invoice.status_label}</Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="Subtotal">{formatIdr(invoice.subtotal)}</Descriptions.Item>
                    <Descriptions.Item label="PPN">{formatIdr(invoice.tax_amount)}</Descriptions.Item>
                    <Descriptions.Item label="PPh 23">{formatIdr(invoice.withholding_tax_amount)}</Descriptions.Item>
                    <Descriptions.Item label="Total">{formatIdr(invoice.total_amount)}</Descriptions.Item>
                </Descriptions>
            </Card>
            <Card title="Line Items" style={{ marginTop: 16 }}>
                <Table
                    rowKey="description"
                    dataSource={invoice.lines}
                    pagination={false}
                    columns={[
                        { title: 'Description', dataIndex: 'description' },
                        {
                            title: 'Account',
                            render: (_, r) => `${r.account_code} - ${r.account_name}`,
                        },
                        { title: 'Qty', dataIndex: 'quantity' },
                        { title: 'Unit Cost', render: (_, r) => formatIdr(r.unit_cost) },
                        { title: 'Amount', render: (_, r) => formatIdr(r.amount) },
                    ]}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
