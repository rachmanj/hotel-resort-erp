import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Descriptions, Form, Input, InputNumber, Modal, Select, Space, Table, Tag, Typography } from 'antd';
import { useState } from 'react';
import FolioChargeTotalsPreview from '@/components/FolioChargeTotalsPreview';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { newIdempotencyKey } from '@/lib/idempotency';
import { coerceFiniteNumber, type TaxRuleForCalculation } from '@/lib/taxCalculator';

interface FolioShowProps {
    folio: {
        id: number;
        folio_no: string;
        status: string;
        status_label: string;
        type: string;
        opened_at?: string;
        closed_at?: string | null;
        guest?: { id: number; full_name: string; phone?: string; email?: string };
        reservation?: {
            id: number;
            reservation_code: string;
            promotion?: { name: string; discount_summary: string } | null;
            promotion_redemptions?: Array<{
                promotion_name?: string;
                code?: string;
                discount_amount: string;
            }>;
        };
        company?: { id: number; name: string } | null;
        items: Array<{
            id: number;
            item_type_label: string;
            description: string;
            quantity: string;
            unit_price: string;
            amount: string;
            dpp_amount: number;
            tax_amount: string;
            service_charge_amount: string;
            is_tax_inclusive: boolean;
            line_total: number;
            posted_at?: string;
            posted_by?: { name: string } | null;
        }>;
        payments: Array<{
            id: number;
            amount: string;
            method_label: string;
            reference_no?: string;
            paid_at?: string;
            received_by?: { name: string } | null;
            is_refund: boolean;
        }>;
    };
    balance: number;
    charges_total: number;
    payments_total: number;
    paymentMethods: Array<{ value: string; label: string }>;
    canPostPayment: boolean;
    canPostCharge: boolean;
    canViewInvoice: boolean;
    canViewGuestInvoice: boolean;
    miscChargeTaxRules: TaxRuleForCalculation[];
    divePackages: Array<{
        id: number;
        code: string;
        name: string;
        type: string;
        price_per_person: number;
    }>;
    diveBoatRoutes: Array<{
        route: string;
        dive_spots: string;
        label: string;
        boat_prices: Array<{
            id: number;
            boat_engine_option: string;
            boat_engine_label: string;
            price: number;
        }>;
    }>;
    dailyTripDestinations: Array<{
        destination: string;
        label: string;
        boat_prices: Array<{
            id: number;
            boat_class: string;
            boat_class_label: string;
            price: number;
        }>;
    }>;
    dailyTripRentals: Array<{ id: number; code: string; name: string; price: number }>;
    dailyTripGuide: { id: number; code: string; name: string; price: number } | null;
    guideChargeUnits: Array<{ value: string; label: string }>;
    revenueCategories: Array<{ id: number; code: string; name: string }>;
}

type GuideChargeUnitValue = 'per_day' | 'half_day' | 'per_trip';

type ChargeItemGroup =
    | 'manual'
    | 'dive_package'
    | 'daily_trip'
    | 'daily_trip_rental'
    | 'guide'
    | 'car_rental';

const CHARGE_ITEM_GROUPS: Array<{ value: ChargeItemGroup; label: string }> = [
    { value: 'manual', label: 'Manual charge' },
    { value: 'dive_package', label: 'Dive package' },
    { value: 'daily_trip', label: 'Daily trip (speedboat)' },
    { value: 'daily_trip_rental', label: 'Daily trip rental' },
    { value: 'guide', label: 'Guide (additional)' },
    {
        value: 'car_rental',
        label: 'Car rental (price on agreement)',
    },
];

function computeDiveUnitPrice(
    pkg: FolioShowProps['divePackages'][number] | undefined,
    boatPrice: number | null,
    quantity: number | null | undefined,
): number {
    const qty = coerceFiniteNumber(quantity);
    const perPerson = coerceFiniteNumber(pkg?.price_per_person);
    const safeBoatPrice =
        boatPrice !== null && Number.isFinite(boatPrice) ? boatPrice : null;

    if (!pkg) {
        return 0;
    }

    if (pkg.type !== 'dive_package' || safeBoatPrice === null) {
        return perPerson;
    }

    const packageLine = perPerson * qty;

    return qty > 0 ? (packageLine + safeBoatPrice) / qty : perPerson;
}

