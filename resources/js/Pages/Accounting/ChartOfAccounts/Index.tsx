import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Input, Select, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface AccountRow {
    id: number;
    parent_id: number | null;
    account_code: string;
    name: string;
    account_type: string;
    account_type_label: string;
    normal_balance: string;
    is_postable: boolean;
    is_active: boolean;
}

interface ChartOfAccountsIndexProps {
    accounts: AccountRow[];
    filters: Record<string, string>;
    accountTypeOptions: Array<{ value: string; label: string }>;
}

export default function ChartOfAccountsIndex({ accounts, filters, accountTypeOptions }: ChartOfAccountsIndexProps) {
    const columns: ProColumns<AccountRow>[] = [
        { title: 'Code', dataIndex: 'account_code', width: 120 },
        { title: 'Name', dataIndex: 'name' },
        { title: 'Type', dataIndex: 'account_type_label', width: 100 },
        { title: 'Normal Balance', dataIndex: 'normal_balance', width: 120 },
        {
            title: 'Postable',
            width: 90,
            render: (_, r) => (r.is_postable ? <Tag color="green">Yes</Tag> : <Tag>No</Tag>),
        },
        {
            title: 'Status',
            width: 90,
            render: (_, r) => (r.is_active ? <Tag color="blue">Active</Tag> : <Tag color="red">Inactive</Tag>),
        },
    ];

    return (
        <AuthenticatedLayout title="Chart of Accounts">
            <Head title="Chart of Accounts" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8 }}>
                <Input.Search
                    placeholder="Search code or name"
                    defaultValue={filters.search}
                    onSearch={(v) => router.get('/accounting/chart-of-accounts', { ...filters, search: v }, { preserveState: true })}
                    style={{ width: 240 }}
                />
                <Select
                    allowClear
                    placeholder="Account type"
                    style={{ width: 160 }}
                    value={filters.account_type}
                    options={accountTypeOptions}
                    onChange={(v) => router.get('/accounting/chart-of-accounts', { ...filters, account_type: v }, { preserveState: true })}
                />
            </div>
            <ProTable rowKey="id" search={false} options={false} dataSource={accounts} columns={columns} pagination={false} scroll={{ x: 'max-content' }} />
        </AuthenticatedLayout>
    );
}
