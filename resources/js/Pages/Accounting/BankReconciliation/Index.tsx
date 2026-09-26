import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, DatePicker, Form, InputNumber, Modal, Progress, Select, Space, theme } from 'antd';
import dayjs from 'dayjs';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MoneyAmount from './components/MoneyAmount';
import StatusTag from './components/StatusTag';

interface ReconciliationRow {
    id: number;
    bank_account_id: number;
    bank_name: string | null;
    account_no: string | null;
    period_end_date: string;
    statement_balance: number;
    book_balance: number;
    variance: number;
    statement_lines_count: number;
    matched_statement_lines_count: number;
    status: string;
    status_label: string;
    validation_status: string | null;
    validation_status_label: string | null;
    validator_name: string | null;
    updated_at: string | null;
}

interface BankReconciliationIndexProps {
    reconciliations: { data: ReconciliationRow[]; total: number };
    bankAccounts: Array<{ id: number; bank_name: string; account_no: string }>;
    filters: {
        status: string | null;
        validation_status: string | null;
        bank_account_id: number | null;
        period_from: string | null;
        period_to: string | null;
        pending_validation: boolean;
    };
    statusOptions: Array<{ value: string; label: string }>;
    validationStatusOptions: Array<{ value: string; label: string }>;
}

export default function BankReconciliationIndex({
    reconciliations,
    bankAccounts,
    filters,
    statusOptions,
    validationStatusOptions,
}: BankReconciliationIndexProps) {
    const { token } = theme.useToken();
    const permissions = (usePage().props.auth as { permissions?: string[] }).permissions ?? [];
    const [createOpen, setCreateOpen] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        bank_account_id: null as number | null,
        period_end_date: dayjs().endOf('month').format('YYYY-MM-DD'),
        statement_balance: 0,
    });

    const applyFilters = (patch: Record<string, string | number | boolean | null | undefined>) => {
        router.get(
            '/accounting/bank-reconciliation',
            {
                ...filters,
                ...patch,
                pending_validation: patch.pending_validation ?? filters.pending_validation,
            },
            { preserveState: true, replace: true },
        );
    };

    const columns: ProColumns<ReconciliationRow>[] = useMemo(
        () => [
            { title: 'Period end', dataIndex: 'period_end_date', width: 110 },
            {
                title: 'Bank account',
                render: (_, row) => (
                    <div>
                        <div>{row.bank_name}</div>
                        <div style={{ color: token.colorTextSecondary, fontSize: token.fontSizeSM }}>{row.account_no}</div>
                    </div>
                ),
            },
            {
                title: 'Statement closing',
                align: 'right',
                width: 130,
                sorter: (a, b) => a.statement_balance - b.statement_balance,
                render: (_, row) => (
                    <span style={{ fontFamily: token.fontFamilyCode }}>
                        <MoneyAmount amount={row.statement_balance} />
                    </span>
                ),
            },
            {
                title: 'Book closing',
                align: 'right',
                width: 130,
                sorter: (a, b) => a.book_balance - b.book_balance,
                render: (_, row) => (
                    <span style={{ fontFamily: token.fontFamilyCode }}>
                        <MoneyAmount amount={row.book_balance} />
                    </span>
                ),
            },
            {
                title: 'Difference',
                align: 'right',
                width: 120,
                sorter: (a, b) => a.variance - b.variance,
                render: (_, row) => (
                    <span
                        style={{
                            fontFamily: token.fontFamilyCode,
                            color: Math.abs(row.variance) >= 0.005 ? token.colorWarning : token.colorText,
                        }}
                    >
                        <MoneyAmount amount={row.variance} />
                    </span>
                ),
            },
            {
                title: 'Matched',
                width: 140,
                render: (_, row) => {
                    const total = row.statement_lines_count;
                    const matched = row.matched_statement_lines_count;
                    const percent = total > 0 ? Math.round((matched / total) * 100) : 0;

                    return (
                        <div>
                            <div style={{ fontSize: token.fontSizeSM, marginBottom: 2 }}>
                                {matched}/{total} matched
                            </div>
                            <Progress percent={percent} size="small" showInfo={false} />
                        </div>
                    );
                },
            },
            {
                title: 'Status',
                width: 200,
                render: (_, row) => (
                    <Space size={4} wrap>
                        <StatusTag label={row.status_label} />
                        {row.validation_status_label && <StatusTag label={row.validation_status_label} tone="processing" />}
                    </Space>
                ),
            },
            { title: 'Validator', dataIndex: 'validator_name', width: 120, render: (value) => value ?? '—' },
            { title: 'Updated', dataIndex: 'updated_at', width: 150, render: (value) => value ?? '—' },
            {
                title: 'Actions',
                width: 220,
                fixed: 'right',
                render: (_, row) => {
                    const actions = [
                        <Link key="open" href={`/accounting/bank-reconciliation/${row.id}/reconcile`}>
                            Open
                        </Link>,
                    ];

                    if (permissions.includes('bankrec.import') && row.statement_lines_count === 0) {
                        actions.push(
                            <Link key="import" href={`/accounting/bank-reconciliation/${row.id}/reconcile`}>
                                Import
                            </Link>,
                        );
                    }

                    if (
                        permissions.includes('bankrec.validate') &&
                        row.validation_status === 'pending'
                    ) {
                        actions.push(
                            <Link key="validate" href={`/accounting/bank-reconciliation/${row.id}/reconcile`}>
                                Validate
                            </Link>,
                        );
                    }

                    if (row.statement_lines_count > 0) {
                        actions.push(
                            <Link key="report" href={`/accounting/bank-reconciliation/${row.id}/report`}>
                                Report
                            </Link>,
                        );
                    }

                    return <Space size="small" wrap>{actions}</Space>;
                },
            },
        ],
        [permissions, token],
    );

    const startReconciliation = () => {
        Modal.confirm({
            title: 'Start new reconciliation?',
            onOk: () =>
                post('/accounting/bank-reconciliation', {
                    onSuccess: () => {
                        setCreateOpen(false);
                        reset();
                    },
                }),
        });
    };

    const hasSessions = reconciliations.data.length > 0 || reconciliations.total > 0;

    return (
        <AuthenticatedLayout title="Bank Reconciliation">
            <Head title="Bank Reconciliation" />
            <Space wrap style={{ marginBottom: token.marginMD }}>
                <Link href="/accounting/bank-accounts">
                    <Button>Manage bank accounts</Button>
                </Link>
                {permissions.includes('bankrec.import') && (
                    <Button type="primary" onClick={() => setCreateOpen(true)}>
                        New reconciliation
                    </Button>
                )}
                <Button
                    type={filters.pending_validation ? 'primary' : 'default'}
                    onClick={() =>
                        applyFilters({
                            pending_validation: !filters.pending_validation,
                            validation_status: filters.pending_validation ? null : 'pending',
                        })
                    }
                >
                    Pending validation
                </Button>
            </Space>

            <Space wrap style={{ marginBottom: token.marginMD }}>
                <Select
                    allowClear
                    placeholder="Status"
                    aria-label="Filter by session status"
                    style={{ width: 180 }}
                    value={filters.status ?? undefined}
                    options={statusOptions}
                    onChange={(value) => applyFilters({ status: value ?? null })}
                />
                <Select
                    allowClear
                    placeholder="Validation status"
                    aria-label="Filter by validation status"
                    style={{ width: 180 }}
                    value={filters.validation_status ?? undefined}
                    options={validationStatusOptions}
                    onChange={(value) => applyFilters({ validation_status: value ?? null })}
                />
                <Select
                    allowClear
                    showSearch
                    placeholder="Bank account"
                    aria-label="Filter by bank account"
                    style={{ width: 240 }}
                    value={filters.bank_account_id ?? undefined}
                    options={bankAccounts.map((account) => ({
                        value: account.id,
                        label: `${account.bank_name} — ${account.account_no}`,
                    }))}
                    onChange={(value) => applyFilters({ bank_account_id: value ?? null })}
                />
                <DatePicker.RangePicker
                    aria-label="Period end date range"
                    value={
                        filters.period_from && filters.period_to
                            ? [dayjs(filters.period_from), dayjs(filters.period_to)]
                            : undefined
                    }
                    onChange={(dates) =>
                        applyFilters({
                            period_from: dates?.[0]?.format('YYYY-MM-DD') ?? null,
                            period_to: dates?.[1]?.format('YYYY-MM-DD') ?? null,
                        })
                    }
                />
            </Space>

            {!hasSessions ? (
                <div
                    style={{
                        padding: token.paddingLG,
                        border: `1px dashed ${token.colorBorder}`,
                        borderRadius: token.borderRadius,
                        color: token.colorTextSecondary,
                    }}
                >
                    No reconciliation sessions yet. Start a new reconciliation for a bank account and period, then import
                    statement lines or enter them manually.
                </div>
            ) : (
                <ProTable
                    rowKey="id"
                    search={false}
                    options={false}
                    size="small"
                    pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }}
                    dataSource={reconciliations.data}
                    columns={columns}
                    scroll={{ x: 'max-content' }}
                />
            )}

            <Modal
                title="New reconciliation"
                open={createOpen}
                onCancel={() => setCreateOpen(false)}
                onOk={startReconciliation}
                confirmLoading={processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Bank account" required>
                        <Select
                            aria-label="Bank account"
                            value={data.bank_account_id}
                            options={bankAccounts.map((account) => ({
                                value: account.id,
                                label: `${account.bank_name} — ${account.account_no}`,
                            }))}
                            onChange={(value) => setData('bank_account_id', value)}
                        />
                    </Form.Item>
                    <Form.Item label="Period end date">
                        <DatePicker
                            aria-label="Period end date"
                            style={{ width: '100%' }}
                            value={dayjs(data.period_end_date)}
                            onChange={(date) => setData('period_end_date', date?.format('YYYY-MM-DD') ?? '')}
                        />
                    </Form.Item>
                    <Form.Item label="Statement closing balance">
                        <InputNumber
                            aria-label="Statement closing balance"
                            style={{ width: '100%' }}
                            value={data.statement_balance}
                            onChange={(value) => setData('statement_balance', value ?? 0)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
