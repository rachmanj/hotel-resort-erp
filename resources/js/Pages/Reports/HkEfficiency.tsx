import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Card, Col, DatePicker, Row, Statistic } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface HousekeeperRow {
    housekeeper_id: number;
    housekeeper_name: string;
    rooms_assigned: number;
    rooms_completed: number;
    avg_clean_minutes: number | null;
}

interface HkEfficiencyProps {
    report: {
        avg_clean_minutes: number | null;
        inspection_pass_rate: number | null;
        by_housekeeper: HousekeeperRow[];
        totals: {
            rooms_assigned: number;
            rooms_completed: number;
            inspections_passed: number;
            rooms_cleaned: number;
        };
    };
    filters: { from: string; to: string };
}

export default function HkEfficiency({ report, filters }: HkEfficiencyProps) {
    const columns: ProColumns<HousekeeperRow>[] = [
        { title: 'Housekeeper', dataIndex: 'housekeeper_name' },
        { title: 'Rooms Assigned', dataIndex: 'rooms_assigned', align: 'right' },
        { title: 'Rooms Completed', dataIndex: 'rooms_completed', align: 'right' },
        {
            title: 'Avg Clean (min)',
            dataIndex: 'avg_clean_minutes',
            render: (v) => v !== null && v !== undefined ? Number(v).toFixed(1) : '–',
            align: 'right',
        },
    ];

    const exportUrl = `/reports/hk-efficiency?from=${filters.from}&to=${filters.to}&export=csv`;

    return (
        <AuthenticatedLayout title="Housekeeping Efficiency">
            <Head title="HK Efficiency" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 12, flexWrap: 'wrap', justifyContent: 'space-between' }}>
                <DatePicker.RangePicker
                    value={[dayjs(filters.from), dayjs(filters.to)]}
                    onChange={(dates) => router.get('/reports/hk-efficiency', {
                        from: dates?.[0]?.format('YYYY-MM-DD'),
                        to: dates?.[1]?.format('YYYY-MM-DD'),
                    }, { preserveState: true })}
                />
                <Button href={exportUrl}>Export CSV</Button>
            </div>
            <Row gutter={16} style={{ marginBottom: 24 }}>
                <Col span={6}>
                    <Card>
                        <Statistic
                            title="Avg Clean Time"
                            value={report.avg_clean_minutes ?? 0}
                            suffix="min"
                            precision={1}
                        />
                    </Card>
                </Col>
                <Col span={6}>
                    <Card>
                        <Statistic
                            title="Inspection Pass Rate"
                            value={report.inspection_pass_rate ?? 0}
                            suffix="%"
                            precision={1}
                        />
                    </Card>
                </Col>
                <Col span={6}>
                    <Card><Statistic title="Rooms Assigned" value={report.totals.rooms_assigned} /></Card>
                </Col>
                <Col span={6}>
                    <Card><Statistic title="Rooms Completed" value={report.totals.rooms_completed} /></Card>
                </Col>
            </Row>
            <ProTable<HousekeeperRow>
                rowKey="housekeeper_id"
                search={false}
                options={false}
                dataSource={report.by_housekeeper}
                columns={columns}
                pagination={false}
                scroll={{ x: 'max-content' }}
            />
        </AuthenticatedLayout>
    );
}
