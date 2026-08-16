import { Head, useForm } from '@inertiajs/react';
import { Button, DatePicker, Form, Input, InputNumber, Select, Space, Table } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface AccountOption {
    id: number;
    account_code: string;
    name: string;
}

interface LineRow {
    key: number;
    chart_of_account_id: number | null;
    description: string;
    debit: number;
    credit: number;
}

interface JournalEntryCreateProps {
    accounts: AccountOption[];
}

export default function JournalEntryCreate({ accounts }: JournalEntryCreateProps) {
    const [lines, setLines] = useState<LineRow[]>([
        { key: 1, chart_of_account_id: null, description: '', debit: 0, credit: 0 },
        { key: 2, chart_of_account_id: null, description: '', debit: 0, credit: 0 },
    ]);

    const form = useForm({
        entry_date: dayjs().format('YYYY-MM-DD'),
        description: '',
        lines: [] as Array<{ chart_of_account_id: number; description: string; debit: number; credit: number }>,
    });

    const accountOptions = accounts.map((a) => ({
        value: a.id,
        label: `${a.account_code} · ${a.name}`,
    }));

    const totalDebit = lines.reduce((sum, l) => sum + (l.debit || 0), 0);
    const totalCredit = lines.reduce((sum, l) => sum + (l.credit || 0), 0);

    const submit = () => {
        form.setData('lines', lines
            .filter((l) => l.chart_of_account_id !== null)
            .map((l) => ({
                chart_of_account_id: l.chart_of_account_id as number,
                description: l.description,
                debit: l.debit,
                credit: l.credit,
            })));
        form.post('/accounting/journal-entries');
    };

    return (
        <AuthenticatedLayout title="New Journal Entry">
            <Head title="New Journal Entry" />
            <Form layout="vertical" style={{ maxWidth: 900 }}>
                <Form.Item label="Entry Date" required>
                    <DatePicker
                        style={{ width: '100%' }}
                        value={dayjs(form.data.entry_date)}
                        onChange={(d) => form.setData('entry_date', d?.format('YYYY-MM-DD') ?? '')}
                    />
                </Form.Item>
                <Form.Item label="Description" required>
                    <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </Form.Item>

                <Table
                    dataSource={lines}
                    pagination={false}
                    rowKey="key"
                    columns={[
                        {
                            title: 'Account',
                            render: (_, row) => (
                                <Select
                                    showSearch
                                    style={{ width: '100%' }}
                                    placeholder="Select account"
                                    options={accountOptions}
                                    value={row.chart_of_account_id}
                                    onChange={(v) => setLines(lines.map((l) => (l.key === row.key ? { ...l, chart_of_account_id: v } : l)))}
                                />
                            ),
                        },
                        {
                            title: 'Description',
                            render: (_, row) => (
                                <Input
                                    value={row.description}
                                    onChange={(e) => setLines(lines.map((l) => (l.key === row.key ? { ...l, description: e.target.value } : l)))}
                                />
                            ),
                        },
                        {
                            title: 'Debit',
                            width: 130,
                            render: (_, row) => (
                                <InputNumber
                                    min={0}
                                    style={{ width: '100%' }}
                                    value={row.debit}
                                    onChange={(v) => setLines(lines.map((l) => (l.key === row.key ? { ...l, debit: v ?? 0 } : l)))}
                                />
                            ),
                        },
                        {
                            title: 'Credit',
                            width: 130,
                            render: (_, row) => (
                                <InputNumber
                                    min={0}
                                    style={{ width: '100%' }}
                                    value={row.credit}
                                    onChange={(v) => setLines(lines.map((l) => (l.key === row.key ? { ...l, credit: v ?? 0 } : l)))}
                                />
                            ),
                        },
                    ]}
                    footer={() => (
                        <Space>
                            <span>Total Debit: {totalDebit.toLocaleString('id-ID')}</span>
                            <span>Total Credit: {totalCredit.toLocaleString('id-ID')}</span>
                            <Button onClick={() => setLines([...lines, { key: Date.now(), chart_of_account_id: null, description: '', debit: 0, credit: 0 }])}>
                                Add Line
                            </Button>
                        </Space>
                    )}
                />

                <Button type="primary" onClick={submit} loading={form.processing} style={{ marginTop: 16 }}>
                    Save Draft
                </Button>
            </Form>
        </AuthenticatedLayout>
    );
}
