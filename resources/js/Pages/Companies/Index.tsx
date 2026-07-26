import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Input, Space, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface CompanyRow {
    id: number;
    name: string;
    tax_id?: string;
    phone?: string;
    email?: string;
    credit_limit: string;
    payment_terms_days: number;
    is_active: boolean;
}

interface CompaniesIndexProps {
    companies: Paginated<CompanyRow>;
    filters: { search?: string };
}

const formatIdr = (v: string) => `Rp ${Number(v).toLocaleString('id-ID')}`;

export default function CompaniesIndex({ companies, filters }: CompaniesIndexProps) {
    const columns: ProColumns<CompanyRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'NPWP', dataIndex: 'tax_id', render: (v) => v ?? '—' },
        { title: 'Phone', dataIndex: 'phone', render: (v) => v ?? '—' },
        { title: 'Email', dataIndex: 'email', render: (v) => v ?? '—' },
        { title: 'Credit Limit', dataIndex: 'credit_limit', render: formatIdr },
        { title: 'Terms (days)', dataIndex: 'payment_terms_days' },
        {
            title: 'Status',
            dataIndex: 'is_active',
            render: (v) => (v ? <Tag color="green">Active</Tag> : <Tag>Inactive</Tag>),
        },
        {
            title: 'Actions',
            render: (_, record) => (
                <Link href={`/companies/${record.id}/edit`}>
                    <Button size="small">Edit</Button>
                </Link>
            ),
        },
    ];

    return (
        <AuthenticatedLayout title="Companies">
            <Head title="Companies" />
            <Space style={{ marginBottom: 16 }}>
                <Link href="/companies/create">
                    <Button type="primary">New Company</Button>
                </Link>
                <Input.Search
                    placeholder="Search name, NPWP..."
                    defaultValue={filters.search}
                    onSearch={(value) => router.get('/companies', { search: value || undefined }, { preserveState: true })}
                    style={{ width: 280 }}
                    allowClear
                />
            </Space>
            <ProTable<CompanyRow>
                rowKey="id"
                search={false}
                options={false}
                pagination={{
                    current: companies.current_page,
                    pageSize: companies.per_page,
                    total: companies.total,
                    onChange: (page) => router.get('/companies', { ...filters, page }, { preserveState: true }),
                }}
                dataSource={companies.data}
                columns={columns}
            />
        </AuthenticatedLayout>
    );
}
