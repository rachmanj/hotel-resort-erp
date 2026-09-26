import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Alert, Button, Card, DatePicker, InputNumber, Modal, Skeleton, Space, Typography, theme } from 'antd';
import dayjs from 'dayjs';
import { useCallback, useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AdjustmentModal from './components/AdjustmentModal';
import BookPanel from './components/BookPanel';
import MatchPanel from './components/MatchPanel';
import StatementPanel from './components/StatementPanel';
import SubmitPanel from './components/SubmitPanel';
import StatusTag from './components/StatusTag';
import VariancePanel from './components/VariancePanel';
import type {
    BookLineRow,
    MatchGroupRow,
    PostableAccount,
    ReconciliationHeader,
    StatementLineRow,
    StatusPayload,
    SubmitChecklistItem,
} from './types';

interface ReconcileProps {
    reconciliation: ReconciliationHeader;
    statementLines: StatementLineRow[];
    bookLines: BookLineRow[];
    matchGroups: MatchGroupRow[];
    statusPayload: StatusPayload;
    submitChecklist: SubmitChecklistItem[];
    postableAccounts: PostableAccount[];
    isPreparer: boolean;
    isEditable: boolean;
}

export default function Reconcile({
    reconciliation,
    statementLines,
    bookLines,
    matchGroups,
    statusPayload: initialStatusPayload,
    submitChecklist,
    postableAccounts,
    isPreparer,
    isEditable,
}: ReconcileProps) {
    const { token } = theme.useToken();
    const flash = (usePage().props.flash as { success?: string; error?: string }) ?? {};
    const permissions = (usePage().props.auth as { permissions?: string[] }).permissions ?? [];

    const [statusPayload, setStatusPayload] = useState(initialStatusPayload);
    const [selectedStatementIds, setSelectedStatementIds] = useState<number[]>([]);
    const [selectedBookIds, setSelectedBookIds] = useState<number[]>([]);
    const [adjustmentLine, setAdjustmentLine] = useState<StatementLineRow | null>(null);
    const [processingPoll, setProcessingPoll] = useState(reconciliation.status === 'processing');

    const canReconcile = permissions.includes('bankrec.reconcile') && isEditable;
    const canAdjust = permissions.includes('bankrec.adjust') && isEditable;
    const canImport = permissions.includes('bankrec.import') && isEditable;

    const selectedStatementLines = useMemo(
        () => statementLines.filter((line) => selectedStatementIds.includes(line.id)),
        [statementLines, selectedStatementIds],
    );
    const selectedBookLines = useMemo(
        () => bookLines.filter((line) => selectedBookIds.includes(line.id)),
        [bookLines, selectedBookIds],
    );

    const pollStatus = useCallback(async () => {
        const response = await fetch(`/accounting/bank-reconciliation/${reconciliation.id}/status`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as StatusPayload;
        setStatusPayload((prev) => ({ ...prev, ...data }));

        if (data.status && data.status !== 'processing') {
            setProcessingPoll(false);
            router.reload({ only: ['statementLines', 'bookLines', 'matchGroups', 'statusPayload', 'submitChecklist', 'reconciliation'] });
        }
    }, [reconciliation.id]);

    useEffect(() => {
        if (!processingPoll && reconciliation.status !== 'processing') {
            return;
        }

        setProcessingPoll(reconciliation.status === 'processing');
    }, [reconciliation.status, processingPoll]);

    useEffect(() => {
        if (!processingPoll) {
            return;
        }

        const timer = window.setInterval(() => {
            void pollStatus();
        }, 3000);

        return () => window.clearInterval(timer);
    }, [processingPoll, pollStatus]);

    const staleWarning =
        (statusPayload.stale_lines_count ?? 0) > 0 ||
        (flash.success?.includes('stale') ?? false);

    const importForm = useForm({
        lines: [{ statement_date: reconciliation.period_end_date, statement_amount: 0, statement_line_ref: '', description: '' }],
    });

    const addImportRow = () => {
        importForm.setData('lines', [
            ...importForm.data.lines,
            { statement_date: reconciliation.period_end_date, statement_amount: 0, statement_line_ref: '', description: '' },
        ]);
    };

    const submitImport = () => {
        Modal.confirm({
            title: 'Import statement lines?',
            onOk: () => importForm.post(`/accounting/bank-reconciliation/${reconciliation.id}/import-lines`),
        });
    };

    const refreshBookLines = () => {
        Modal.confirm({
            title: 'Refresh book lines?',
            content: 'Unmatched book lines will be reloaded from the general ledger. Matched lines are kept.',
            onOk: () => router.post(`/accounting/bank-reconciliation/${reconciliation.id}/book-lines/refresh`),
        });
    };

    const autoMatch = () => {
        Modal.confirm({
            title: 'Run auto-match?',
            content: 'Existing manual match groups will not be removed.',
            onOk: () => router.post(`/accounting/bank-reconciliation/${reconciliation.id}/auto-match`),
        });
    };

    const validationLabel = reconciliation.validation_status_label
        ? `${reconciliation.status_label} · ${reconciliation.validation_status_label}`
        : reconciliation.status_label;

    return (
        <AuthenticatedLayout title="Bank reconciliation workspace">
            <Head title="Bank Reconciliation" />
            <div style={{ marginBottom: token.marginMD }}>
                <Link href="/accounting/bank-reconciliation">Back to list</Link>
            </div>

            <div
                style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    justifyContent: 'space-between',
                    gap: token.paddingMD,
                    marginBottom: token.marginMD,
                }}
            >
                <div>
                    <Typography.Title level={4} style={{ margin: 0 }}>
                        {reconciliation.bank_name} · {reconciliation.account_no}
                    </Typography.Title>
                    <Typography.Text type="secondary">
                        Period ending {reconciliation.period_end_date}
                    </Typography.Text>
                </div>
                <Space wrap>
                    <StatusTag
                        label={validationLabel}
                        tone={reconciliation.status === 'completed' ? 'success' : reconciliation.status === 'failed' ? 'error' : 'processing'}
                    />
                    {canReconcile && (
                        <Button onClick={refreshBookLines} aria-label="Refresh book lines">
                            Refresh book lines
                        </Button>
                    )}
                    {canReconcile && (
                        <Button onClick={autoMatch} aria-label="Auto-match">
                            Auto-match
                        </Button>
                    )}
                    <Link href={`/accounting/bank-reconciliation/${reconciliation.id}/report`}>
                        <Button aria-label="Open reconciliation report">Report</Button>
                    </Link>
                </Space>
            </div>

            {flash.success && (
                <Alert type="success" message={flash.success} style={{ marginBottom: token.marginMD }} showIcon />
            )}
            {flash.error && <Alert type="error" message={flash.error} style={{ marginBottom: token.marginMD }} showIcon />}

            {staleWarning && (
                <Alert
                    type="warning"
                    showIcon
                    style={{ marginBottom: token.marginMD }}
                    message={`${statusPayload.stale_lines_count ?? 0} book line(s) are stale because the ledger changed. Review them in the book panel before submitting.`}
                />
            )}

            {processingPoll ? (
                <Card>
                    <Skeleton active paragraph={{ rows: 6 }} />
                    <Typography.Text type="secondary">Processing statement import…</Typography.Text>
                </Card>
            ) : (
                <>
                    {canImport && statementLines.length === 0 && (
                        <Card size="small" title="Import statement lines manually" style={{ marginBottom: token.marginMD }}>
                            {importForm.data.lines.map((line, index) => (
                                <Space key={index} wrap style={{ marginBottom: token.marginXS }}>
                                    <DatePicker
                                        aria-label="Statement line date"
                                        value={dayjs(line.statement_date)}
                                        onChange={(date) => {
                                            const next = [...importForm.data.lines];
                                            next[index] = { ...next[index], statement_date: date?.format('YYYY-MM-DD') ?? '' };
                                            importForm.setData('lines', next);
                                        }}
                                    />
                                    <InputNumber
                                        aria-label="Statement line amount"
                                        value={line.statement_amount}
                                        onChange={(value) => {
                                            const next = [...importForm.data.lines];
                                            next[index] = { ...next[index], statement_amount: value ?? 0 };
                                            importForm.setData('lines', next);
                                        }}
                                    />
                                </Space>
                            ))}
                            <Space>
                                <Button onClick={addImportRow}>Add row</Button>
                                <Button type="primary" loading={importForm.processing} onClick={submitImport}>
                                    Import lines
                                </Button>
                            </Space>
                        </Card>
                    )}

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
                            gap: token.paddingMD,
                            marginBottom: token.marginMD,
                        }}
                    >
                        <VariancePanel statusPayload={statusPayload} />
                        <MatchPanel
                            reconciliationId={reconciliation.id}
                            selectedStatementLines={selectedStatementLines}
                            selectedBookLines={selectedBookLines}
                            matchGroups={matchGroups}
                            canReconcile={canReconcile}
                        />
                        <SubmitPanel
                            reconciliation={reconciliation}
                            checklist={submitChecklist}
                            isPreparer={isPreparer}
                            isEditable={isEditable}
                        />
                    </div>

                    <Space direction="vertical" size="middle" style={{ width: '100%' }}>
                        <StatementPanel
                            reconciliationId={reconciliation.id}
                            lines={statementLines}
                            selectedIds={selectedStatementIds}
                            onSelectionChange={setSelectedStatementIds}
                            canReconcile={canReconcile}
                            canAdjust={canAdjust}
                            canImport={canImport}
                            onCreateAdjustment={(line) => setAdjustmentLine(line)}
                        />
                        <BookPanel
                            reconciliationId={reconciliation.id}
                            lines={bookLines}
                            selectedIds={selectedBookIds}
                            onSelectionChange={setSelectedBookIds}
                            canReconcile={canReconcile}
                        />
                    </Space>
                </>
            )}

            <AdjustmentModal
                open={adjustmentLine !== null}
                reconciliationId={reconciliation.id}
                line={adjustmentLine}
                postableAccounts={postableAccounts}
                onClose={() => setAdjustmentLine(null)}
            />
        </AuthenticatedLayout>
    );
}
