import type { CSSProperties } from 'react';
import { formatCompactIdr, formatFullIdr } from '../formatMoney';

interface MoneyAmountProps {
    amount: number;
    compact?: boolean;
    style?: CSSProperties;
    className?: string;
}

export default function MoneyAmount({ amount, compact = true, style, className }: MoneyAmountProps) {
    const display = compact ? formatCompactIdr(amount) : formatFullIdr(amount);

    return (
        <span title={formatFullIdr(amount)} style={style} className={className}>
            {display}
        </span>
    );
}
