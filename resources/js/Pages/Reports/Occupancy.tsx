import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Card, Col, DatePicker, Row, Statistic } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface OccupancyRow {
    room_type_id: number;
    room_type_name: string;
    total_rooms: number;
    rooms_sold: number;
    rooms_available: number;
    occupancy_pct: number;
}

interface OccupancyProps {
    report: {
        summary: {
            rooms_sold: number;
            rooms_available: number;
            occupancy_pct: number;
            total_rooms: number;
        };
        by_room_type: OccupancyRow[];
    };
    filters: { from: string; to: string };
}

export default function Occupancy({ report, filters }: OccupancyProps) {
    const columns: ProColumns<OccupancyRow>[] = [
        { title: 'Room Type', dataIndex: 'room_type_name' },
        { title: 'Total Rooms', dataIndex: 'total_rooms', align: 'right' },
        { title: 'Room Nights Sold', dataIndex: 'rooms_sold', align: 'right' },
        { title: 'Available Room Nights', dataIndex: 'rooms_available', align: 'right' },
        { title: 'Occupancy %', dataIndex: 'occupancy_pct', render: (v) => `${Number(v)}%`, align: 'right' },
    ];

    const exportUrl = `/reports/occupancy?from=${filters.from}&to=${filters.to}&export=csv`;

    return (
        <AuthenticatedLayout title="Occupancy Report">
            <Head title="Occupancy" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 12, flexWrap: 'wrap', justifyContent: 'space-between' }}>
                <DatePicker.RangePicker
                    value={[dayjs(filters.from), dayjs(filters.to)]}
                    onChange={(dates) => router.get('/reports/occupancy', {
                        from: dates?.[0]?.format('YYYY-MM-DD'),
                        to: dates?.[1]?.format('YYYY-MM-DD'),
                    }, { preserveState: true })}
                />
                <Button href={exportUrl}>Export CSV</Button>
            </div>
            <Row gutter={16} style={{ marginBottom: 24 }}>
                <Col span={6}>
                    <Card><Statistic title="Occupancy %" value={report.summary.occupancy_pct} suffix="%" precision={2} /></Card>
                </Col>
                <Col span={6}>
                    <Card><Statistic title="Room Nights Sold" value={report.summary.rooms_sold} /></Card>
                </Col>
                <Col span={6}>
                    <Card><Statistic title="Available Room Nights" value={report.summary.rooms_available} /></Card>
                </Col>
                <Col span={6}>
                    <Card><Statistic title="Total Rooms" value={report.summary.total_rooms} /></Card>
                </Col>
            </Row>
            <ProTable<OccupancyRow>
                rowKey="room_type_id"
                search={false}
                options={false}
                dataSource={report.by_room_type}
                columns={columns}
                pagination={false}
                scroll={{ x: 'max-content' }}
            />
        </AuthenticatedLayout>
    );
}
