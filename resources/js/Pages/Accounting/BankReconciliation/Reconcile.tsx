import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, Card, DatePicker, Descriptions, InputNumber, Space, Table } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface StatementLine {
    statement_date: string;
    statement_amount: number;
    statement_line_ref?: string;
}

interface ReconcileProps {
    reconciliation: {
        id: number;
        bank_name: string | null;
        account_no: string | null;
        period_end_date: string;
        statement_balance: number;
        book_balance: number;
        status: string;
        status_label: string;
        lines: Array<{
            id: number;
            statement_line_ref: string | null;
            statement_date: string;
            statement_amount: number;
            is_matched: boolean;
            gl_description: string | null;
        }>;
    };
    unmatchedLedger: Array<{
        id: number;
        transaction_date: string;
        description: string;
        amount: number;
    }>;
}

const formatIdr = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

export default function Reconcile({ reconciliation, unmatchedLedger }: ReconcileProps) {
    const [importLines, setImportLines] = useState<StatementLine[]>([
        { statement_date: reconciliation.period_end_date, statement_amount: 0 },
    ]);

    const importForm = useForm({ lines: [] as StatementLine[] });

    const submitImport = () => {
        importForm.setData('lines', importLines);
        importForm.post(`/accounting/bank-reconciliation/${reconciliation.id}/import-lines`);
    };

    const matchLine = (lineId: number, glId: number) => {
        router.post(`/accounting/bank-reconciliation/${reconciliation.id}/match`, {
            line_id: lineId,
            general_ledger_id: glId,
        });
    };

    const isComplete = reconciliation.status === 'completed';

    return (
        <AuthenticatedLayout title="Reconcile Bank Statement">
            <Head title="Bank Reconciliation" />
            <Link href="/accounting/bank-reconciliation" style={{ marginBottom: 16, display: 'inline-block' }}>
                ← Back
            </Link>
            <Card>
                <Descriptions bordered column={2}>
                    <Descriptions.Item label="Bank">{reconciliation.bank_name}</Descriptions.Item>
                    <Descriptions.Item label="Account">{reconciliation.account_no}</Descriptions.Item>
                    <Descriptions.Item label="Period End">{reconciliation.period_end_date}</Descriptions.Item>
                    <Descriptions.Item label="Status">{reconciliation.status_label}</Descriptions.Item>
                    <Descriptions.Item label="Statement Balance">
                        {formatIdr(reconciliation.statement_balance)}
                    </Descriptions.Item>
                    <Descriptions.Item label="Book Balance">
                        {formatIdr(reconciliation.book_balance)}
                    </Descriptions.Item>
                </Descriptions>
                {!isComplete && (
                    <Space style={{ marginTop: 16 }}>
                        <Button onClick={() => router.post(`/accounting/bank-reconciliation/${reconciliation.id}/auto-match`)}>
                            Auto Match
                        </Button>
                        <Button
                            type="primary"
                            onClick={() => router.post(`/accounting/bank-reconciliation/${reconciliation.id}/complete`)}
                        >
                            Complete Reconciliation
                        </Button>
                    </Space>
                )}
            </Card>
            {!isComplete && (
                <Card title="Import Statement Lines" style={{ marginTop: 16 }}>
                    {importLines.map((line, i) => (
                        <Space key={i} style={{ marginBottom: 8 }}>
                            <DatePicker
                                value={dayjs(line.statement_date)}
                                onChange={(d) => {
                                    const next = [...importLines];
                                    next[i].statement_date = d?.format('YYYY-MM-DD') ?? '';
                                    setImportLines(next);
                                }}
                            />
                            <InputNumber
                                value={line.statement_amount}
                                onChange={(v) => {
                                    const next = [...importLines];
                                    next[i].statement_amount = v ?? 0;
                                    setImportLines(next);
                                }}
                            />
                        </Space>
                    ))}
                    <Space>
                        <Button onClick={() => setImportLines([...importLines, { statement_date: reconciliation.period_end_date, statement_amount: 0 }])}>
                            Add Line
                        </Button>
                        <Button type="primary" loading={importForm.processing} onClick={submitImport}>
                            Import
                        </Button>
                    </Space>
                </Card>
            )}
            <Card title="Statement Lines" style={{ marginTop: 16 }}>
                <Table
                    rowKey="id"
                    dataSource={reconciliation.lines}
                    pagination={false}
                    columns={[
                        { title: 'Date', dataIndex: 'statement_date' },
                        { title: 'Amount', render: (_, r) => formatIdr(r.statement_amount) },
                        { title: 'Ref', dataIndex: 'statement_line_ref' },
                        { title: 'Matched GL', dataIndex: 'gl_description' },
                        {
                            title: 'Match',
                            render: (_, r) =>
                                !r.is_matched &&
                                !isComplete && (
                                    <select
                                        onChange={(e) => e.target.value && matchLine(r.id, Number(e.target.value))}
                                        defaultValue=""
                                    >
                                        <option value="">Select GL entry</option>
                                        {unmatchedLedger.map((gl) => (
                                            <option key={gl.id} value={gl.id}>
                                                {gl.transaction_date} · {gl.description} ({formatIdr(gl.amount)})
                                            </option>
                                        ))}
                                    </select>
                                ),
                        },
                    ]}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
