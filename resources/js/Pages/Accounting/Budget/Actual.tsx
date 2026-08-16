import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { InputNumber } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface VarianceRow {
    department: string;
    account_code: string | null;
    account_name: string | null;
    month: number;
    budgeted: number;
    actual: number;
    variance: number;
    variance_pct: number;
}

interface BudgetActualProps {
    rows: VarianceRow[];
    year: number;
    month: number | null;
}

const formatIdr = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

export default function BudgetActual({ rows, year, month }: BudgetActualProps) {
    const columns: ProColumns<VarianceRow>[] = [
        { title: 'Department', dataIndex: 'department' },
        { title: 'Account', render: (_, r) => `${r.account_code} - ${r.account_name}` },
        { title: 'Month', dataIndex: 'month', width: 70 },
        { title: 'Budgeted', render: (_, r) => formatIdr(r.budgeted), width: 130 },
        { title: 'Actual', render: (_, r) => formatIdr(r.actual), width: 130 },
        { title: 'Variance', render: (_, r) => formatIdr(r.variance), width: 130 },
        { title: 'Var %', render: (_, r) => `${r.variance_pct}%`, width: 80 },
    ];

    return (
        <AuthenticatedLayout title="Budget vs Actual">
            <Head title="Budget vs Actual" />
            <Link href="/accounting/budgets" style={{ marginBottom: 16, display: 'inline-block' }}>
                ← Back to Budgets
            </Link>
            <div style={{ marginBottom: 16, display: 'flex', gap: 8 }}>
                <InputNumber
                    value={year}
                    onChange={(v) => router.get('/accounting/budgets/actual', { year: v, month }, { preserveState: true })}
                />
                <InputNumber
                    min={1}
                    max={12}
                    placeholder="Month (all)"
                    value={month ?? undefined}
                    onChange={(v) => router.get('/accounting/budgets/actual', { year, month: v }, { preserveState: true })}
                />
            </div>
            <ProTable rowKey={(_, i) => String(i)} search={false} options={false} pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }} dataSource={rows} columns={columns} scroll={{ x: 'max-content' }} />
        </AuthenticatedLayout>
    );
}
