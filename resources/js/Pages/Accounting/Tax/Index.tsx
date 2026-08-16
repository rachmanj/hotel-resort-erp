import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Input, Select, Table } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface TaxTransactionRow {
    id: number;
    tax_type: string;
    tax_type_label: string;
    source_type: string;
    source_id: number;
    transaction_date: string;
    base_amount: number;
    tax_rate_percent: number;
    tax_amount: number;
    tax_period: string;
    status: string;
    status_label: string;
}

interface TaxSummaryRow {
    tax_type: string;
    tax_type_label: string;
    total_base: number;
    total_tax: number;
    count: number;
}

interface TaxIndexProps {
    transactions: TaxTransactionRow[];
    summary: TaxSummaryRow[];
    filters: { period: string; tax_type: string };
    taxTypeOptions: Array<{ value: string; label: string }>;
}

const formatIdr = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

export default function TaxIndex({ transactions, summary, filters, taxTypeOptions }: TaxIndexProps) {
    const columns: ProColumns<TaxTransactionRow>[] = [
        { title: 'Date', dataIndex: 'transaction_date', width: 110 },
        { title: 'Type', dataIndex: 'tax_type_label', width: 120 },
        { title: 'Source', render: (_, r) => `${r.source_type} #${r.source_id}` },
        { title: 'DPP', render: (_, r) => formatIdr(r.base_amount), width: 130 },
        { title: 'Rate %', dataIndex: 'tax_rate_percent', width: 80 },
        { title: 'Tax', render: (_, r) => formatIdr(r.tax_amount), width: 130 },
        { title: 'Status', dataIndex: 'status_label', width: 110 },
    ];

    return (
        <AuthenticatedLayout title="Tax Reports">
            <Head title="Tax Reports" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8, alignItems: 'center' }}>
                <Input
                    placeholder="YYYY-MM"
                    value={filters.period}
                    style={{ width: 120 }}
                    onChange={(e) =>
                        router.get('/accounting/tax', { period: e.target.value, tax_type: filters.tax_type }, { preserveState: true })
                    }
                />
                <Select
                    allowClear
                    placeholder="Tax Type"
                    style={{ width: 160 }}
                    value={filters.tax_type || undefined}
                    options={taxTypeOptions}
                    onChange={(v) =>
                        router.get('/accounting/tax', { period: filters.period, tax_type: v }, { preserveState: true })
                    }
                />
                <Button
                    type="primary"
                    onClick={() =>
                        router.post('/accounting/tax/mark-reported', {
                            period: filters.period,
                            tax_type: filters.tax_type || null,
                        })
                    }
                >
                    Mark Period Reported
                </Button>
            </div>
            <Table
                rowKey="tax_type"
                dataSource={summary}
                pagination={false}
                style={{ marginBottom: 24 }}
                columns={[
                    { title: 'Tax Type', dataIndex: 'tax_type_label' },
                    { title: 'Count', dataIndex: 'count' },
                    { title: 'Total DPP', render: (_, r) => formatIdr(r.total_base) },
                    { title: 'Total Tax', render: (_, r) => formatIdr(r.total_tax) },
                ]}
            />
            <ProTable rowKey="id" search={false} options={false} dataSource={transactions} columns={columns} scroll={{ x: 'max-content' }} />
        </AuthenticatedLayout>
    );
}
