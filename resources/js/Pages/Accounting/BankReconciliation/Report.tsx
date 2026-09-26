import { Head, Link } from '@inertiajs/react';
import { Button, Card, Descriptions, Space, Table, Typography, theme } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MoneyAmount from './components/MoneyAmount';
import StatusTag from './components/StatusTag';

interface ReportItem {
    date: string | null;
    description: string | null;
    reference: string | null;
    status: string;
    amount: number;
}

interface ReportProps {
    header: {
        id: number;
        bank_name: string | null;
        account_no: string | null;
        period_end_date: string;
        periode: string | null;
        status: string;
        validation_status: string | null;
        validator_name: string | null;
        generated_at: string;
    };
    balanceProof: {
        statement_closing: number;
        deposits_in_transit: number;
        outstanding_checks: number;
        adjusted_statement_balance: number;
        book_closing: number;
        unexplained_difference: number;
        cleared_difference: number;
    };
    statementItems: ReportItem[];
    bookItems: ReportItem[];
    adjustments: Array<{
        date: string | null;
        description: string | null;
        journal_no: string | null;
        amount: number;
    }>;
    signOff: {
        prepared_by: string | null;
        prepared_at: string | null;
        submitted_by: string | null;
        submitted_at: string | null;
        validated_by: string | null;
        validated_at: string | null;
    };
}

export default function Report({ header, balanceProof, statementItems, bookItems, adjustments, signOff }: ReportProps) {
    const { token } = theme.useToken();
    const basePath = `/accounting/bank-reconciliation/${header.id}`;

    const moneyColumn = (amount: number) => (
        <span style={{ fontFamily: token.fontFamilyCode }}>
            <MoneyAmount amount={amount} compact={false} />
        </span>
    );

    return (
        <AuthenticatedLayout title="Bank reconciliation report">
            <Head title="Bank Reconciliation Report" />
            <Space style={{ marginBottom: token.marginMD }} wrap>
                <Link href="/accounting/bank-reconciliation">Back to list</Link>
                <Link href={`${basePath}/reconcile`}>Open workspace</Link>
            </Space>

            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    marginBottom: token.marginMD,
                    flexWrap: 'wrap',
                    gap: token.paddingMD,
                }}
            >
                <div>
                    <Typography.Title level={4} style={{ margin: 0 }}>
                        {header.bank_name} · {header.account_no}
                    </Typography.Title>
                    <Typography.Text type="secondary">Period ending {header.period_end_date}</Typography.Text>
                </div>
                <Space wrap>
                    <StatusTag label={header.status} />
                    {header.validation_status && <StatusTag label={header.validation_status} tone="processing" />}
                    <a href={`${basePath}/report/download`}>
                        <Button aria-label="Download PDF report">Download PDF</Button>
                    </a>
                </Space>
            </div>

            <Card size="small" style={{ marginBottom: token.marginMD }}>
                <Descriptions size="small" column={2}>
                    <Descriptions.Item label="Validator">{header.validator_name ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Generated">{header.generated_at}</Descriptions.Item>
                </Descriptions>
            </Card>

            <Card size="small" title="Balance proof" style={{ marginBottom: token.marginMD }}>
                <Descriptions size="small" column={1} bordered>
                    <Descriptions.Item label="Statement closing balance">{moneyColumn(balanceProof.statement_closing)}</Descriptions.Item>
                    <Descriptions.Item label="Deposits in transit">{moneyColumn(balanceProof.deposits_in_transit)}</Descriptions.Item>
                    <Descriptions.Item label="Outstanding checks">{moneyColumn(balanceProof.outstanding_checks)}</Descriptions.Item>
                    <Descriptions.Item label="Adjusted statement balance">{moneyColumn(balanceProof.adjusted_statement_balance)}</Descriptions.Item>
                    <Descriptions.Item label="Book closing balance">{moneyColumn(balanceProof.book_closing)}</Descriptions.Item>
                    <Descriptions.Item label="Cleared difference">{moneyColumn(balanceProof.cleared_difference)}</Descriptions.Item>
                    <Descriptions.Item label="Unexplained difference">{moneyColumn(balanceProof.unexplained_difference)}</Descriptions.Item>
                </Descriptions>
            </Card>

            <Card size="small" title="Open statement items" style={{ marginBottom: token.marginMD }}>
                <Table
                    size="small"
                    rowKey={(row, index) => `st-${index}`}
                    dataSource={statementItems}
                    pagination={false}
                    locale={{ emptyText: 'No open statement items.' }}
                    columns={[
                        { title: 'Date', dataIndex: 'date', width: 110 },
                        { title: 'Description', dataIndex: 'description' },
                        { title: 'Reference', dataIndex: 'reference', width: 120 },
                        { title: 'Status', dataIndex: 'status', width: 120 },
                        { title: 'Amount', align: 'right', render: (_, row) => moneyColumn(row.amount) },
                    ]}
                />
            </Card>

            <Card size="small" title="Open book items" style={{ marginBottom: token.marginMD }}>
                <Table
                    size="small"
                    rowKey={(row, index) => `bk-${index}`}
                    dataSource={bookItems}
                    pagination={false}
                    locale={{ emptyText: 'No open book items.' }}
                    columns={[
                        { title: 'Date', dataIndex: 'date', width: 110 },
                        { title: 'Description', dataIndex: 'description' },
                        { title: 'Reference', dataIndex: 'reference', width: 120 },
                        { title: 'Status', dataIndex: 'status', width: 120 },
                        { title: 'Amount', align: 'right', render: (_, row) => moneyColumn(row.amount) },
                    ]}
                />
            </Card>

            <Card size="small" title="Adjustments" style={{ marginBottom: token.marginMD }}>
                <Table
                    size="small"
                    rowKey={(row, index) => `adj-${index}`}
                    dataSource={adjustments}
                    pagination={false}
                    locale={{ emptyText: 'No adjustments posted.' }}
                    columns={[
                        { title: 'Date', dataIndex: 'date', width: 110 },
                        { title: 'Description', dataIndex: 'description' },
                        { title: 'Journal no.', dataIndex: 'journal_no', width: 140 },
                        { title: 'Amount', align: 'right', render: (_, row) => moneyColumn(row.amount) },
                    ]}
                />
            </Card>

            <Card size="small" title="Sign-off">
                <Descriptions size="small" column={1}>
                    <Descriptions.Item label="Prepared by">
                        {signOff.prepared_by ?? '—'}
                        {signOff.prepared_at ? ` · ${signOff.prepared_at}` : ''}
                    </Descriptions.Item>
                    <Descriptions.Item label="Submitted by">
                        {signOff.submitted_by ?? '—'}
                        {signOff.submitted_at ? ` · ${signOff.submitted_at}` : ''}
                    </Descriptions.Item>
                    <Descriptions.Item label="Validated by">
                        {signOff.validated_by ?? '—'}
                        {signOff.validated_at ? ` · ${signOff.validated_at}` : ''}
                    </Descriptions.Item>
                </Descriptions>
            </Card>
        </AuthenticatedLayout>
    );
}
