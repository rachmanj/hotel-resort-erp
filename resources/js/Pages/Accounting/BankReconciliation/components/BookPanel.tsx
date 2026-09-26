import { router } from '@inertiajs/react';
import { Button, Card, DatePicker, Input, Modal, Select, Space, Table, Tag, Tooltip, Typography, theme } from 'antd';
import type { ColumnsType, TableRowSelection } from 'antd/es/table/interface';
import dayjs from 'dayjs';
import { useMemo, useState } from 'react';
import type { BookLineRow } from '../types';
import MoneyAmount from './MoneyAmount';
import StatusTag from './StatusTag';

interface BookPanelProps {
    reconciliationId: number;
    lines: BookLineRow[];
    selectedIds: number[];
    onSelectionChange: (ids: number[]) => void;
    canReconcile: boolean;
}

export default function BookPanel({
    reconciliationId,
    lines,
    selectedIds,
    onSelectionChange,
    canReconcile,
}: BookPanelProps) {
    const { token } = theme.useToken();
    const [statusFilter, setStatusFilter] = useState<string | undefined>();
    const [search, setSearch] = useState('');
    const [dateRange, setDateRange] = useState<[dayjs.Dayjs | null, dayjs.Dayjs | null] | null>(null);

    const filtered = useMemo(() => {
        return lines.filter((line) => {
            if (statusFilter && line.match_status !== statusFilter) {
                return false;
            }

            if (search) {
                const haystack = `${line.description ?? ''} ${line.reference ?? ''} ${line.doc_num ?? ''}`.toLowerCase();
                if (!haystack.includes(search.toLowerCase())) {
                    return false;
                }
            }

            if (dateRange?.[0] && line.posting_date) {
                if (dayjs(line.posting_date).isBefore(dateRange[0], 'day')) {
                    return false;
                }
            }

            if (dateRange?.[1] && line.posting_date) {
                if (dayjs(line.posting_date).isAfter(dateRange[1], 'day')) {
                    return false;
                }
            }

            return true;
        });
    }, [lines, statusFilter, search, dateRange]);

    const promptReason = (title: string, onConfirm: (reason: string) => void) => {
        let reason = '';
        Modal.confirm({
            title,
            content: (
                <Input.TextArea
                    aria-label="Reason"
                    rows={3}
                    onChange={(event) => {
                        reason = event.target.value;
                    }}
                />
            ),
            onOk: () => onConfirm(reason),
        });
    };

    const columns: ColumnsType<BookLineRow> = [
        { title: 'Date', dataIndex: 'posting_date', width: 100, sorter: (a, b) => (a.posting_date ?? '').localeCompare(b.posting_date ?? '') },
        { title: 'Doc / ref.', dataIndex: 'reference', width: 120, ellipsis: true },
        { title: 'Description', dataIndex: 'description', ellipsis: true },
        {
            title: 'Debit',
            dataIndex: 'debit',
            width: 110,
            align: 'right',
            sorter: (a, b) => a.debit - b.debit,
            render: (value: number) =>
                value > 0 ? (
                    <span style={{ fontFamily: token.fontFamilyCode }}>
                        <MoneyAmount amount={value} />
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            title: 'Credit',
            dataIndex: 'credit',
            width: 110,
            align: 'right',
            sorter: (a, b) => a.credit - b.credit,
            render: (value: number) =>
                value > 0 ? (
                    <span style={{ fontFamily: token.fontFamilyCode }}>
                        <MoneyAmount amount={value} />
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            title: 'Status',
            width: 160,
            render: (_, row) => (
                <Space size={4}>
                    <StatusTag label={row.match_status_label} />
                    {row.is_stale && (
                        <Tooltip title={row.stale_reason ?? 'General ledger changed after this line was loaded.'}>
                            <Tag style={{ marginInlineEnd: 0 }}>Ledger changed</Tag>
                        </Tooltip>
                    )}
                </Space>
            ),
        },
        {
            title: 'Group',
            dataIndex: 'match_group_id',
            width: 70,
            render: (value: number | null) => value ?? '—',
        },
        {
            title: 'Actions',
            width: 180,
            render: (_, row) => {
                if (!canReconcile) {
                    return null;
                }

                const actions = [];

                if (row.match_status === 'excluded') {
                    actions.push(
                        <Button key="include" type="link" size="small" onClick={() => router.post(`/accounting/bank-reconciliation/${reconciliationId}/book-lines/${row.id}/include`)}>
                            Include
                        </Button>,
                    );
                } else {
                    actions.push(
                        <Button
                            key="exclude"
                            type="link"
                            size="small"
                            onClick={() =>
                                promptReason('Exclude book line', (reason) =>
                                    router.post(`/accounting/bank-reconciliation/${reconciliationId}/book-lines/${row.id}/exclude`, {
                                        reason,
                                    }),
                                )
                            }
                        >
                            Exclude
                        </Button>,
                    );
                }

                if (row.match_status === 'outstanding') {
                    actions.push(
                        <Button key="clear" type="link" size="small" onClick={() => router.post(`/accounting/bank-reconciliation/${reconciliationId}/book-lines/${row.id}/include`)}>
                            Clear outstanding
                        </Button>,
                    );
                } else if (row.match_status === 'unmatched') {
                    actions.push(
                        <Button
                            key="outstanding"
                            type="link"
                            size="small"
                            onClick={() =>
                                promptReason('Mark outstanding', (reason) =>
                                    router.post(`/accounting/bank-reconciliation/${reconciliationId}/book-lines/${row.id}/outstanding`, {
                                        reason,
                                    }),
                                )
                            }
                        >
                            Outstanding
                        </Button>,
                    );
                }

                return <Space size={0} wrap>{actions}</Space>;
            },
        },
    ];

    const rowSelection: TableRowSelection<BookLineRow> = {
        selectedRowKeys: selectedIds,
        onChange: (keys) => onSelectionChange(keys as number[]),
        getCheckboxProps: (row) => ({
            disabled: !canReconcile || row.match_status === 'matched' || row.match_status === 'manual',
        }),
    };

    const statusOptions = Array.from(new Set(lines.map((line) => line.match_status))).map((value) => {
        const label = lines.find((line) => line.match_status === value)?.match_status_label ?? value;

        return { value, label };
    });

    return (
        <Card size="small" title="Book (GL) lines">
            <Space wrap style={{ marginBottom: token.marginSM }}>
                <Select
                    allowClear
                    placeholder="Status"
                    aria-label="Filter book lines by status"
                    style={{ width: 160 }}
                    value={statusFilter}
                    options={statusOptions}
                    onChange={setStatusFilter}
                />
                <Input.Search
                    allowClear
                    placeholder="Search description or reference"
                    aria-label="Search book lines"
                    style={{ width: 220 }}
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onSearch={setSearch}
                />
                <DatePicker.RangePicker
                    aria-label="Book line date range"
                    value={dateRange}
                    onChange={(values) => setDateRange(values)}
                />
            </Space>

            {lines.length === 0 ? (
                <Typography.Paragraph type="secondary">
                    No book lines loaded yet. Use Refresh book lines in the toolbar to load general ledger activity for this period.
                </Typography.Paragraph>
            ) : (
                <Table
                    size="small"
                    rowKey="id"
                    columns={columns}
                    dataSource={filtered}
                    pagination={{ pageSize: 15, showSizeChanger: false }}
                    rowSelection={canReconcile ? rowSelection : undefined}
                    scroll={{ x: 'max-content' }}
                />
            )}
        </Card>
    );
}
