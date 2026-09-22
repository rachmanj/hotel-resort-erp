import { Head, Link, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    Col,
    DatePicker,
    Descriptions,
    Form,
    Input,
    InputNumber,
    Popconfirm,
    Row,
    Select,
    Space,
    Statistic,
    Table,
    Tag,
    Upload,
    theme,
} from 'antd';
import { UploadOutlined } from '@ant-design/icons';
import dayjs, { type Dayjs } from 'dayjs';
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

interface ProformaPayment {
    id: number;
    amount: number;
    method: string;
    method_label: string;
    received_from: string;
    reference_no?: string | null;
    paid_at: string;
    status: string;
    status_label: string;
    status_color: string;
    recorded_by?: string | null;
    verified_by?: string | null;
    verified_at?: string | null;
    receipt_number?: string | null;
    proof_url?: string | null;
    notes?: string | null;
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
        received_total: number;
        outstanding_total: number;
        lines: ProformaLine[];
    };
    payment_details: {
        bank_accounts: Array<{ bank_name: string; account_no: string; account_name: string }>;
        terms: string[];
    };
    payments: ProformaPayment[];
    canRelease: boolean;
    canRecordPayment: boolean;
    canVerifyPayment: boolean;
}

const formatIdr = (value: number | string) => `Rp ${Number(value).toLocaleString('id-ID')}`;

