import { Head, Link, useForm } from '@inertiajs/react';
import { Alert, Button, Descriptions, Popconfirm, Space, Table, Tag, theme } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface GuestInvoiceLine {
    id: number;
    description: string;
    quantity: number;
    nights: number | null;
    unit_price: number;
    amount: number;
    tax_amount: number;
    service_charge_amount: number;
    line_total: number;
}

interface GuestInvoiceProps {
    company: { name: string; title: string };
    folio: { id: number; folio_no: string };
    customer: { id?: number | null; name?: string | null; contact?: string | null };
    invoice: {
        id: number;
        number: string;
        status: string;
        status_label: string;
        status_color: string;
        revision: number;
        date: string;
        issued_at?: string | null;
        released_at?: string | null;
        released_by?: string | null;
        prepared_by?: string | null;
        approved_by?: string | null;
        notes?: string | null;
        subtotal: number;
        total: number;
        lines: GuestInvoiceLine[];
    };
    terms: {
        title: string;
        bank_accounts: Array<{ bank_name: string; account_no: string; account_name: string }>;
        items: string[];
    };
    signatures: { prepared_by: string; approved_by: string; received_by: string };
    show_tax_columns: boolean;
    canRelease: boolean;
}

const formatIdr = (value: number | string) => `Rp ${Number(value).toLocaleString('id-ID')}`;

export default function GuestInvoice({
    company,
    folio,
    customer,
    invoice,
    terms,
    signatures,
    show_tax_columns,
    canRelease,
}: GuestInvoiceProps) {
    const { token } = theme.useToken();
    const releaseForm = useForm({});

    const isReleased = invoice.status === 'released';

    const submitRelease = () => {
        releaseForm.post(`/folios/${folio.id}/guest-invoice/release`, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout title={`Invoice ${invoice.number}`}>
            <Head title={`Invoice ${invoice.number}`} />

            <Space style={{ marginBottom: 16 }} wrap>
                <Link href={`/folios/${folio.id}`}>
                    <Button>Back to folio</Button>
                </Link>
                <a href={`/folios/${folio.id}/guest-invoice/download`}>
                    <Button>Download PDF</Button>
                </a>
                {canRelease && !isReleased && (
                    <Popconfirm
                        title="Release invoice"
                        description="Once released the invoice can be sent to the guest and is never rewritten. Later folio changes create a new revision."
                        okText="Release"
                        cancelText="Cancel"
                        onConfirm={submitRelease}
                    >
                        <Button type="primary" loading={releaseForm.processing}>
                            Release
                        </Button>
                    </Popconfirm>
                )}
            </Space>

            {!isReleased && (
                <Alert
                    type="warning"
                    showIcon
                    style={{ marginBottom: 16, maxWidth: 900 }}
                    message="Draft, not yet released by Finance"
                    description="This invoice still follows the folio charges and must not be sent to the guest until Finance releases it."
                />
            )}

            <div style={{ maxWidth: 900 }}>
                <div style={{ textAlign: 'center', marginBottom: 24 }}>
                    <div style={{ fontSize: token.fontSizeHeading3, fontWeight: 600, letterSpacing: 1 }}>
                        {company.name}
                    </div>
                    <div style={{ fontSize: token.fontSizeHeading4, fontWeight: 600, letterSpacing: 3 }}>
                        {company.title}
                    </div>
                </div>

                <Descriptions bordered column={2} size="small" style={{ marginBottom: 24 }}>
                    <Descriptions.Item label="Date">{invoice.date}</Descriptions.Item>
                    <Descriptions.Item label="Invoice Number">{invoice.number}</Descriptions.Item>
                    <Descriptions.Item label="Customer ID">{customer.id ?? '-'}</Descriptions.Item>
                    <Descriptions.Item label="Customer Name">{customer.name ?? '-'}</Descriptions.Item>
                    <Descriptions.Item label="Folio">{folio.folio_no}</Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag color={invoice.status_color}>{invoice.status_label}</Tag>
                        <span style={{ color: token.colorTextSecondary }}>Revision {invoice.revision}</span>
                    </Descriptions.Item>
                    {isReleased && (
                        <Descriptions.Item label="Released by" span={2}>
                            {invoice.released_by ?? '-'} on {invoice.released_at ?? '-'}
                        </Descriptions.Item>
                    )}
                </Descriptions>

                <div style={{ marginBottom: 24 }}>
                    <h3>{terms.title}</h3>
                    <Table
                        rowKey="account_no"
                        size="small"
                        pagination={false}
                        dataSource={terms.bank_accounts}
                        columns={[
                            { title: 'Bank', dataIndex: 'bank_name' },
                            { title: 'Account No.', dataIndex: 'account_no' },
                            { title: 'Account Name', dataIndex: 'account_name' },
                        ]}
                    />
                    <ol style={{ marginTop: 12, color: token.colorTextSecondary }}>
                        {terms.items.map((term) => (
                            <li key={term}>{term}</li>
                        ))}
                    </ol>
                </div>

                <Table<GuestInvoiceLine>
                    rowKey="id"
                    size="small"
                    pagination={false}
                    dataSource={invoice.lines}
                    locale={{ emptyText: 'No charges recorded yet' }}
                    columns={[
                        { title: 'Rincian', dataIndex: 'description' },
                        { title: 'QTY', dataIndex: 'quantity', align: 'right', render: (value: number) => Number(value) },
                        { title: 'Ns', dataIndex: 'nights', align: 'right', render: (value: number | null) => value ?? '' },
                        { title: 'Harga', dataIndex: 'unit_price', align: 'right', render: formatIdr },
                        ...(show_tax_columns
                            ? [
                                  { title: 'SC', dataIndex: 'service_charge_amount', align: 'right' as const, render: formatIdr },
                                  { title: 'Tax', dataIndex: 'tax_amount', align: 'right' as const, render: formatIdr },
                              ]
                            : []),
                        { title: 'Total', dataIndex: 'line_total', align: 'right', render: formatIdr },
                    ]}
                />

                <div style={{ textAlign: 'right', marginTop: 16 }}>
                    <div style={{ fontSize: token.fontSizeHeading5, fontWeight: 600 }}>
                        Grand Total: {formatIdr(invoice.total)}
                    </div>
                </div>

                <Space size={64} style={{ marginTop: 48 }} align="end" wrap>
                    {[
                        { label: signatures.prepared_by, name: invoice.prepared_by },
                        { label: signatures.approved_by, name: invoice.approved_by },
                        { label: signatures.received_by, name: customer.name },
                    ].map((signature) => (
                        <div key={signature.label}>
                            {signature.label},
                            <div
                                style={{
                                    marginTop: 48,
                                    paddingTop: 4,
                                    minWidth: 180,
                                    textAlign: 'center',
                                    borderTop: `1px solid ${token.colorBorder}`,
                                }}
                            >
                                {signature.name ?? '-'}
                            </div>
                        </div>
                    ))}
                </Space>
            </div>
        </AuthenticatedLayout>
    );
}
