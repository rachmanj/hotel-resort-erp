import { theme, Typography } from 'antd';
import { calculateTaxAmount, type TaxRuleForCalculation } from '@/lib/taxCalculator';

const formatIdr = (v: number) => `Rp ${v.toLocaleString('id-ID')}`;

interface FolioChargeTotalsPreviewProps {
    unitPrice: number;
    quantity: number;
    taxRules: TaxRuleForCalculation[];
}

export default function FolioChargeTotalsPreview({
    unitPrice,
    quantity,
    taxRules,
}: FolioChargeTotalsPreviewProps) {
    const { token } = theme.useToken();
    const totals = calculateTaxAmount(unitPrice, quantity, taxRules);

    const rows = [
        { label: 'Subtotal (price x pax)', value: totals.subtotal },
        { label: 'Service charge', value: totals.service_charge },
        { label: 'Tax', value: totals.tax },
        { label: 'Total to folio', value: totals.total, strong: true },
    ];

    return (
        <div
            style={{
                marginTop: 16,
                padding: 12,
                background: token.colorFillAlter,
                borderRadius: token.borderRadius,
            }}
        >
            {rows.map((row) => (
                <div
                    key={row.label}
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        marginBottom: 4,
                    }}
                >
                    <Typography.Text type={row.strong ? undefined : 'secondary'}>
                        {row.label}
                    </Typography.Text>
                    <Typography.Text strong={row.strong}>{formatIdr(row.value)}</Typography.Text>
                </div>
            ))}
        </div>
    );
}
