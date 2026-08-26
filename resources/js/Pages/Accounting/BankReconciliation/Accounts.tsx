import { Head, Link, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal, Select } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface BankAccountRow {
    id: number;
    bank_name: string;
    account_no: string;
    account_name: string;
    type: string;
    type_label: string;
    currency_code: string;
    gl_account: string | null;
    is_active: boolean;
}

interface BankAccountsProps {
    bankAccounts: BankAccountRow[];
    typeOptions: Array<{ value: string; label: string }>;
    glAccounts: Array<{ id: number; account_code: string; name: string }>;
}

export default function BankAccounts({ bankAccounts, typeOptions, glAccounts }: BankAccountsProps) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        bank_name: '',
        account_no: '',
        account_name: '',
        type: 'bank',
        chart_of_account_id: null as number | null,
        currency_code: 'IDR',
    });

    const columns: ProColumns<BankAccountRow>[] = [
        { title: 'Type', dataIndex: 'type_label', width: 100 },
        { title: 'Bank', dataIndex: 'bank_name' },
        { title: 'Account No', dataIndex: 'account_no', width: 140 },
        { title: 'Account Name', dataIndex: 'account_name' },
        { title: 'Currency', dataIndex: 'currency_code', width: 80 },
        { title: 'GL Account', dataIndex: 'gl_account' },
        { title: 'Active', render: (_, r) => (r.is_active ? 'Yes' : 'No'), width: 80 },
    ];

    const submit = () => {
        post('/accounting/bank-accounts', {
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <AuthenticatedLayout title="Bank Accounts">
            <Head title="Bank Accounts" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8 }}>
                <Link href="/accounting/bank-reconciliation">
                    <Button>← Reconciliations</Button>
                </Link>
                <Button type="primary" onClick={() => setOpen(true)}>
                    Add Bank Account
                </Button>
            </div>
            <ProTable
                rowKey="id"
                search={false}
                options={false}
                pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }}
                dataSource={bankAccounts}
                columns={columns}
                scroll={{ x: 'max-content' }}
            />
            <Modal title="New Bank Account" open={open} onCancel={() => setOpen(false)} onOk={submit} confirmLoading={processing}>
                <Form layout="vertical">
                    <Form.Item label="Type" required>
                        <Select
                            value={data.type}
                            options={typeOptions}
                            onChange={(v) => setData('type', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Bank Name" required>
                        <Input
                            placeholder="Bank or fund name"
                            value={data.bank_name}
                            onChange={(e) => setData('bank_name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Account No" required>
                        <Input
                            placeholder="Account number"
                            value={data.account_no}
                            onChange={(e) => setData('account_no', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Account Name" required>
                        <Input
                            placeholder="Account display name"
                            value={data.account_name}
                            onChange={(e) => setData('account_name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="GL Account" required>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            placeholder="Select GL account"
                            value={data.chart_of_account_id}
                            options={glAccounts.map((a) => ({
                                value: a.id,
                                label: `${a.account_code} - ${a.name}`,
                            }))}
                            onChange={(v) => setData('chart_of_account_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Currency">
                        <Select
                            value={data.currency_code}
                            options={[
                                { value: 'IDR', label: 'IDR' },
                                { value: 'USD', label: 'USD' },
                            ]}
                            onChange={(v) => setData('currency_code', v)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