export default function Proforma({
    company,
    reservation,
    customer,
    proforma,
    payment_details,
    payments,
    canRelease,
    canRecordPayment,
    canVerifyPayment,
}: ProformaProps) {
    const { token } = theme.useToken();
    const releaseForm = useForm({});
    const paymentForm = useForm({});
    const verifyForm = useForm({});
    const [recordForm] = Form.useForm();

    const submitRelease = () => {
        releaseForm.post(`/reservations/${reservation.id}/proforma/release`, {
            preserveScroll: true,
        });
    };

    const submitPayment = (values: {
        amount: number;
        method: string;
        received_from: string;
        reference_no?: string;
        paid_at: Dayjs;
        notes?: string;
        proof?: Array<{ originFileObj?: File }>;
    }) => {
        paymentForm.transform(() => ({
            amount: values.amount,
            method: values.method,
            received_from: values.received_from,
            reference_no: values.reference_no ?? '',
            paid_at: values.paid_at.format('YYYY-MM-DD'),
            notes: values.notes ?? '',
            proof: values.proof?.[0]?.originFileObj ?? null,
        }));

        paymentForm.post(`/reservations/${reservation.id}/proforma/payments`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => recordForm.resetFields(),
        });
    };

    const verifyPayment = (paymentId: number) => {
        verifyForm.post(`/proforma-payments/${paymentId}/verify`, { preserveScroll: true });
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

                <Card title="Payments" size="small" style={{ marginTop: 32 }}>
                    <Row gutter={16} style={{ marginBottom: 16 }}>
                        <Col span={8}>
                            <Statistic title="Purchase Total" value={proforma.total} formatter={(value) => formatIdr(value as number)} />
                        </Col>
                        <Col span={8}>
                            <Statistic
                                title="Received (verified)"
                                value={proforma.received_total}
                                valueStyle={{ color: token.colorSuccess }}
                                formatter={(value) => formatIdr(value as number)}
                            />
                        </Col>
                        <Col span={8}>
                            <Statistic
                                title="Outstanding"
                                value={proforma.outstanding_total}
                                valueStyle={{ color: proforma.outstanding_total > 0 ? token.colorWarning : token.colorSuccess }}
                                formatter={(value) => formatIdr(value as number)}
                            />
                        </Col>
                    </Row>

                    <Table<ProformaPayment>
                        rowKey="id"
                        size="small"
                        pagination={false}
                        dataSource={payments}
                        locale={{ emptyText: 'No payment recorded yet' }}
                        columns={[
                            { title: 'Paid Date', dataIndex: 'paid_at' },
                            { title: 'Received From', dataIndex: 'received_from' },
                            { title: 'Method', dataIndex: 'method_label' },
                            { title: 'Reference', dataIndex: 'reference_no', render: (value: string | null) => value ?? '-' },
                            { title: 'Amount', dataIndex: 'amount', align: 'right', render: formatIdr },
                            {
                                title: 'Status',
                                dataIndex: 'status',
                                render: (_: string, payment) => (
                                    <Tag color={payment.status_color}>{payment.status_label}</Tag>
                                ),
                            },
                            { title: 'Receipt No.', dataIndex: 'receipt_number', render: (value: string | null) => value ?? '-' },
                            {
                                title: 'Actions',
                                key: 'actions',
                                render: (_: unknown, payment) => (
                                    <Space size="small" wrap>
                                        {payment.proof_url && (
                                            <a href={payment.proof_url} target="_blank" rel="noreferrer">
                                                Proof
                                            </a>
                                        )}
                                        {payment.status === 'verified' && (
                                            <a href={`/proforma-payments/${payment.id}/receipt`}>Receipt PDF</a>
                                        )}
                                        {canVerifyPayment && payment.status !== 'verified' && (
                                            <Popconfirm
                                                title="Verify payment"
                                                description="Confirm the money reached the bank account. A payment receipt will be issued."
                                                okText="Verify"
                                                cancelText="Cancel"
                                                onConfirm={() => verifyPayment(payment.id)}
                                            >
                                                <Button size="small" type="primary" loading={verifyForm.processing}>
                                                    Verify
                                                </Button>
                                            </Popconfirm>
                                        )}
                                    </Space>
                                ),
                            },
                        ]}
                    />

                    {canRecordPayment && (
                        <Form
                            form={recordForm}
                            layout="vertical"
                            style={{ marginTop: 24 }}
                            initialValues={{
                                method: 'bank_transfer',
                                received_from: customer.name ?? '',
                                paid_at: dayjs(),
                            }}
                            onFinish={submitPayment}
                        >
                            <Row gutter={16}>
                                <Col xs={24} md={8}>
                                    <Form.Item
                                        name="amount"
                                        label="Amount"
                                        rules={[{ required: true, message: 'Amount is required' }]}
                                        validateStatus={paymentForm.errors.amount ? 'error' : undefined}
                                        help={paymentForm.errors.amount}
                                    >
                                        <InputNumber
                                            style={{ width: '100%' }}
                                            min={1}
                                            addonBefore="Rp"
                                            formatter={(value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, '.')}
                                            parser={(value) => Number(`${value}`.replace(/\./g, ''))}
                                        />
                                    </Form.Item>
                                </Col>
                                <Col xs={24} md={8}>
                                    <Form.Item
                                        name="method"
                                        label="Method"
                                        rules={[{ required: true }]}
                                        validateStatus={paymentForm.errors.method ? 'error' : undefined}
                                        help={paymentForm.errors.method}
                                    >
                                        <Select
                                            options={[
                                                { value: 'bank_transfer', label: 'Transfer Bank' },
                                                { value: 'cash', label: 'Cash' },
                                            ]}
                                        />
                                    </Form.Item>
                                </Col>
                                <Col xs={24} md={8}>
                                    <Form.Item
                                        name="paid_at"
                                        label="Paid Date"
                                        rules={[{ required: true }]}
                                        validateStatus={paymentForm.errors.paid_at ? 'error' : undefined}
                                        help={paymentForm.errors.paid_at}
                                    >
                                        <DatePicker style={{ width: '100%' }} format="DD MMM YYYY" />
                                    </Form.Item>
                                </Col>
                                <Col xs={24} md={8}>
                                    <Form.Item
                                        name="received_from"
                                        label="Received From"
                                        rules={[{ required: true, message: 'Received from is required' }]}
                                        validateStatus={paymentForm.errors.received_from ? 'error' : undefined}
                                        help={paymentForm.errors.received_from}
                                    >
                                        <Input />
                                    </Form.Item>
                                </Col>
                                <Col xs={24} md={8}>
                                    <Form.Item
                                        name="reference_no"
                                        label="Reference Number"
                                        validateStatus={paymentForm.errors.reference_no ? 'error' : undefined}
                                        help={paymentForm.errors.reference_no}
                                    >
                                        <Input placeholder="Transfer reference / slip number" />
                                    </Form.Item>
                                </Col>
                                <Col xs={24} md={8}>
                                    <Form.Item
                                        name="proof"
                                        label="Proof of Payment"
                                        valuePropName="fileList"
                                        getValueFromEvent={(event) => (Array.isArray(event) ? event : event?.fileList)}
                                        validateStatus={paymentForm.errors.proof ? 'error' : undefined}
                                        help={paymentForm.errors.proof}
                                    >
                                        <Upload beforeUpload={() => false} maxCount={1} accept=".jpg,.jpeg,.png,.pdf">
                                            <Button icon={<UploadOutlined />}>Select file</Button>
                                        </Upload>
                                    </Form.Item>
                                </Col>
                                <Col span={24}>
                                    <Form.Item
                                        name="notes"
                                        label="Notes"
                                        validateStatus={paymentForm.errors.notes ? 'error' : undefined}
                                        help={paymentForm.errors.notes}
                                    >
                                        <Input.TextArea rows={2} placeholder="Shown as In Payment Of on the receipt" />
                                    </Form.Item>
                                </Col>
                            </Row>
                            <Button type="primary" htmlType="submit" loading={paymentForm.processing}>
                                Record Payment
                            </Button>
                        </Form>
                    )}
                </Card>

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
