import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Descriptions, Popconfirm, Space, Table, Tag, theme } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface ProformaLine {
    id: number;
    description: string;
    note?: string | null;
    unit_price: number;
    quantity: number;
    nights: number;
    stay_price: number;
    amount: number;
}

interface ProformaProps {
    company: { name: string; title: string };
    reservation: { id: number; reservation_code: string };
    customer: { id?: number | null; name?: string | null; contact?: string | null };
    proforma: {
        id: number;
        number: string;
        status: string;
        status_label: string;
        status_color: string;
        revision: number;
        date: string;
        date_of_stay: string;
        nights: number;
        issued_at?: string | null;
        released_at?: string | null;
        released_by?: string | null;
        prepared_by?: string | null;
        notes?: string | null;
        subtotal: number;
        total: number;
        lines: ProformaLine[];
    };
    payment_details: {
        bank_accounts: Array<{ bank_name: string; account_no: string; account_name: string }>;
        terms: string[];
    };
    canRelease: boolean;
}

const formatIdr = (value: number | string) => `Rp ${Number(value).toLocaleString('id-ID')}`;

export default function Proforma({
    company,
    reservation,
    customer,
    proforma,
    payment_details,
    canRelease,
}: ProformaProps) {
    const { token } = theme.useToken();
    const releaseForm = useForm({});

    const submitRelease = () => {
        releaseForm.post(`/reservations/${reservation.id}/proforma/release`, {
            preserveScroll: true,
        });
    };

    const isReleased = proforma.status === 'released';

    return (
        <AuthenticatedLayout title={`Proforma Invoice ${proforma.number}`}>
            <Head title={`Proforma Invoice ${proforma.number}`} />

            <Space style={{ marginBottom: 16 }} wrap>
                <Link href={`/reservations/${reservation.id}`}>
                    <Button>Back to reservation</Button>
                </Link>
                <a href={`/reservations/${reservation.id}/proforma/download`}>
                    <Button>Download PDF</Button>
                </a>
                {canRelease && !isReleased && (
                    <Popconfirm
                        title="Release proforma invoice"
                        description="Once released the document can no longer be rewritten. Later changes create a new revision."
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

            <div style={{ maxWidth: 900 }}>
                <div style={{ textAlign: 'center', marginBottom: 24 }}>
                    <div style={{ fontSize: token.fontSizeHeading3, fontWeight: 600, letterSpacing: 1 }}>
                        {company.name}
                    </div>
                    <div style={{ fontSize: token.fontSizeHeading5, color: token.colorTextSecondary }}>
                        {company.title}
                    </div>
                </div>

                <Descriptions bordered column={2} size="small" style={{ marginBottom: 24 }}>
                    <Descriptions.Item label="Customer ID">{customer.id ?? '-'}</Descriptions.Item>
                    <Descriptions.Item label="No.">{proforma.number}</Descriptions.Item>
                    <Descriptions.Item label="Customer Name">{customer.name ?? '-'}</Descriptions.Item>
                    <Descriptions.Item label="Date">{proforma.date}</Descriptions.Item>
                    <Descriptions.Item label="Contact">{customer.contact ?? '-'}</Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag color={proforma.status_color}>{proforma.status_label}</Tag>
                        <span style={{ color: token.colorTextSecondary }}>Revision {proforma.revision}</span>
                    </Descriptions.Item>
                    <Descriptions.Item label="Date of Stay">
                        {proforma.date_of_stay} ({proforma.nights} night{proforma.nights > 1 ? 's' : ''})
                    </Descriptions.Item>
                    <Descriptions.Item label="Reservation">{reservation.reservation_code}</Descriptions.Item>
                    {isReleased && (
                        <Descriptions.Item label="Released by" span={2}>
                            {proforma.released_by ?? '-'} on {proforma.released_at ?? '-'}
                        </Descriptions.Item>
                    )}
                </Descriptions>

                <Table<ProformaLine>
                    rowKey="id"
                    size="small"
                    pagination={false}
                    dataSource={proforma.lines}
                    expandable={{
                        expandedRowRender: (line) => (
                            <span style={{ fontStyle: 'italic', color: token.colorTextSecondary }}>{line.note}</span>
                        ),
                        rowExpandable: (line) => Boolean(line.note),
                        defaultExpandAllRows: true,
                        showExpandColumn: false,
                    }}
                    columns={[
                        { title: 'Description', dataIndex: 'description' },
                        { title: 'Rate', dataIndex: 'unit_price', align: 'right', render: formatIdr },
                        { title: 'Qty', dataIndex: 'quantity', align: 'right' },
                        { title: 'Ns', dataIndex: 'nights', align: 'right' },
                        { title: 'Price', dataIndex: 'stay_price', align: 'right', render: formatIdr },
                        { title: 'Amount', dataIndex: 'amount', align: 'right', render: formatIdr },
                    ]}
                />

                <div style={{ textAlign: 'right', marginTop: 16 }}>
                    <div style={{ color: token.colorTextSecondary }}>Total: {formatIdr(proforma.subtotal)}</div>
                    <div style={{ fontSize: token.fontSizeHeading5, fontWeight: 600 }}>
                        Purchase Total: {formatIdr(proforma.total)}
                    </div>
                </div>

                <div style={{ marginTop: 32 }}>
                    <h3>Payment Details</h3>
                    <Table
                        rowKey="account_no"
                        size="small"
                        pagination={false}
                        dataSource={payment_details.bank_accounts}
                        columns={[
                            { title: 'Bank', dataIndex: 'bank_name' },
                            { title: 'Account No.', dataIndex: 'account_no' },
                            { title: 'Account Name', dataIndex: 'account_name' },
                        ]}
                    />
                    <ul style={{ marginTop: 12, color: token.colorTextSecondary }}>
                        {payment_details.terms.map((term) => (
                            <li key={term}>{term}</li>
                        ))}
                    </ul>
                </div>

                <div style={{ marginTop: 32 }}>
                    Prepared by,
                    <div
                        style={{
                            marginTop: 40,
                            paddingTop: 4,
                            minWidth: 200,
                            display: 'inline-block',
                            borderTop: `1px solid ${token.colorBorder}`,
                        }}
                    >
                        {proforma.prepared_by ?? '-'}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