const formatIdr = (v: number | string) => `Rp ${Number(v).toLocaleString('id-ID')}`;

const GUIDE_UNIT_PHRASES: Record<GuideChargeUnitValue, string> = {
    per_day: 'per day',
    half_day: 'half day',
    per_trip: 'per trip',
};

function buildGuideChargeDescription(rateItemName: string, unit: GuideChargeUnitValue): string {
    const base = rateItemName.replace(/\s*\(per day\)\s*$/i, '').trim();

    return `${base} (${GUIDE_UNIT_PHRASES[unit]})`;
}

type DailyTripGuideRate = NonNullable<FolioShowProps['dailyTripGuide']>;

function guideChargeFieldsForUnit(
    dailyTripGuide: DailyTripGuideRate,
    unit: GuideChargeUnitValue,
): {
    guide_unit: GuideChargeUnitValue;
    rate_item_id: number;
    description: string;
    unit_price: number;
} {
    return {
        guide_unit: unit,
        rate_item_id: dailyTripGuide.id,
        description: buildGuideChargeDescription(dailyTripGuide.name, unit),
        unit_price: unit === 'per_day' ? dailyTripGuide.price : 0,
    };
}

function rateItemPricingFields(
    rateItemId: number | null,
    quantity: number,
    selectedDailyTripDestination: FolioShowProps['dailyTripDestinations'][number] | undefined,
    dailyTripRentals: FolioShowProps['dailyTripRentals'],
    dailyTripGuide: FolioShowProps['dailyTripGuide'],
    fallbackDescription: string,
): { description: string; unit_price: number; quantity: number; rate_item_id: number } | null {
    if (rateItemId === null) {
        return null;
    }

    const fromDestination = selectedDailyTripDestination?.boat_prices.find((price) => price.id === rateItemId);
    const fromRental = dailyTripRentals.find((rental) => rental.id === rateItemId);
    const fromGuide = dailyTripGuide?.id === rateItemId ? dailyTripGuide : undefined;
    const rate = fromDestination ?? fromRental ?? fromGuide;

    if (!rate) {
        return null;
    }

    const description =
        'name' in rate && typeof rate.name === 'string' ? rate.name : fallbackDescription;

    return {
        description,
        unit_price: rate.price,
        quantity,
        rate_item_id: rateItemId,
    };
}

function diveChargePricingFields(
    pkg: FolioShowProps['divePackages'][number] | undefined,
    routeLabel: string | null,
    boatRateId: number | null,
    quantity: number,
    diveBoatRoutes: FolioShowProps['diveBoatRoutes'],
): { description: string; unit_price: number } | null {
    if (!pkg) {
        return null;
    }

    const route = diveBoatRoutes.find((item) => item.label === routeLabel);
    const boatPrice =
        boatRateId !== null
            ? route?.boat_prices.find((price) => price.id === boatRateId)?.price ?? null
            : null;
    const description =
        pkg.type === 'dive_package' && route
            ? `Dive: ${pkg.name} · ${route.route}`
            : `Dive: ${pkg.name}`;

    return {
        description,
        unit_price: computeDiveUnitPrice(pkg, boatPrice, quantity),
    };
}

