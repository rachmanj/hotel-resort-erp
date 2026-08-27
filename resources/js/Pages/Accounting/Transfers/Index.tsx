import { Head, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Col, DatePicker, Form, Input, InputNumber, Modal, Row, Select } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { newIdempotencyKey } from '@/lib/idempotency';

dayjs.locale('en');

interface TransferRow {
    id: number;
    transfer_no: string;
    transfer_date: string;
    description: string;
    amount: number;
    from_account: string | null;
    to_account: string | null;
    from_bank: string | null;
    to_bank: string | null;
    created_by: string | null;
}

interface AccountOption {
    id: number;
    account_code: string;
    name: string;
}

interface TransfersIndexProps {
    transfers: {
        data: TransferRow[];
        links: unknown[];
        meta: unknown;
    };
    cashAccounts: AccountOption[];
    allAccounts: AccountOption[];
}

const formatIdr = (amount: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);

export default function TransfersIndex({ transfers, cashAccounts, allAccounts }: TransfersIndexProps) {
    const [createOpen, setCreateOpen] = useState(false);

    const form = useForm({
        from_chart_of_account_id: cashAccounts[0]?.id ?? null,
        to_chart_of_account_id: null as number | null,
        amount: 0,
        transfer_date: dayjs().format('YYYY-MM-DD'),
        description: '',
    });

    const columns: ProColumns<TransferRow>[] = [
        { title: 'Transfer No', dataIndex: 'transfer_no', width: 150 },
        { title: 'Date', dataIndex: 'transfer_date', width: 110 },
        { title: 'From Account', dataIndex: 'from_account' },
        { title: 'To Account', dataIndex: 'to_account' },
        {
            title: 'Amount',
            width: 140,
            align: 'right',
            render: (_, r) => formatIdr(r.amount),
        },
        { title: 'Description', dataIndex: 'description', ellipsis: true },
        { title: 'Created By', dataIndex: 'created_by', width: 120 },
    ];

    return (
        <AuthenticatedLayout title="Fund Transfers">
            <Head title="Fund Transfers" />
            <div style={{ marginBottom: 16 }}>
                <Button type="primary" onClick={() => setCreateOpen(true)}>
                    New Transfer
                </Button>
            </div>

            <ProTable
                rowKey="id"
                search={{ searchText: 'Search', resetText: 'Reset' }}
                options={false}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                }}
                dataSource={transfers.data}
                columns={columns}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title="New Fund Transfer"
                open={createOpen}
                onCancel={() => setCreateOpen(false)}
                onOk={() =>
                    form.post('/accounting/transfers', {
                        headers: { 'X-Idempotency-Key': newIdempotencyKey() },
                        onSuccess: () => {
                            setCreateOpen(false);
                            form.reset();
                        },
                    })
                }
                confirmLoading={form.processing}
                width={600}
            >
                <Form layout="vertical">
                    <Form.Item label="From Account" required>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            placeholder="Select source account"
                            value={form.data.from_chart_of_account_id}
                            options={allAccounts.map((a) => ({
                                value: a.id,
                                label: `${a.account_code} - ${a.name}`,
                            }))}
                            onChange={(v) => form.setData('from_chart_of_account_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="To Account" required>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            placeholder="Select destination account"
                            value={form.data.to_chart_of_account_id}
                            options={allAccounts.map((a) => ({
                                value: a.id,
                                label: `${a.account_code} - ${a.name}`,
                            }))}
                            onChange={(v) => form.setData('to_chart_of_account_id', v)}
                        />
                    </Form.Item>
                    <Row gutter={16}>
                        <Col span={12}>
                            <Form.Item label="Amount" required>
                                <InputNumber
                                    style={{ width: '100%' }}
                                    min={0.01}
                                    placeholder="Amount"
                                    value={form.data.amount || null}
                                    onChange={(v) => form.setData('amount', v ?? 0)}
                                />
                            </Form.Item>
                        </Col>
                        <Col span={12}>
                            <Form.Item label="Date" required>
                                <DatePicker
                                    style={{ width: '100%' }}
                                    value={dayjs(form.data.transfer_date)}
                                    onChange={(d) =>
                                        form.setData('transfer_date', d?.format('YYYY-MM-DD') ?? '')
                                    }
                                />
                            </Form.Item>
                        </Col>
                    </Row>
                    <Form.Item label="Description" required>
                        <Input.TextArea
                            placeholder="Transfer description"
                            rows={2}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
