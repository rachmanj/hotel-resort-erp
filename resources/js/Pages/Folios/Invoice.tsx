import { Head, Link } from '@inertiajs/react';
import { Button, Descriptions, Table, theme } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface FolioInvoiceProps {
    folio: {
        id?: number;
        folio_no: string;
        status: string;
        opened_at?: string;
        closed_at?: string | null;
        guest?: { full_name: string; phone?: string; email?: string; address?: string };
        reservation?: { reservation_code: string; arrival_date: string; departure_date: string };
        company?: { name: string; tax_id?: string; billing_address?: string } | null;
        items: Array<{
            description: string;
            quantity: string;
            unit_price: string;
            amount: string;
            tax_amount: string;
            service_charge_amount: string;
            line_total: number;
        }>;
        payments: Array<{
            amount: string;
            method: string;
            reference_no?: string;
            paid_at?: string;
        }>;
    };
    balance: number;
    charges_total: number;
}

const formatIdr = (v: number | string) => `Rp ${Number(v).toLocaleString('id-ID')}`;

export default function FolioInvoice({ folio, balance, charges_total }: FolioInvoiceProps) {
    const { token } = theme.useToken();
    return (
        <AuthenticatedLayout title={`Invoice ${folio.folio_no}`}>
            <Head title={`Invoice ${folio.folio_no}`} />
            <div style={{ maxWidth: 800, margin: '0 auto' }}>
                <div style={{ textAlign: 'right', marginBottom: 16 }}>
                    <Button onClick={() => window.print()}>Print</Button>
                    <a href={`/folios/${folio.id}/invoice/download`} style={{ marginLeft: 8 }}>
                        <Button type="primary">Download PDF</Button>
                    </a>
                </div>

                <h1 style={{ textAlign: 'center' }}>INVOICE</h1>
                <p style={{ textAlign: 'center', color: token.colorTextSecondary }}>{folio.folio_no}</p>

                <Descriptions bordered column={2} size="small" style={{ marginBottom: 24 }}>
                    <Descriptions.Item label="Guest">{folio.guest?.full_name}</Descriptions.Item>
                    <Descriptions.Item label="Phone">{folio.guest?.phone ?? '–'}</Descriptions.Item>
                    <Descriptions.Item label="Reservation">{folio.reservation?.reservation_code}</Descriptions.Item>
                    <Descriptions.Item label="Stay">
                        {folio.reservation?.arrival_date} · {folio.reservation?.departure_date}
                    </Descriptions.Item>
                    {folio.company && (
                        <Descriptions.Item label="Bill To" span={2}>
                            {folio.company.name} (NPWP: {folio.company.tax_id ?? '–'})
                        </Descriptions.Item>
                    )}
                </Descriptions>

                <Table
                    rowKey="description"
                    size="small"
                    pagination={false}
                    dataSource={folio.items}
                    columns={[
                        { title: 'Description', dataIndex: 'description' },
                        { title: 'Qty', dataIndex: 'quantity', render: (v) => Number(v) },
                        { title: 'Unit', dataIndex: 'unit_price', render: formatIdr },
                        { title: 'Amount', dataIndex: 'amount', render: formatIdr },
                        { title: 'SC', dataIndex: 'service_charge_amount', render: formatIdr },
                        { title: 'Tax', dataIndex: 'tax_amount', render: formatIdr },
                        { title: 'Total', dataIndex: 'line_total', render: formatIdr },
                    ]}
                />

                <div style={{ textAlign: 'right', marginTop: 24 }}>
                    <p><strong>Charges Total:</strong> {formatIdr(charges_total)}</p>
                    <p style={{ fontSize: 18 }}><strong>Balance Due:</strong> {formatIdr(balance)}</p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