export default function FolioShow({
    folio,
    balance,
    charges_total,
    payments_total,
    paymentMethods,
    canPostPayment,
    canPostCharge,
    canViewInvoice,
    canViewGuestInvoice,
    miscChargeTaxRules,
    divePackages,
    diveBoatRoutes,
    dailyTripDestinations,
    dailyTripRentals,
    dailyTripGuide,
    guideChargeUnits,
    revenueCategories,
}: FolioShowProps) {
    const [chargeModalOpen, setChargeModalOpen] = useState(false);

    const paymentForm = useForm({
        amount: balance > 0 ? balance : 0,
        method: 'cash',
        reference_no: '',
    });

    const chargeForm = useForm({
        charge_item_group: 'manual' as ChargeItemGroup,
        dive_package_id: null as number | null,
        dive_route_label: null as string | null,
        dive_boat_rate_item_id: null as number | null,
        daily_trip_destination_label: null as string | null,
        rate_item_id: null as number | null,
        guide_unit: 'per_day' as GuideChargeUnitValue,
        revenue_category_id: null as number | null,
        description: '',
        quantity: 1,
        unit_price: 0,
    });

    const selectedDivePackage = divePackages.find((p) => p.id === chargeForm.data.dive_package_id);
    const requiresBoatRoute = selectedDivePackage?.type === 'dive_package';
    const selectedRoute = diveBoatRoutes.find((route) => route.label === chargeForm.data.dive_route_label);
    const selectedDailyTripDestination = dailyTripDestinations.find(
        (destination) => destination.label === chargeForm.data.daily_trip_destination_label,
    );
    const chargeItemGroup = chargeForm.data.charge_item_group;

    const submitPayment = () => {
        paymentForm.post(`/folios/${folio.id}/payments`, {
            headers: { 'X-Idempotency-Key': newIdempotencyKey() },
            preserveScroll: true,
            onSuccess: () => paymentForm.reset('reference_no'),
        });
    };

    const openChargeModal = () => {
        chargeForm.reset();
        chargeForm.setData({
            charge_item_group: 'manual',
            dive_package_id: null,
            dive_route_label: null,
            dive_boat_rate_item_id: null,
            daily_trip_destination_label: null,
            rate_item_id: null,
            guide_unit: 'per_day',
            revenue_category_id: null,
            description: '',
            quantity: 1,
            unit_price: 0,
        });
        setChargeModalOpen(true);
    };

    const chargeGroupResetFields = (group: ChargeItemGroup) => ({
        charge_item_group: group,
        dive_package_id: null,
        dive_route_label: null,
        dive_boat_rate_item_id: null,
        daily_trip_destination_label: null,
        rate_item_id: null,
        guide_unit: 'per_day' as GuideChargeUnitValue,
        revenue_category_id: null,
        description: '',
        quantity: 1,
        unit_price: 0,
    });

    const onChargeItemGroupChange = (group: ChargeItemGroup) => {
        const reset = chargeGroupResetFields(group);

        if (group === 'guide' && dailyTripGuide) {
            chargeForm.setData({
                ...reset,
                ...guideChargeFieldsForUnit(dailyTripGuide, 'per_day'),
            });

            return;
        }

        if (group === 'car_rental') {
            const transportCar = revenueCategories.find((category) => category.code === 'transport_car');
            chargeForm.setData({
                ...reset,
                revenue_category_id: transportCar?.id ?? null,
                description: '',
                unit_price: 0,
            });

            return;
        }

        chargeForm.setData(reset);
    };

    const onGuideUnitChange = (unit: GuideChargeUnitValue) => {
        if (!dailyTripGuide) {
            return;
        }

        chargeForm.setData({
            ...chargeForm.data,
            ...guideChargeFieldsForUnit(dailyTripGuide, unit),
        });
    };

    const onDailyTripDestinationChange = (destinationLabel: string | null) => {
        chargeForm.setData({
            ...chargeForm.data,
            daily_trip_destination_label: destinationLabel,
            rate_item_id: null,
            unit_price: 0,
            description: '',
        });
    };

    const onDailyTripBoatClassChange = (rateItemId: number | null) => {
        const destination = dailyTripDestinations.find(
            (item) => item.label === chargeForm.data.daily_trip_destination_label,
        );
        const boatPrice = destination?.boat_prices.find((price) => price.id === rateItemId);
        const pricing = rateItemPricingFields(
            rateItemId,
            chargeForm.data.quantity,
            destination,
            dailyTripRentals,
            dailyTripGuide,
            chargeForm.data.description,
        );
        const description =
            destination && boatPrice
                ? `Daily Trip: ${destination.destination} · ${boatPrice.boat_class_label}`
                : (pricing?.description ?? chargeForm.data.description);

        chargeForm.setData({
            ...chargeForm.data,
            rate_item_id: rateItemId,
            ...(pricing ?? {}),
            description,
        });
    };

    const onDailyTripRentalChange = (rateItemId: number | null) => {
        const pricing = rateItemPricingFields(
            rateItemId,
            chargeForm.data.quantity,
            selectedDailyTripDestination,
            dailyTripRentals,
            dailyTripGuide,
            chargeForm.data.description,
        );

        chargeForm.setData({
            ...chargeForm.data,
            rate_item_id: rateItemId,
            ...(pricing ?? {}),
        });
    };

    const onChargeDivePackageChange = (packageId: number | null) => {
        const pkg = packageId !== null ? divePackages.find((p) => p.id === packageId) : undefined;
        const diveCategory = pkg ? revenueCategories.find((c) => c.code === 'dive_center') : undefined;
        const pricing = diveChargePricingFields(pkg, null, null, chargeForm.data.quantity, diveBoatRoutes);

        chargeForm.setData({
            ...chargeForm.data,
            charge_item_group: 'dive_package',
            dive_package_id: packageId,
            dive_route_label: null,
            dive_boat_rate_item_id: null,
            daily_trip_destination_label: null,
            rate_item_id: null,
            revenue_category_id: diveCategory?.id ?? chargeForm.data.revenue_category_id,
            ...(pricing ?? {}),
        });
    };

    const onChargeDiveRouteChange = (routeLabel: string | null) => {
        const pricing = diveChargePricingFields(
            selectedDivePackage,
            routeLabel,
            null,
            chargeForm.data.quantity,
            diveBoatRoutes,
        );

        chargeForm.setData({
            ...chargeForm.data,
            dive_route_label: routeLabel,
            dive_boat_rate_item_id: null,
            ...(pricing ?? {}),
        });
    };

    const onChargeBoatEngineChange = (boatRateId: number | null) => {
        const pricing = diveChargePricingFields(
            selectedDivePackage,
            chargeForm.data.dive_route_label,
            boatRateId,
            chargeForm.data.quantity,
            diveBoatRoutes,
        );

        chargeForm.setData({
            ...chargeForm.data,
            dive_boat_rate_item_id: boatRateId,
            ...(pricing ?? {}),
        });
    };

    const onChargeQuantityChange = (quantity: number) => {
        if (chargeItemGroup === 'dive_package') {
            const pricing = diveChargePricingFields(
                selectedDivePackage,
                chargeForm.data.dive_route_label,
                chargeForm.data.dive_boat_rate_item_id,
                quantity,
                diveBoatRoutes,
            );

            chargeForm.setData({
                ...chargeForm.data,
                quantity,
                ...(pricing ?? {}),
            });

            return;
        }

        if (chargeItemGroup === 'daily_trip' || chargeItemGroup === 'daily_trip_rental') {
            const pricing = rateItemPricingFields(
                chargeForm.data.rate_item_id,
                quantity,
                selectedDailyTripDestination,
                dailyTripRentals,
                dailyTripGuide,
                chargeForm.data.description,
            );

            chargeForm.setData({
                ...chargeForm.data,
                quantity,
                ...(pricing ?? {}),
            });

            return;
        }

        if (chargeItemGroup === 'guide' && dailyTripGuide) {
            chargeForm.setData({
                ...chargeForm.data,
                quantity,
                ...guideChargeFieldsForUnit(dailyTripGuide, chargeForm.data.guide_unit),
            });

            return;
        }

        chargeForm.setData('quantity', quantity);
    };

    const guideUnit = chargeForm.data.guide_unit;
    const guideUnitPriceLocked = chargeItemGroup === 'guide' && guideUnit === 'per_day';
    const guideOperatorSetsUnitPrice =
        chargeItemGroup === 'guide' && (guideUnit === 'half_day' || guideUnit === 'per_trip');

    const submitCharge = () => {
        chargeForm.post(`/folios/${folio.id}/charges`, {
            headers: { 'X-Idempotency-Key': newIdempotencyKey() },
            preserveScroll: true,
            onSuccess: () => setChargeModalOpen(false),
        });
    };

    return (
        <AuthenticatedLayout title={`Folio ${folio.folio_no}`}>
            <Head title={folio.folio_no} />
            <Space style={{ marginBottom: 16 }} wrap>
                {folio.reservation && (
                    <Link href={`/reservations/${folio.reservation.id}`}>
                        <Button>Back to Reservation</Button>
                    </Link>
                )}
                {canViewInvoice && (
                    <>
                        <Link href={`/folios/${folio.id}/invoice`}>
                            <Button>View Invoice</Button>
                        </Link>
                        <a href={`/folios/${folio.id}/invoice/download`}>
                            <Button>Download PDF</Button>
                        </a>
                    </>
                )}
                {canViewGuestInvoice && (
                    <Link href={`/folios/${folio.id}/guest-invoice`}>
                        <Button>Guest Invoice</Button>
                    </Link>
                )}
            </Space>

            <Descriptions bordered column={2} size="small" style={{ marginBottom: 24 }}>
                <Descriptions.Item label="Folio No">{folio.folio_no}</Descriptions.Item>
                <Descriptions.Item label="Status">
                    <Tag color={folio.status === 'open' ? 'green' : 'default'}>{folio.status_label}</Tag>
                </Descriptions.Item>
                <Descriptions.Item label="Guest">{folio.guest?.full_name}</Descriptions.Item>
                <Descriptions.Item label="Reservation">{folio.reservation?.reservation_code}</Descriptions.Item>
                {folio.reservation?.promotion && (
                    <Descriptions.Item label="Promotion" span={2}>
                        <Tag color="green">
                            {folio.reservation.promotion.name} ·{' '}
                            {folio.reservation.promotion.discount_summary}
                        </Tag>
                    </Descriptions.Item>
                )}
                {folio.company && (
                    <Descriptions.Item label="Company" span={2}>{folio.company.name}</Descriptions.Item>
                )}
                <Descriptions.Item label="Charges">{formatIdr(charges_total)}</Descriptions.Item>
                <Descriptions.Item label="Payments">{formatIdr(payments_total)}</Descriptions.Item>
                <Descriptions.Item label="Balance" span={2}>
                    <strong style={{ fontSize: 16, color: balance > 0 ? '#cf1322' : '#389e0d' }}>
                        {formatIdr(balance)}
                    </strong>
                </Descriptions.Item>
            </Descriptions>

            <h3>Line Items</h3>
            <Typography.Paragraph type="secondary">
                Room and F&amp;B prices already include service charge and PBJT. The DPP, SC and PBJT columns are
                the split carved out of the price for reporting and never change what the guest pays.
            </Typography.Paragraph>
            <Table
                rowKey="id"
                size="small"
                pagination={false}
                style={{ marginBottom: 24 }}
                dataSource={folio.items}
                columns={[
                    { title: 'Type', dataIndex: 'item_type_label' },
                    { title: 'Description', dataIndex: 'description' },
                    { title: 'Qty', dataIndex: 'quantity', render: (v) => Number(v) },
                    { title: 'Unit Price', dataIndex: 'unit_price', render: formatIdr },
                    { title: 'Amount', dataIndex: 'amount', render: formatIdr },
                    {
                        title: 'DPP',
                        dataIndex: 'dpp_amount',
                        render: (v, record) => (record.is_tax_inclusive ? formatIdr(v) : '–'),
                    },
                    {
                        title: 'SC',
                        dataIndex: 'service_charge_amount',
                        render: (v, record) => (record.is_tax_inclusive ? formatIdr(v) : '–'),
                    },
                    {
                        title: 'PBJT',
                        dataIndex: 'tax_amount',
                        render: (v, record) => (record.is_tax_inclusive ? formatIdr(v) : '–'),
                    },
                    { title: 'Total', dataIndex: 'line_total', render: formatIdr },
                ]}
            />

            <h3>Payments</h3>
            <Table
                rowKey="id"
                size="small"
                pagination={false}
                style={{ marginBottom: 24 }}
                dataSource={folio.payments}
                columns={[
                    { title: 'Date', dataIndex: 'paid_at' },
                    { title: 'Method', dataIndex: 'method_label' },
                    { title: 'Reference', dataIndex: 'reference_no', render: (v) => v ?? '–' },
                    { title: 'Amount', dataIndex: 'amount', render: formatIdr },
                    { title: 'Received By', dataIndex: ['received_by', 'name'] },
                ]}
            />

            {canPostPayment && (
                <>
                    <h3>Post Payment</h3>
                    <Form layout="inline" onFinish={submitPayment} style={{ gap: 8 }}>
                        <Form.Item label="Amount">
                            <InputNumber
                                min={0}
                                value={paymentForm.data.amount}
                                onChange={(v) => paymentForm.setData('amount', v ?? 0)}
                                formatter={(v) => `${v}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                            />
                        </Form.Item>
                        <Form.Item label="Method">
                            <Select
                                style={{ width: 160 }}
                                value={paymentForm.data.method}
                                onChange={(v) => paymentForm.setData('method', v)}
                                options={paymentMethods}
                            />
                        </Form.Item>
                        <Form.Item label="Reference">
                            <Input
                                value={paymentForm.data.reference_no}
                                onChange={(e) => paymentForm.setData('reference_no', e.target.value)}
                            />
                        </Form.Item>
                        <Form.Item>
                            <Space>
                                {canPostCharge && (
                                    <Button onClick={openChargeModal}>Add Charge</Button>
                                )}
                                <Button type="primary" htmlType="submit" loading={paymentForm.processing}>
                                    Post Payment
                                </Button>
                            </Space>
                        </Form.Item>
                    </Form>
                </>
            )}

            {canPostCharge && !canPostPayment && (
                <div style={{ marginTop: 16 }}>
                    <Button type="primary" onClick={openChargeModal}>Add Charge</Button>
                </div>
            )}

            <Modal
                title="Add Charge"
                open={chargeModalOpen}
                onCancel={() => setChargeModalOpen(false)}
                onOk={submitCharge}
                confirmLoading={chargeForm.processing}
                okText="Save"
                width={560}
            >
                <Form layout="vertical">
                    <Form.Item label="Charge type">
                        <Select
                            value={chargeForm.data.charge_item_group}
                            options={CHARGE_ITEM_GROUPS}
                            onChange={(value) => onChargeItemGroupChange(value as ChargeItemGroup)}
                        />
                    </Form.Item>
                    {chargeItemGroup === 'dive_package' && (
                    <Form.Item label="Dive Package" required>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            placeholder="Select dive package"
                            value={chargeForm.data.dive_package_id ?? undefined}
                            options={divePackages.map((pkg) => ({
                                value: pkg.id,
                                label: `${pkg.code} · ${pkg.name} · ${formatIdr(pkg.price_per_person)}`,
                            }))}
                            onChange={(value) => onChargeDivePackageChange(value ?? null)}
                        />
                    </Form.Item>
                    )}
                    {chargeItemGroup === 'dive_package' && requiresBoatRoute && (
                        <>
                            <Form.Item label="Boat Route" required>
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    placeholder="Select dive route"
                                    value={chargeForm.data.dive_route_label ?? undefined}
                                    options={diveBoatRoutes.map((route) => ({
                                        value: route.label,
                                        label: route.label,
                                    }))}
                                    onChange={(value) => onChargeDiveRouteChange(value ?? null)}
                                />
                            </Form.Item>
                            {selectedRoute && (
                                <Form.Item label="Boat Engine" required>
                                    <Select
                                        placeholder="Select boat engine option"
                                        value={chargeForm.data.dive_boat_rate_item_id ?? undefined}
                                        options={selectedRoute.boat_prices.map((price) => ({
                                            value: price.id,
                                            label: `${price.boat_engine_label} · ${formatIdr(price.price)}`,
                                        }))}
                                        onChange={(value) => onChargeBoatEngineChange(value ?? null)}
                                    />
                                </Form.Item>
                            )}
                        </>
                    )}
                    {chargeItemGroup === 'daily_trip' && (
                        <>
                            <Form.Item label="Destination" required>
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    placeholder="Select trip destination"
                                    value={chargeForm.data.daily_trip_destination_label ?? undefined}
                                    options={dailyTripDestinations.map((destination) => ({
                                        value: destination.label,
                                        label: destination.label,
                                    }))}
                                    onChange={(value) => onDailyTripDestinationChange(value ?? null)}
                                />
                            </Form.Item>
                            {selectedDailyTripDestination && (
                                <Form.Item label="Boat class" required>
                                    <Select
                                        placeholder="Select boat class"
                                        value={chargeForm.data.rate_item_id ?? undefined}
                                        options={selectedDailyTripDestination.boat_prices.map((price) => ({
                                            value: price.id,
                                            label: `${price.boat_class_label} · ${formatIdr(price.price)}`,
                                        }))}
                                        onChange={(value) => onDailyTripBoatClassChange(value ?? null)}
                                    />
                                </Form.Item>
                            )}
                        </>
                    )}
                    {chargeItemGroup === 'daily_trip_rental' && (
                        <Form.Item label="Rental item" required>
                            <Select
                                showSearch
                                optionFilterProp="label"
                                placeholder="Select rental equipment"
                                value={chargeForm.data.rate_item_id ?? undefined}
                                options={dailyTripRentals.map((rental) => ({
                                    value: rental.id,
                                    label: `${rental.name} · ${formatIdr(rental.price)}`,
                                }))}
                                onChange={(value) => onDailyTripRentalChange(value ?? null)}
                            />
                        </Form.Item>
                    )}
                    {chargeItemGroup === 'guide' && dailyTripGuide && (
                        <>
                            <Form.Item label="Guide">
                                <Typography.Text>
                                    {dailyTripGuide.name} · {formatIdr(dailyTripGuide.price)} per day (list)
                                </Typography.Text>
                            </Form.Item>
                            <Form.Item label="Billing unit" required>
                                <Select
                                    value={chargeForm.data.guide_unit}
                                    options={guideChargeUnits}
                                    onChange={(value) =>
                                        onGuideUnitChange(value as GuideChargeUnitValue)
                                    }
                                />
                            </Form.Item>
                        </>
                    )}
                    {chargeItemGroup === 'car_rental' && (
                        <Typography.Paragraph type="secondary" style={{ marginBottom: 16 }}>
                            No fixed price list — enter the agreed amount and describe the car type, route, and
                            duration.
                        </Typography.Paragraph>
                    )}
                    <Form.Item label="Revenue Category">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="Select revenue category"
                            value={chargeForm.data.revenue_category_id ?? undefined}
                            options={revenueCategories.map((category) => ({
                                value: category.id,
                                label: `${category.code} · ${category.name}`,
                            }))}
                            onChange={(value) =>
                                chargeForm.setData('revenue_category_id', value ?? null)
                            }
                        />
                    </Form.Item>
                    <Form.Item label="Description" required>
                        <Input
                            value={chargeForm.data.description}
                            placeholder={
                                chargeItemGroup === 'car_rental'
                                    ? 'e.g. Avanza, Tanjung Batu–Berau, 1 day'
                                    : undefined
                            }
                            onChange={(e) => chargeForm.setData('description', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Quantity" required>
                        <InputNumber
                            min={0.01}
                            style={{ width: '100%' }}
                            value={chargeForm.data.quantity}
                            onChange={(v) => onChargeQuantityChange(v ?? 1)}
                        />
                    </Form.Item>
                    <Form.Item label="Unit Price" required>
                        <InputNumber
                            min={
                                chargeItemGroup === 'car_rental' || guideOperatorSetsUnitPrice ? 0.01 : 0
                            }
                            disabled={guideUnitPriceLocked}
                            style={{ width: '100%' }}
                            value={chargeForm.data.unit_price}
                            onChange={(v) => chargeForm.setData('unit_price', v ?? 0)}
                        />
                    </Form.Item>
                    <FolioChargeTotalsPreview
                        unitPrice={chargeForm.data.unit_price}
                        quantity={chargeForm.data.quantity}
                        taxRules={miscChargeTaxRules}
                    />
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
