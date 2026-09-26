import { Card, Typography, theme } from 'antd';
import type { StatusPayload } from '../types';
import MoneyAmount from './MoneyAmount';

interface VariancePanelProps {
    statusPayload: StatusPayload;
}

export default function VariancePanel({ statusPayload }: VariancePanelProps) {
    const { token } = theme.useToken();

    const rows = [
        { label: 'Statement closing balance', value: statusPayload.statement_closing },
        { label: 'Deposits in transit', value: statusPayload.deposits_in_transit },
        { label: 'Outstanding checks', value: statusPayload.outstanding_checks },
        { label: 'Adjusted statement balance', value: statusPayload.adjusted_statement_balance },
        { label: 'Book closing balance', value: statusPayload.book_closing },
        { label: 'Cleared difference (matched lines)', value: statusPayload.difference },
        { label: 'Unexplained difference', value: statusPayload.unexplained_difference },
        {
            label: 'Cross-foot',
            value: statusPayload.cross_foot === null ? null : statusPayload.cross_foot ? 'Pass' : 'Fail',
            isText: true,
        },
    ];

    return (
        <Card size="small" title="Balance proof summary">
            <div style={{ display: 'flex', flexDirection: 'column', gap: token.paddingXS }}>
                {rows.map((row) => (
                    <div
                        key={row.label}
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            gap: token.paddingSM,
                            fontSize: token.fontSizeSM,
                        }}
                    >
                        <span style={{ color: token.colorTextSecondary }}>{row.label}</span>
                        <span style={{ fontFamily: token.fontFamilyCode, textAlign: 'right' }}>
                            {row.isText ? (
                                row.value
                            ) : row.value === null ? (
                                '—'
                            ) : (
                                <MoneyAmount amount={Number(row.value)} />
                            )}
                        </span>
                    </div>
                ))}
            </div>
            <Typography.Paragraph
                style={{
                    marginTop: token.marginMD,
                    marginBottom: 0,
                    color: statusPayload.is_balanced ? token.colorSuccess : token.colorWarning,
                }}
            >
                {statusPayload.is_balanced
                    ? 'Balanced — this session meets all reconciliation checks.'
                    : statusPayload.diagnostic}
            </Typography.Paragraph>
        </Card>
    );
}
