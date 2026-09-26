import { router } from '@inertiajs/react';
import { Button, Card, List, Modal, Space, Typography, theme } from 'antd';
import type { BookLineRow, MatchGroupRow, StatementLineRow } from '../types';
import MoneyAmount from './MoneyAmount';

const TOLERANCE = 0.005;

interface MatchPanelProps {
    reconciliationId: number;
    selectedStatementLines: StatementLineRow[];
    selectedBookLines: BookLineRow[];
    matchGroups: MatchGroupRow[];
    canReconcile: boolean;
}

export default function MatchPanel({
    reconciliationId,
    selectedStatementLines,
    selectedBookLines,
    matchGroups,
    canReconcile,
}: MatchPanelProps) {
    const { token } = theme.useToken();

    const bankTotal = selectedStatementLines.reduce((sum, line) => sum + line.net_amount, 0);
    const bookTotal = selectedBookLines.reduce((sum, line) => sum + line.net_amount, 0);
    const combined = bankTotal + bookTotal;
    const canMatch = Math.abs(combined) <= TOLERANCE && selectedStatementLines.length > 0 && selectedBookLines.length > 0;

    const runMatch = () => {
        Modal.confirm({
            title: 'Save match group?',
            content: 'Selected statement and book lines will be linked as a manual match.',
            onOk: () => {
                router.post(`/accounting/bank-reconciliation/${reconciliationId}/match`, {
                    statement_line_ids: selectedStatementLines.map((line) => line.id),
                    book_line_ids: selectedBookLines.map((line) => line.id),
                });
            },
        });
    };

    const unmatch = (matchId: number) => {
        Modal.confirm({
            title: 'Remove match group?',
            content: 'Lines in this group will return to unmatched status.',
            okType: 'danger',
            okText: 'Unmatch',
            onOk: () => {
                router.delete(`/accounting/bank-reconciliation/${reconciliationId}/match/${matchId}`);
            },
        });
    };

    return (
        <Card size="small" title="Matching">
            <Space direction="vertical" style={{ width: '100%' }} size="middle">
                <div>
                    <Typography.Text strong>Current selection</Typography.Text>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: token.paddingSM,
                            marginTop: token.marginXS,
                            fontSize: token.fontSizeSM,
                        }}
                    >
                        <div>
                            <div style={{ color: token.colorTextSecondary }}>Statement ({selectedStatementLines.length})</div>
                            <div style={{ fontFamily: token.fontFamilyCode }}>
                                <MoneyAmount amount={bankTotal} />
                            </div>
                        </div>
                        <div>
                            <div style={{ color: token.colorTextSecondary }}>Book ({selectedBookLines.length})</div>
                            <div style={{ fontFamily: token.fontFamilyCode }}>
                                <MoneyAmount amount={bookTotal} />
                            </div>
                        </div>
                    </div>
                    <div style={{ marginTop: token.marginXS, fontSize: token.fontSizeSM }}>
                        Combined net: <MoneyAmount amount={combined} />
                    </div>
                    <Space style={{ marginTop: token.marginSM }}>
                        <Button type="primary" disabled={!canReconcile || !canMatch} onClick={runMatch}>
                            Match
                        </Button>
                        {!canMatch && (selectedStatementLines.length > 0 || selectedBookLines.length > 0) && (
                            <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>
                                Match is disabled until the combined net is zero (currently{' '}
                                <MoneyAmount amount={combined} />).
                            </Typography.Text>
                        )}
                    </Space>
                </div>

                <div>
                    <Typography.Text strong>Existing match groups</Typography.Text>
                    {matchGroups.length === 0 ? (
                        <Typography.Paragraph type="secondary" style={{ marginBottom: 0, marginTop: token.marginXS }}>
                            No match groups yet. Select lines on both sides, then save a manual match or run auto-match.
                        </Typography.Paragraph>
                    ) : (
                        <List
                            size="small"
                            style={{ marginTop: token.marginXS }}
                            dataSource={matchGroups}
                            renderItem={(group) => (
                                <List.Item
                                    actions={
                                        canReconcile
                                            ? [
                                                  <Button
                                                      key="unmatch"
                                                      type="link"
                                                      size="small"
                                                      danger
                                                      onClick={() => unmatch(group.id)}
                                                  >
                                                      Unmatch
                                                  </Button>,
                                              ]
                                            : undefined
                                    }
                                >
                                    <List.Item.Meta
                                        title={`${group.match_type_label} — ${group.statement_line_ids.length} statement / ${group.book_line_ids.length} book`}
                                        description={
                                            <span style={{ fontFamily: token.fontFamilyCode, fontSize: token.fontSizeSM }}>
                                                Bank <MoneyAmount amount={group.bank_total} /> · Book{' '}
                                                <MoneyAmount amount={group.book_total} /> · Diff{' '}
                                                <MoneyAmount amount={group.difference} />
                                                {group.matched_by ? ` · ${group.matched_by}` : ''}
                                            </span>
                                        }
                                    />
                                </List.Item>
                            )}
                        />
                    )}
                </div>
            </Space>
        </Card>
    );
}
