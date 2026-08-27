import { Head, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Card, Col, DatePicker, Form, Input, InputNumber, Modal, Row, Select, Space, Typography } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { newIdempotencyKey } from '@/lib/idempotency';

dayjs.locale('en');

interface GlActivityRow {
    id: number;
    transaction_date: string;
    description: string;
    reference_number: string | null;
    debit: number;
    credit: number;
    source_type: string;
    department_name: string | null;
}

interface PettyCashAccount {
    id: number;
    account_name: string;
    bank_name: string;
    account_no: string;
    gl_account: string | null;
    balance: number;
    recent_activity: GlActivityRow[];
}

interface AccountOption {
    id: number;
    account_code: string;
    name: string;
}

interface DepartmentOption {
    id: number;
    code: string;
    name: string;
}

interface PettyCashIndexProps {
    pettyCashAccounts: PettyCashAccount[];
    bankAccounts: Array<{ id: number; bank_name: string; account_name: string }>;
    expenseAccounts: AccountOption[];
    incomeAccounts: AccountOption[];
    departments: DepartmentOption[];
    directionOptions: Array<{ value: string; label: string }>;
}

const formatIdr = (amount: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);

export default function PettyCashIndex({
    pettyCashAccounts,
    bankAccounts,
    expenseAccounts,
    incomeAccounts,
    departments,
    directionOptions,
}: PettyCashIndexProps) {
    const [cashOpen, setCashOpen] = useState(false);
    const [replenishOpen, setReplenishOpen] = useState(false);
    const [expandedAccount, setExpandedAccount] = useState<PettyCashAccount | null>(null);

    const cashForm = useForm({
        bank_account_id: null as number | null,
        direction: 'out',
        amount: 0,
        transaction_date: dayjs().format('YYYY-MM-DD'),
        department_id: null as number | null,
        description: '',
        chart_of_account_id: null as number | null,
    });

    const replenishForm = useForm({
        from_bank_account_id: null as number | null,
        to_bank_account_id: null as number | null,
        amount: 0,
        transfer_date: dayjs().format('YYYY-MM-DD'),
        description: '',
    });

    const counterpartAccounts = cashForm.data.direction === 'in' ? incomeAccounts : expenseAccounts;

    const activityColumns: ProColumns<GlActivityRow>[] = [
        { title: 'Date', dataIndex: 'transaction_date', width: 110 },
        { title: 'Reference', dataIndex: 'reference_number', width: 140 },
        { title: 'Description', dataIndex: 'description' },
        { title: 'Department', dataIndex: 'department_name', width: 120 },
        {
            title: 'Debit',
            width: 120,
            align: 'right',
            render: (_, r) => (r.debit > 0 ? formatIdr(r.debit) : ''),
        },
        {
            title: 'Credit',
            width: 120,
            align: 'right',
            render: (_, r) => (r.credit > 0 ? formatIdr(r.credit) : ''),
        },
    ];

    const accountColumns: ProColumns<PettyCashAccount>[] = [
        { title: 'Account Name', dataIndex: 'account_name' },
        { title: 'GL Account', dataIndex: 'gl_account' },
        {
            title: 'Balance',
            width: 150,
            align: 'right',
            render: (_, r) => <Typography.Text strong>{formatIdr(r.balance)}</Typography.Text>,
        },
        {
            title: 'Actions',
            width: 120,
            render: (_, r) => (
                <Button size="small" onClick={() => setExpandedAccount(r)}>
                    Activity
                </Button>
            ),
        },
    ];

    return (
        <AuthenticatedLayout title="Petty Cash">
            <Head title="Petty Cash" />
            <Space style={{ marginBottom: 16 }}>
                <Button type="primary" onClick={() => setCashOpen(true)}>
                    Cash In / Out
                </Button>
                <Button onClick={() => setReplenishOpen(true)}>
                    Replenish (Tarik Dana)
                </Button>
            </Space>

            <ProTable
                rowKey="id"
                search={false}
                options={false}
                pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }}
                dataSource={pettyCashAccounts}
                columns={accountColumns}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title="Petty Cash Transaction"
                open={cashOpen}
                onCancel={() => setCashOpen(false)}
                onOk={() =>
                    cashForm.post('/accounting/petty-cash/transactions', {
                        headers: { 'X-Idempotency-Key': newIdempotencyKey() },
                        onSuccess: () => {
                            setCashOpen(false);
                            cashForm.reset();
                        },
                    })
                }
                confirmLoading={cashForm.processing}
                width={560}
            >
                <Form layout="vertical">
                    <Form.Item label="Petty Cash Account" required>
                        <Select
                            placeholder="Select petty cash account"
                            value={cashForm.data.bank_account_id}
                            options={pettyCashAccounts.map((a) => ({
                                value: a.id,
                                label: `${a.account_name} (${a.gl_account})`,
                            }))}
                            onChange={(v) => cashForm.setData('bank_account_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Direction" required>
                        <Select
                            value={cashForm.data.direction}
                            options={directionOptions}
                            onChange={(v) => {
                                cashForm.setData('direction', v);
                                cashForm.setData('chart_of_account_id', null);
                            }}
                        />
                    </Form.Item>
                    <Row gutter={16}>
                        <Col span={12}>
                            <Form.Item label="Amount" required>
                                <InputNumber
                                    style={{ width: '100%' }}
                                    min={0.01}
                                    placeholder="Amount"
                                    value={cashForm.data.amount || null}
                                    onChange={(v) => cashForm.setData('amount', v ?? 0)}
                                />
                            </Form.Item>
                        </Col>
                        <Col span={12}>
                            <Form.Item label="Date" required>
                                <DatePicker
                                    style={{ width: '100%' }}
                                    value={dayjs(cashForm.data.transaction_date)}
                                    onChange={(d) =>
                                        cashForm.setData('transaction_date', d?.format('YYYY-MM-DD') ?? '')
                                    }
                                />
                            </Form.Item>
                        </Col>
                    </Row>
                    <Form.Item label="Counterpart Account" required>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            placeholder="Select expense or income account"
                            value={cashForm.data.chart_of_account_id}
                            options={counterpartAccounts.map((a) => ({
                                value: a.id,
                                label: `${a.account_code} - ${a.name}`,
                            }))}
                            onChange={(v) => cashForm.setData('chart_of_account_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Department">
                        <Select
                            allowClear
                            placeholder="Optional department"
                            value={cashForm.data.department_id}
                            options={departments.map((d) => ({
                                value: d.id,
                                label: `${d.code} - ${d.name}`,
                            }))}
                            onChange={(v) => cashForm.setData('department_id', v ?? null)}
                        />
                    </Form.Item>
                    <Form.Item label="Description" required>
                        <Input.TextArea
                            placeholder="Transaction description"
                            rows={2}
                            value={cashForm.data.description}
                            onChange={(e) => cashForm.setData('description', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title="Replenish Petty Cash"
                open={replenishOpen}
                onCancel={() => setReplenishOpen(false)}
                onOk={() =>
                    replenishForm.post('/accounting/petty-cash/replenish', {
                        headers: { 'X-Idempotency-Key': newIdempotencyKey() },
                        onSuccess: () => {
                            setReplenishOpen(false);
                            replenishForm.reset();
                        },
                    })
                }
                confirmLoading={replenishForm.processing}
                width={560}
            >
                <Form layout="vertical">
                    <Form.Item label="From Bank Account" required>
                        <Select
                            placeholder="Select bank account"
                            value={replenishForm.data.from_bank_account_id}
                            options={bankAccounts.map((a) => ({
                                value: a.id,
                                label: `${a.bank_name} - ${a.account_name}`,
                            }))}
                            onChange={(v) => replenishForm.setData('from_bank_account_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="To Petty Cash Account" required>
                        <Select
                            placeholder="Select petty cash account"
                            value={replenishForm.data.to_bank_account_id}
                            options={pettyCashAccounts.map((a) => ({
                                value: a.id,
                                label: a.account_name,
                            }))}
                            onChange={(v) => replenishForm.setData('to_bank_account_id', v)}
                        />
                    </Form.Item>
                    <Row gutter={16}>
                        <Col span={12}>
                            <Form.Item label="Amount" required>
                                <InputNumber
                                    style={{ width: '100%' }}
                                    min={0.01}
                                    placeholder="Amount"
                                    value={replenishForm.data.amount || null}
                                    onChange={(v) => replenishForm.setData('amount', v ?? 0)}
                                />
                            </Form.Item>
                        </Col>
                        <Col span={12}>
                            <Form.Item label="Date" required>
                                <DatePicker
                                    style={{ width: '100%' }}
                                    value={dayjs(replenishForm.data.transfer_date)}
                                    onChange={(d) =>
                                        replenishForm.setData('transfer_date', d?.format('YYYY-MM-DD') ?? '')
                                    }
                                />
                            </Form.Item>
                        </Col>
                    </Row>
                    <Form.Item label="Description" required>
                        <Input.TextArea
                            placeholder="Replenishment description"
                            rows={2}
                            value={replenishForm.data.description}
                            onChange={(e) => replenishForm.setData('description', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title={expandedAccount ? `Activity — ${expandedAccount.account_name}` : 'Activity'}
                open={expandedAccount !== null}
                onCancel={() => setExpandedAccount(null)}
                footer={null}
                width={900}
            >
                {expandedAccount && (
                    <Card size="small" style={{ marginBottom: 16 }}>
                        <Typography.Text>
                            Balance: <strong>{formatIdr(expandedAccount.balance)}</strong>
                        </Typography.Text>
                    </Card>
                )}
                {expandedAccount && (
                    <ProTable
                        rowKey="id"
                        search={false}
                        options={false}
                        pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }}
                        dataSource={expandedAccount.recent_activity}
                        columns={activityColumns}
                        scroll={{ x: 'max-content' }}
                    />
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
