import { Head, router } from '@inertiajs/react';
import { DatePicker, Table } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface TrialBalanceRow {
    account_id: number;
    account_code: string;
    account_name: string;
    account_type: string;
    debit: number;
    credit: number;
}

interface TrialBalanceProps {
    rows: TrialBalanceRow[];
    filters: { as_of: string };
    totals: { debit: number; credit: number };
}

export default function TrialBalance({ rows, filters, totals }: TrialBalanceProps) {
    return (
        <AuthenticatedLayout title="Trial Balance">
            <Head title="Trial Balance" />
            <div style={{ marginBottom: 16 }}>
                <DatePicker
                    value={dayjs(filters.as_of)}
                    onChange={(d) => router.get('/accounting/reports/trial-balance', { as_of: d?.format('YYYY-MM-DD') }, { preserveState: true })}
                />
            </div>
            <Table
                dataSource={rows}
                rowKey="account_id"
                pagination={false}
                columns={[
                    { title: 'Code', dataIndex: 'account_code', width: 100 },
                    { title: 'Account', dataIndex: 'account_name' },
                    { title: 'Type', dataIndex: 'account_type', width: 100 },
                    { title: 'Debit', dataIndex: 'debit', render: (v: number) => v.toLocaleString('id-ID'), align: 'right' },
                    { title: 'Credit', dataIndex: 'credit', render: (v: number) => v.toLocaleString('id-ID'), align: 'right' },
                ]}
                summary={() => (
                    <Table.Summary.Row>
                        <Table.Summary.Cell index={0} colSpan={3}><strong>Total</strong></Table.Summary.Cell>
                        <Table.Summary.Cell index={1} align="right"><strong>{totals.debit.toLocaleString('id-ID')}</strong></Table.Summary.Cell>
                        <Table.Summary.Cell index={2} align="right"><strong>{totals.credit.toLocaleString('id-ID')}</strong></Table.Summary.Cell>
                    </Table.Summary.Row>
                )}
            />
        </AuthenticatedLayout>
    );
}
