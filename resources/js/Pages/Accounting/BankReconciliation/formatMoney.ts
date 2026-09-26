export function formatFullIdr(amount: number): string {
    return `Rp ${amount.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function formatCompactIdr(amount: number): string {
    const abs = Math.abs(amount);
    const sign = amount < 0 ? '-' : '';

    const formatNum = (value: number): string =>
        value.toLocaleString('id-ID', {
            maximumFractionDigits: 1,
            minimumFractionDigits: Number.isInteger(value) ? 0 : 1,
        });

    if (abs >= 1_000_000_000) {
        const billions = abs / 1_000_000_000;

        return `Rp ${sign}${formatNum(billions)} M`;
    }

    if (abs >= 1_000_000) {
        const millions = abs / 1_000_000;

        return `Rp ${sign}${formatNum(millions)} jt`;
    }

    if (abs >= 1_000) {
        const thousands = abs / 1_000;

        return `Rp ${sign}${formatNum(thousands)} rb`;
    }

    return formatFullIdr(amount);
}
