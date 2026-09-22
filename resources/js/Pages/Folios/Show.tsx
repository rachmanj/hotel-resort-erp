import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Descriptions, Form, Input, InputNumber, Modal, Select, Space, Table, Tag, Typography } from 'antd';
import { useState } from 'react';
import FolioChargeTotalsPreview from '@/components/FolioChargeTotalsPreview';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { newIdempotencyKey } from '@/lib/idempotency';
import type { TaxRuleForCalculation } from '@/lib/taxCalculator';

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
    revenueCategories: Array<{ id: number; code: string; name: string }>;
}

function computeDiveUnitPrice(
    pkg: FolioShowProps['divePackages'][number] | undefined,
    boatPrice: number | null,
    quantity: number,
): number {
    if (!pkg) {
        return 0;
    }

    if (pkg.type !== 'dive_package' || boatPrice === null) {
        return pkg.price_per_person;
    }

    const packageLine = pkg.price_per_person * quantity;

    return quantity > 0 ? (packageLine + boatPrice) / quantity : pkg.price_per_person;
}

const formatIdr = (v: number | string) => `Rp ${Number(v).toLocaleString('id-ID')}`;

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
    revenueCategories,
}: FolioShowProps) {
    const [chargeModalOpen, setChargeModalOpen] = useState(false);

    const paymentForm = useForm({
        amount: balance > 0 ? balance : 0,
        method: 'cash',
        reference_no: '',
    });

    const chargeForm = useForm({
        dive_package_id: null as number | null,
        dive_route_label: null as string | null,
        dive_boat_rate_item_id: null as number | null,
        revenue_category_id: null as number | null,
        description: '',
        quantity: 1,
        unit_price: 0,
    });

    const selectedDivePackage = divePackages.find((p) => p.id === chargeForm.data.dive_package_id);
    const requiresBoatRoute = selectedDivePackage?.type === 'dive_package';
    const selectedRoute = diveBoatRoutes.find((route) => route.label === chargeForm.data.dive_route_label);

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
            dive_package_id: null,
            dive_route_label: null,
            dive_boat_rate_item_id: null,
            revenue_category_id: null,
            description: '',
            quantity: 1,
            unit_price: 0,
        });
        setChargeModalOpen(true);
    };

    const applyDiveChargePricing = (
        pkg: FolioShowProps['divePackages'][number] | undefined,
        routeLabel: string | null,
        boatRateId: number | null,
        quantity: number,
    ) => {
        const route = diveBoatRoutes.find((item) => item.label === routeLabel);
        const boatPrice =
            boatRateId !== null
                ? route?.boat_prices.find((price) => price.id === boatRateId)?.price ?? null
                : null;

        if (pkg) {
            const description =
                pkg.type === 'dive_package' && route
                    ? `Dive: ${pkg.name} · ${route.route}`
                    : `Dive: ${pkg.name}`;
            chargeForm.setData('description', description);
            chargeForm.setData('unit_price', computeDiveUnitPrice(pkg, boatPrice, quantity));
        }
    };

    const onChargeDivePackageChange = (packageId: number | null) => {
        const pkg = packageId !== null ? divePackages.find((p) => p.id === packageId) : undefined;

        chargeForm.setData({
            dive_package_id: packageId,
            dive_route_label: null,
            dive_boat_rate_item_id: null,
        });

        if (pkg) {
            const diveCategory = revenueCategories.find((c) => c.code === 'dive_center');
            if (diveCategory) {
                chargeForm.setData('revenue_category_id', diveCategory.id);
            }
            applyDiveChargePricing(pkg, null, null, chargeForm.data.quantity);
        }
    };

    const onChargeDiveRouteChange = (routeLabel: string | null) => {
        chargeForm.setData({
            dive_route_label: routeLabel,
            dive_boat_rate_item_id: null,
        });
        applyDiveChargePricing(selectedDivePackage, routeLabel, null, chargeForm.data.quantity);
    };

    const onChargeBoatEngineChange = (boatRateId: number | null) => {
        chargeForm.setData('dive_boat_rate_item_id', boatRateId);
        applyDiveChargePricing(
            selectedDivePackage,
            chargeForm.data.dive_route_label,
            boatRateId,
            chargeForm.data.quantity,
        );
    };

    const onChargeQuantityChange = (quantity: number) => {
        chargeForm.setData('quantity', quantity);
        applyDiveChargePricing(
            selectedDivePackage,
            chargeForm.data.dive_route_label,
            chargeForm.data.dive_boat_rate_item_id,
            quantity,
        );
    };

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
                    <Form.Item label="Dive Package">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="Optional dive package"
                            value={chargeForm.data.dive_package_id ?? undefined}
                            options={divePackages.map((pkg) => ({
                                value: pkg.id,
                                label: `${pkg.code} · ${pkg.name} · ${formatIdr(pkg.price_per_person)}`,
                            }))}
                            onChange={(value) => onChargeDivePackageChange(value ?? null)}
                        />
                    </Form.Item>
                    {requiresBoatRoute && (
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
                            min={0}
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
