import { theme, Typography } from 'antd';
import { extractInclusiveTax, type TaxRuleForCalculation } from '@/lib/taxCalculator';

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
    const split = extractInclusiveTax(unitPrice, quantity, taxRules);
    const isInclusive = taxRules.length > 0;

    const rows = isInclusive
        ? [
              { label: 'DPP (excl. SC & PBJT)', value: split.dpp },
              { label: 'Service charge (included)', value: split.service_charge },
              { label: 'PBJT (included)', value: split.tax },
              { label: 'Total to folio', value: split.total, strong: true },
          ]
        : [{ label: 'Total to folio', value: split.total, strong: true }];

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
