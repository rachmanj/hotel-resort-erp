import { Head, router } from '@inertiajs/react';
import { Col, DatePicker, Row, Table } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface StatementLine {
    account_code: string;
    account_name: string;
    amount: number;
}

interface BalanceSheetProps {
    statement: {
        assets: StatementLine[];
        liabilities: StatementLine[];
        equity: StatementLine[];
        total_assets: number;
        total_liabilities: number;
        total_equity: number;
        total_liabilities_and_equity: number;
    };
    filters: { as_of: string };
}

function SectionTable({ title, lines, total }: { title: string; lines: StatementLine[]; total: number }) {
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

export default function BalanceSheet({ statement, filters }: BalanceSheetProps) {
    return (
        <AuthenticatedLayout title="Balance Sheet">
            <Head title="Balance Sheet" />
            <DatePicker
                value={dayjs(filters.as_of)}
                onChange={(d) => router.get('/accounting/reports/balance-sheet', { as_of: d?.format('YYYY-MM-DD') }, { preserveState: true })}
                style={{ marginBottom: 16 }}
            />
            <Row gutter={24}>
                <Col span={12}>
                    <SectionTable title="Assets" lines={statement.assets} total={statement.total_assets} />
                </Col>
                <Col span={12}>
                    <SectionTable title="Liabilities" lines={statement.liabilities} total={statement.total_liabilities} />
                    <SectionTable title="Equity" lines={statement.equity} total={statement.total_equity} />
                    <p><strong>L + E: {statement.total_liabilities_and_equity.toLocaleString('id-ID')}</strong></p>
                </Col>
            </Row>
        </AuthenticatedLayout>
    );
}
