import { Head, router } from '@inertiajs/react';
import { DatePicker, Table } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface StatementLine {
    account_code: string;
    account_name: string;
    amount: number;
}

interface IncomeStatementProps {
    statement: {
        revenue: StatementLine[];
        cogs: StatementLine[];
        expenses: StatementLine[];
        total_revenue: number;
        total_cogs: number;
        total_expenses: number;
        gross_profit: number;
        net_income: number;
    };
    filters: { from: string; to: string };
}

function LineTable({ title, lines, total }: { title: string; lines: StatementLine[]; total: number }) {
    return (
        <div style={{ marginBottom: 24 }}>
            <h3>{title}</h3>
            <Table
                dataSource={lines}
                rowKey="account_code"
                pagination={false}
                size="small"
                columns={[
                    { title: 'Code', dataIndex: 'account_code', width: 90 },
                    { title: 'Account', dataIndex: 'account_name' },
                    { title: 'Amount', dataIndex: 'amount', render: (v: number) => v.toLocaleString('id-ID'), align: 'right' },
                ]}
                footer={() => <div style={{ textAlign: 'right' }}><strong>Total: {total.toLocaleString('id-ID')}</strong></div>}
            />
        </div>
    );
}

export default function IncomeStatement({ statement, filters }: IncomeStatementProps) {
    return (
        <AuthenticatedLayout title="Income Statement">
            <Head title="Income Statement (P&L)" />
            <DatePicker.RangePicker
                value={[dayjs(filters.from), dayjs(filters.to)]}
                onChange={(dates) => router.get('/accounting/reports/income-statement', {
                    from: dates?.[0]?.format('YYYY-MM-DD'),
                    to: dates?.[1]?.format('YYYY-MM-DD'),
                }, { preserveState: true })}
                style={{ marginBottom: 16 }}
            />
            <LineTable title="Revenue" lines={statement.revenue} total={statement.total_revenue} />
            <LineTable title="COGS" lines={statement.cogs} total={statement.total_cogs} />
            <p><strong>Gross Profit: {statement.gross_profit.toLocaleString('id-ID')}</strong></p>
            <LineTable title="Operating Expenses" lines={statement.expenses} total={statement.total_expenses} />
            <p><strong>Net Income: {statement.net_income.toLocaleString('id-ID')}</strong></p>
        </AuthenticatedLayout>
    );
}
