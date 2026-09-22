export const SERVICE_CHARGE_CODE = 'service_charge';

export interface TaxRuleForCalculation {
    code: string;
    rate_percent: number;
    is_compounding: boolean;
}

export interface TaxCalculationResult {
    subtotal: number;
    service_charge: number;
    tax: number;
    total: number;
}

export interface InclusiveTaxSplit {
    dpp: number;
    service_charge: number;
    tax: number;
    total: number;
}

function round2(value: number): number {
    return Math.round(value * 100) / 100;
}

export function calculateTaxAmount(
    unitPrice: number,
    quantity: number,
    rules: TaxRuleForCalculation[],
): TaxCalculationResult {
    const lineSubtotal = round2(unitPrice * quantity);

    return calculateTaxFromSubtotal(lineSubtotal, rules);
}

export function calculateTaxFromSubtotal(
    subtotal: number,
    rules: TaxRuleForCalculation[],
): TaxCalculationResult {
    const roundedSubtotal = round2(subtotal);
    let serviceCharge = 0;
    let tax = 0;
    let runningBase = roundedSubtotal;

    for (const rule of rules) {
        const rate = rule.rate_percent / 100;

        if (rule.code === SERVICE_CHARGE_CODE) {
            serviceCharge = round2(roundedSubtotal * rate);
            runningBase = roundedSubtotal + serviceCharge;
        } else if (rule.is_compounding) {
            tax = round2(runningBase * rate);
        } else {
            tax += round2(roundedSubtotal * rate);
        }
    }

    return {
        subtotal: roundedSubtotal,
        service_charge: serviceCharge,
        tax,
        total: round2(roundedSubtotal + serviceCharge + tax),
    };
}

/**
 * Mirrors TaxAmountCalculator::inclusiveFactor() — the multiplier that turns a DPP
 * into the price list amount.
 */
export function inclusiveTaxFactor(rules: TaxRuleForCalculation[]): number {
    let serviceChargeFactor = 0;
    let taxFactor = 0;
    let runningFactor = 1;

    for (const rule of rules) {
        const rate = rule.rate_percent / 100;

        if (rule.code === SERVICE_CHARGE_CODE) {
            serviceChargeFactor = rate;
            runningFactor = 1 + rate;
        } else if (rule.is_compounding) {
            taxFactor = runningFactor * rate;
        } else {
            taxFactor += rate;
        }
    }

    return 1 + serviceChargeFactor + taxFactor;
}

/**
 * Mirrors TaxAmountCalculator::extractInclusive(). The total is always the price
 * list amount handed in, never the sum of the rounded parts.
 */
export function extractInclusiveTax(
    unitPrice: number,
    quantity: number,
    rules: TaxRuleForCalculation[],
): InclusiveTaxSplit {
    const total = round2(unitPrice * quantity);
    const dpp = round2(total / inclusiveTaxFactor(rules));
    const forward = calculateTaxFromSubtotal(dpp, rules);

    return {
        dpp,
        service_charge: forward.service_charge,
        tax: forward.tax,
        total,
    };
}
