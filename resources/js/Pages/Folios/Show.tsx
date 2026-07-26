import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Descriptions, Form, Input, InputNumber, Select, Space, Table, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface FolioShowProps {
    folio: {
        id: number;
        folio_no: string;
        status: string;
        status_label: string;
        type: string;
        opened_at?: string;
        closed_at?: string | null;
        guest?: { id: number; full_name: string; phone?: string; email?: string };
        reservation?: { id: number; reservation_code: string };
        company?: { id: number; name: string } | null;
        items: Array<{
            id: number;
            item_type_label: string;
            description: string;
            quantity: string;
            unit_price: string;
            amount: string;
            tax_amount: string;
            service_charge_amount: string;
            line_total: number;
            posted_at?: string;
            posted_by?: { name: string } | null;
        }>;
        payments: Array<{
            id: number;
            amount: string;
            method_label: string;
            reference_no?: string;
            paid_at?: string;
            received_by?: { name: string } | null;
            is_refund: boolean;
        }>;
    };
    balance: number;
    charges_total: number;
    payments_total: number;
    paymentMethods: Array<{ value: string; label: string }>;
    canPostPayment: boolean;
    canViewInvoice: boolean;
}

const formatIdr = (v: number | string) => `Rp ${Number(v).toLocaleString('id-ID')}`;

export default function FolioShow({
    folio,
    balance,
    charges_total,
    payments_total,
    paymentMethods,
    canPostPayment,
    canViewInvoice,
}: FolioShowProps) {
    const paymentForm = useForm({
        amount: balance > 0 ? balance : 0,
        method: 'cash',
        reference_no: '',
    });

    const submitPayment = () => {
        paymentForm.post(`/folios/${folio.id}/payments`, {
            preserveScroll: true,
            onSuccess: () => paymentForm.reset('reference_no'),
        });
    };

    return (
        <AuthenticatedLayout title={`Folio ${folio.folio_no}`}>
            <Head title={folio.folio_no} />
            <Space style={{ marginBottom: 16 }} wrap>
                {folio.reservation && (
                    <Link href={`/reservations/${folio.reservation.id}`}>
                        <Button>Back to Reservation</Button>
                    </Link>
                )}
                {canViewInvoice && (
                    <>
                        <Link href={`/folios/${folio.id}/invoice`}>
                            <Button>View Invoice</Button>
                        </Link>
                        <a href={`/folios/${folio.id}/invoice/download`}>
                            <Button>Download PDF</Button>
                        </a>
                    </>
                )}
            </Space>

            <Descriptions bordered column={2} size="small" style={{ marginBottom: 24 }}>
                <Descriptions.Item label="Folio No">{folio.folio_no}</Descriptions.Item>
                <Descriptions.Item label="Status">
                    <Tag color={folio.status === 'open' ? 'green' : 'default'}>{folio.status_label}</Tag>
                </Descriptions.Item>
                <Descriptions.Item label="Guest">{folio.guest?.full_name}</Descriptions.Item>
                <Descriptions.Item label="Reservation">{folio.reservation?.reservation_code}</Descriptions.Item>
                {folio.company && (
                    <Descriptions.Item label="Company" span={2}>{folio.company.name}</Descriptions.Item>
                )}
                <Descriptions.Item label="Charges">{formatIdr(charges_total)}</Descriptions.Item>
                <Descriptions.Item label="Payments">{formatIdr(payments_total)}</Descriptions.Item>
                <Descriptions.Item label="Balance" span={2}>
                    <strong style={{ fontSize: 16, color: balance > 0 ? '#cf1322' : '#389e0d' }}>
                        {formatIdr(balance)}
                    </strong>
                </Descriptions.Item>
            </Descriptions>

            <h3>Line Items</h3>
            <Table
                rowKey="id"
                size="small"
                pagination={false}
                style={{ marginBottom: 24 }}
                dataSource={folio.items}
                columns={[
                    { title: 'Type', dataIndex: 'item_type_label' },
                    { title: 'Description', dataIndex: 'description' },
                    { title: 'Qty', dataIndex: 'quantity', render: (v) => Number(v) },
                    { title: 'Unit Price', dataIndex: 'unit_price', render: formatIdr },
                    { title: 'Amount', dataIndex: 'amount', render: formatIdr },
                    { title: 'SC', dataIndex: 'service_charge_amount', render: formatIdr },
                    { title: 'Tax', dataIndex: 'tax_amount', render: formatIdr },
                    { title: 'Total', dataIndex: 'line_total', render: formatIdr },
                ]}
            />

            <h3>Payments</h3>
            <Table
                rowKey="id"
                size="small"
                pagination={false}
                style={{ marginBottom: 24 }}
                dataSource={folio.payments}
                columns={[
                    { title: 'Date', dataIndex: 'paid_at' },
                    { title: 'Method', dataIndex: 'method_label' },
                    { title: 'Reference', dataIndex: 'reference_no', render: (v) => v ?? '—' },
                    { title: 'Amount', dataIndex: 'amount', render: formatIdr },
                    { title: 'Received By', dataIndex: ['received_by', 'name'] },
                ]}
            />

            {canPostPayment && (
                <>
                    <h3>Post Payment</h3>
                    <Form layout="inline" onFinish={submitPayment} style={{ gap: 8 }}>
                        <Form.Item label="Amount">
                            <InputNumber
                                min={0}
                                value={paymentForm.data.amount}
                                onChange={(v) => paymentForm.setData('amount', v ?? 0)}
                                formatter={(v) => `${v}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                            />
                        </Form.Item>
                        <Form.Item label="Method">
                            <Select
                                style={{ width: 160 }}
                                value={paymentForm.data.method}
                                onChange={(v) => paymentForm.setData('method', v)}
                                options={paymentMethods}
                            />
                        </Form.Item>
                        <Form.Item label="Reference">
                            <Input
                                value={paymentForm.data.reference_no}
                                onChange={(e) => paymentForm.setData('reference_no', e.target.value)}
                            />
                        </Form.Item>
                        <Form.Item>
                            <Button type="primary" htmlType="submit" loading={paymentForm.processing}>
                                Post Payment
                            </Button>
                        </Form.Item>
                    </Form>
                </>
            )}
        </AuthenticatedLayout>
    );
}
