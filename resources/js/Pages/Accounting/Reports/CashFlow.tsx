import { Head, router } from '@inertiajs/react';
import { Card, Col, DatePicker, Row, Statistic } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface CashFlowProps {
    filters: { from: string; to: string };
    summary: {
        opening_cash: number;
        closing_cash: number;
        net_income: number;
        net_cash_change: number;
        investing_activities: number;
        financing_activities: number;
    };
}

export default function CashFlow({ filters, summary }: CashFlowProps) {
    return (
        <AuthenticatedLayout title="Cash Flow Statement">
            <Head title="Cash Flow" />
            <DatePicker.RangePicker
                value={[dayjs(filters.from), dayjs(filters.to)]}
                onChange={(dates) => router.get('/accounting/reports/cash-flow', {
                    from: dates?.[0]?.format('YYYY-MM-DD'),
                    to: dates?.[1]?.format('YYYY-MM-DD'),
                }, { preserveState: true })}
                style={{ marginBottom: 24 }}
            />
            <Row gutter={[16, 16]}>
                <Col span={8}>
                    <Card><Statistic title="Opening Cash" value={summary.opening_cash} prefix="Rp" precision={0} /></Card>
                </Col>
                <Col span={8}>
                    <Card><Statistic title="Net Income (Operating)" value={summary.net_income} prefix="Rp" precision={0} /></Card>
                </Col>
                <Col span={8}>
                    <Card><Statistic title="Closing Cash" value={summary.closing_cash} prefix="Rp" precision={0} /></Card>
                </Col>
                <Col span={8}>
                    <Card><Statistic title="Investing Activities" value={summary.investing_activities} prefix="Rp" precision={0} /></Card>
                </Col>
                <Col span={8}>
                    <Card><Statistic title="Financing Activities" value={summary.financing_activities} prefix="Rp" precision={0} /></Card>
                </Col>
                <Col span={8}>
                    <Card><Statistic title="Net Cash Change" value={summary.net_cash_change} prefix="Rp" precision={0} /></Card>
                </Col>
            </Row>
        </AuthenticatedLayout>
    );
}
