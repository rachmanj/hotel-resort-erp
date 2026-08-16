import { Head, Link, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, DatePicker, Form, InputNumber, Modal, Select } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface ReconciliationRow {
    id: number;
    bank_name: string | null;
    account_no: string | null;
    period_end_date: string;
    statement_balance: number;
    book_balance: number;
    variance: number;
    status: string;
    status_label: string;
    reconciled_by: string | null;
}

interface BankReconciliationIndexProps {
    reconciliations: { data: ReconciliationRow[] };
    bankAccounts: Array<{ id: number; bank_name: string; account_no: string }>;
}

const formatIdr = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

export default function BankReconciliationIndex({ reconciliations, bankAccounts }: BankReconciliationIndexProps) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        bank_account_id: null as number | null,
        period_end_date: dayjs().endOf('month').format('YYYY-MM-DD'),
        statement_balance: 0,
    });

    const columns: ProColumns<ReconciliationRow>[] = [
        { title: 'Bank', dataIndex: 'bank_name' },
        { title: 'Account', dataIndex: 'account_no', width: 140 },
        { title: 'Period End', dataIndex: 'period_end_date', width: 120 },
        { title: 'Statement', render: (_, r) => formatIdr(r.statement_balance), width: 140 },
        { title: 'Book', render: (_, r) => formatIdr(r.book_balance), width: 140 },
        { title: 'Variance', render: (_, r) => formatIdr(r.variance), width: 120 },
        { title: 'Status', dataIndex: 'status_label', width: 120 },
        {
            title: 'Action',
            width: 100,
            render: (_, r) => <Link href={`/accounting/bank-reconciliation/${r.id}/reconcile`}>Open</Link>,
        },
    ];

    const startReconciliation = () => {
        post('/accounting/bank-reconciliation', {
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <AuthenticatedLayout title="Bank Reconciliation">
            <Head title="Bank Reconciliation" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8 }}>
                <Link href="/accounting/bank-accounts">
                    <Button>Manage Bank Accounts</Button>
                </Link>
                <Button type="primary" onClick={() => setOpen(true)}>
                    New Reconciliation
                </Button>
            </div>
            <ProTable rowKey="id" search={false} options={false} pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }} dataSource={reconciliations.data} columns={columns} scroll={{ x: 'max-content' }} />
            <Modal
                title="Start Reconciliation"
                open={open}
                onCancel={() => setOpen(false)}
                onOk={startReconciliation}
                confirmLoading={processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Bank Account" required>
                        <Select
                            value={data.bank_account_id}
                            options={bankAccounts.map((a) => ({
                                value: a.id,
                                label: `${a.bank_name} - ${a.account_no}`,
                            }))}
                            onChange={(v) => setData('bank_account_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Period End Date">
                        <DatePicker
                            style={{ width: '100%' }}
                            value={dayjs(data.period_end_date)}
                            onChange={(d) => setData('period_end_date', d?.format('YYYY-MM-DD') ?? '')}
                        />
                    </Form.Item>
                    <Form.Item label="Statement Balance">
                        <InputNumber
                            style={{ width: '100%' }}
                            value={data.statement_balance}
                            onChange={(v) => setData('statement_balance', v ?? 0)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
