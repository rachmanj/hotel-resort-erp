import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { DatePicker, Select, Statistic } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface GlRow {
    id: number;
    transaction_date: string;
    account_code: string;
    account_name: string;
    description: string;
    debit: number;
    credit: number;
    reference_number: string | null;
    source_type: string;
    source_id: number;
}

interface GeneralLedgerIndexProps {
    entries: { data: GlRow[] };
    accounts: Array<{ id: number; account_code: string; name: string }>;
    filters: Record<string, string>;
    accountBalance: number | null;
}

export default function GeneralLedgerIndex({ entries, accounts, filters, accountBalance }: GeneralLedgerIndexProps) {
    const columns: ProColumns<GlRow>[] = [
        { title: 'Date', dataIndex: 'transaction_date', width: 110 },
        { title: 'Code', dataIndex: 'account_code', width: 90 },
        { title: 'Account', dataIndex: 'account_name' },
        { title: 'Description', dataIndex: 'description' },
        { title: 'Debit', dataIndex: 'debit', render: (v) => Number(v).toLocaleString('id-ID'), width: 120 },
        { title: 'Credit', dataIndex: 'credit', render: (v) => Number(v).toLocaleString('id-ID'), width: 120 },
        { title: 'Ref', dataIndex: 'reference_number', width: 120 },
    ];

    const accountOptions = accounts.map((a) => ({ value: a.id, label: `${a.account_code} · ${a.name}` }));

    return (
        <AuthenticatedLayout title="General Ledger">
            <Head title="General Ledger" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8, alignItems: 'center' }}>
                <Select
                    allowClear
                    showSearch
                    placeholder="Filter by account"
                    style={{ width: 280 }}
                    value={filters.account_id ? Number(filters.account_id) : undefined}
                    options={accountOptions}
                    onChange={(v) => router.get('/accounting/general-ledger', { ...filters, account_id: v }, { preserveState: true })}
                />
                <DatePicker.RangePicker
                    value={filters.from && filters.to ? [dayjs(filters.from), dayjs(filters.to)] : undefined}
                    onChange={(dates) => router.get('/accounting/general-ledger', {
                        ...filters,
                        from: dates?.[0]?.format('YYYY-MM-DD'),
                        to: dates?.[1]?.format('YYYY-MM-DD'),
                    }, { preserveState: true })}
                />
                {accountBalance !== null && (
                    <Statistic title="Account Balance" value={accountBalance} precision={0} prefix="Rp" />
                )}
            </div>
            <ProTable rowKey="id" search={false} options={false} pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }} dataSource={entries.data} columns={columns} scroll={{ x: 'max-content' }} />
        </AuthenticatedLayout>
    );
}
