import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Card, DatePicker, Form, Input, InputNumber, Select, Space, Table } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface LineRow {
    key: string;
    chart_of_account_id: number | null;
    department_id: number | null;
    description: string;
    quantity: number;
    unit_cost: number;
}

interface PayablesCreateProps {
    suppliers: Array<{ id: number; name: string }>;
    purchaseOrders: Array<{ id: number; po_no: string; supplier_id: number }>;
    accounts: Array<{ id: number; account_code: string; name: string }>;
    departments: Array<{ id: number; code: string; name: string }>;
}

export default function PayablesCreate({ suppliers, purchaseOrders, accounts, departments }: PayablesCreateProps) {
    const [lines, setLines] = useState<LineRow[]>([
        { key: '1', chart_of_account_id: null, department_id: null, description: '', quantity: 1, unit_cost: 0 },
    ]);

    const { data, setData, post, processing, errors } = useForm({
        invoice_no: '',
        supplier_id: null as number | null,
        purchase_order_id: null as number | null,
        invoice_date: dayjs().format('YYYY-MM-DD'),
        due_date: dayjs().add(30, 'day').format('YYYY-MM-DD'),
        tax_amount: 0,
        withholding_tax_amount: 0,
        lines: [] as Array<{
            chart_of_account_id: number;
            department_id: number | null;
            description: string;
            quantity: number;
            unit_cost: number;
        }>,
    });

    const subtotal = lines.reduce((sum, l) => sum + l.quantity * l.unit_cost, 0);

    const submit = () => {
        setData(
            'lines',
            lines
                .filter((l) => l.chart_of_account_id && l.description)
                .map((l) => ({
                    chart_of_account_id: l.chart_of_account_id!,
                    department_id: l.department_id,
                    description: l.description,
                    quantity: l.quantity,
                    unit_cost: l.unit_cost,
                })),
        );
        post('/accounting/payables');
    };

    return (
        <AuthenticatedLayout title="New Supplier Invoice">
            <Head title="New Supplier Invoice" />
            <Link href="/accounting/payables" style={{ marginBottom: 16, display: 'inline-block' }}>
                ← Back
            </Link>
            <Card>
                <Form layout="vertical">
                    <Space wrap style={{ width: '100%' }}>
                        <Form.Item label="Invoice No" required style={{ minWidth: 200 }}>
                            <Input value={data.invoice_no} onChange={(e) => setData('invoice_no', e.target.value)} />
                        </Form.Item>
                        <Form.Item label="Supplier" required style={{ minWidth: 220 }}>
                            <Select
                                showSearch
                                optionFilterProp="label"
                                value={data.supplier_id}
                                options={suppliers.map((s) => ({ value: s.id, label: s.name }))}
                                onChange={(v) => setData('supplier_id', v)}
                            />
                        </Form.Item>
                        <Form.Item label="PO (optional)" style={{ minWidth: 200 }}>
                            <Select
                                allowClear
                                value={data.purchase_order_id}
                                options={purchaseOrders.map((po) => ({ value: po.id, label: po.po_no }))}
                                onChange={(v) => setData('purchase_order_id', v ?? null)}
                            />
                        </Form.Item>
                    </Space>
                    <Space wrap>
                        <Form.Item label="Invoice Date">
                            <DatePicker
                                value={dayjs(data.invoice_date)}
                                onChange={(d) => setData('invoice_date', d?.format('YYYY-MM-DD') ?? '')}
                            />
                        </Form.Item>
                        <Form.Item label="Due Date">
                            <DatePicker
                                value={dayjs(data.due_date)}
                                onChange={(d) => setData('due_date', d?.format('YYYY-MM-DD') ?? '')}
                            />
                        </Form.Item>
                        <Form.Item label="PPN Amount">
                            <InputNumber
                                min={0}
                                value={data.tax_amount}
                                onChange={(v) => setData('tax_amount', v ?? 0)}
                            />
                        </Form.Item>
                        <Form.Item label="PPh 23 Withholding">
                            <InputNumber
                                min={0}
                                value={data.withholding_tax_amount}
                                onChange={(v) => setData('withholding_tax_amount', v ?? 0)}
                            />
                        </Form.Item>
                    </Space>
                </Form>
                <Table
                    rowKey="key"
                    dataSource={lines}
                    pagination={false}
                    columns={[
                        {
                            title: 'Account',
                            render: (_, r, i) => (
                                <Select
                                    style={{ width: 260 }}
                                    showSearch
                                    optionFilterProp="label"
                                    value={r.chart_of_account_id}
                                    options={accounts.map((a) => ({
                                        value: a.id,
                                        label: `${a.account_code} - ${a.name}`,
                                    }))}
                                    onChange={(v) => {
                                        const next = [...lines];
                                        next[i].chart_of_account_id = v;
                                        setLines(next);
                                    }}
                                />
                            ),
                        },
                        {
                            title: 'Department',
                            width: 180,
                            render: (_, r, i) => (
                                <Select
                                    allowClear
                                    showSearch
                                    style={{ width: 170 }}
                                    placeholder="Optional"
                                    value={r.department_id}
                                    options={departments.map((d) => ({ value: d.id, label: d.name }))}
                                    onChange={(v) => {
                                        const next = [...lines];
                                        next[i].department_id = v ?? null;
                                        setLines(next);
                                    }}
                                />
                            ),
                        },
                        {
                            title: 'Description',
                            render: (_, r, i) => (
                                <Input
                                    value={r.description}
                                    onChange={(e) => {
                                        const next = [...lines];
                                        next[i].description = e.target.value;
                                        setLines(next);
                                    }}
                                />
                            ),
                        },
                        {
                            title: 'Qty',
                            width: 90,
                            render: (_, r, i) => (
                                <InputNumber
                                    min={0.01}
                                    value={r.quantity}
                                    onChange={(v) => {
                                        const next = [...lines];
                                        next[i].quantity = v ?? 1;
                                        setLines(next);
                                    }}
                                />
                            ),
                        },
                        {
                            title: 'Unit Cost',
                            width: 130,
                            render: (_, r, i) => (
                                <InputNumber
                                    min={0}
                                    value={r.unit_cost}
                                    onChange={(v) => {
                                        const next = [...lines];
                                        next[i].unit_cost = v ?? 0;
                                        setLines(next);
                                    }}
                                />
                            ),
                        },
                    ]}
                    footer={() => (
                        <Button
                            type="dashed"
                            onClick={() =>
                                setLines([
                                    ...lines,
                                    {
                                        key: String(Date.now()),
                                        chart_of_account_id: null,
                                        department_id: null,
                                        description: '',
                                        quantity: 1,
                                        unit_cost: 0,
                                    },
                                ])
                            }
                        >
                            Add Line
                        </Button>
                    )}
                />
                <div style={{ marginTop: 16, textAlign: 'right' }}>
                    <p>Subtotal: Rp {subtotal.toLocaleString('id-ID')}</p>
                    <p>
                        Total: Rp{' '}
                        {(subtotal + data.tax_amount - data.withholding_tax_amount).toLocaleString('id-ID')}
                    </p>
                    {errors.lines && <p style={{ color: 'red' }}>{errors.lines}</p>}
                    <Button type="primary" loading={processing} onClick={submit}>
                        Save Invoice
                    </Button>
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
