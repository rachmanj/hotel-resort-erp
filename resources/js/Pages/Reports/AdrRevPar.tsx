import { Head, router } from '@inertiajs/react';
import { Button, Card, Col, DatePicker, Row, Statistic } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface PeriodMetrics {
    room_revenue: number;
    rooms_sold: number;
    rooms_available: number;
    adr: number;
    revpar: number;
    occupancy_pct: number;
}

interface AdrRevParProps {
    report: {
        current: PeriodMetrics;
        comparison: PeriodMetrics;
        variance: { adr_pct: number | null; revpar_pct: number | null; occupancy_pct: number | null };
    };
    filters: { from: string; to: string };
}

function formatVariance(pct: number | null): string {
    if (pct === null) {
        return '—';
    }

    const sign = pct >= 0 ? '+' : '';

    return `${sign}${pct}%`;
}

export default function AdrRevPar({ report, filters }: AdrRevParProps) {
    const exportUrl = `/reports/adr-revpar?from=${filters.from}&to=${filters.to}&export=csv`;

    return (
        <AuthenticatedLayout title="ADR / RevPAR Report">
            <Head title="ADR / RevPAR" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 12, flexWrap: 'wrap', justifyContent: 'space-between' }}>
                <DatePicker.RangePicker
                    value={[dayjs(filters.from), dayjs(filters.to)]}
                    onChange={(dates) => router.get('/reports/adr-revpar', {
                        from: dates?.[0]?.format('YYYY-MM-DD'),
                        to: dates?.[1]?.format('YYYY-MM-DD'),
                    }, { preserveState: true })}
                />
                <Button href={exportUrl}>Export CSV</Button>
            </div>
            <Row gutter={16} style={{ marginBottom: 24 }}>
                <Col span={8}>
                    <Card title="Current Period">
                        <Statistic title="ADR" value={report.current.adr} prefix="Rp" />
                        <Statistic title="RevPAR" value={report.current.revpar} prefix="Rp" style={{ marginTop: 16 }} />
                        <Statistic title="Occupancy" value={report.current.occupancy_pct} suffix="%" style={{ marginTop: 16 }} />
                        <Statistic title="Room Revenue" value={report.current.room_revenue} prefix="Rp" style={{ marginTop: 16 }} />
                    </Card>
                </Col>
                <Col span={8}>
                    <Card title="Previous Period">
                        <Statistic title="ADR" value={report.comparison.adr} prefix="Rp" />
                        <Statistic title="RevPAR" value={report.comparison.revpar} prefix="Rp" style={{ marginTop: 16 }} />
                        <Statistic title="Occupancy" value={report.comparison.occupancy_pct} suffix="%" style={{ marginTop: 16 }} />
                        <Statistic title="Room Revenue" value={report.comparison.room_revenue} prefix="Rp" style={{ marginTop: 16 }} />
                    </Card>
                </Col>
                <Col span={8}>
                    <Card title="Variance vs Previous">
                        <Statistic title="ADR" value={formatVariance(report.variance.adr_pct)} />
                        <Statistic title="RevPAR" value={formatVariance(report.variance.revpar_pct)} style={{ marginTop: 16 }} />
                        <Statistic title="Occupancy" value={formatVariance(report.variance.occupancy_pct)} style={{ marginTop: 16 }} />
                    </Card>
                </Col>
            </Row>
        </AuthenticatedLayout>
    );
}
