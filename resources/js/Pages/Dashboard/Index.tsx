import { Head, Link, router } from '@inertiajs/react';
import { Card, Col, Row, Tag, Typography } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface RoomChip {
    id: number;
    number: string;
    housekeeping_status: string;
    housekeeping_status_label: string;
    housekeeping_status_color: string;
}

interface RoomStatusSummary {
    total: number;
    dirty: number;
    cleaning: number;
    clean: number;
    ready: number;
    out_of_order: number;
}

interface DashboardIndexProps {
    roomStatusSummary: RoomStatusSummary;
    rooms: RoomChip[];
}

const FILTER_OPTIONS = [
    { key: 'all', label: 'All' },
    { key: 'dirty', label: 'Dirty' },
    { key: 'cleaning', label: 'Cleaning' },
    { key: 'clean', label: 'Clean' },
    { key: 'ready', label: 'Ready' },
    { key: 'out_of_order', label: 'Out of Order' },
];

export default function DashboardIndex({ roomStatusSummary, rooms }: DashboardIndexProps) {
    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />
            <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Typography.Text type="secondary">Occupancy</Typography.Text>
                        <div style={{ fontSize: 24, fontWeight: 600 }}>–</div>
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Typography.Text type="secondary">Revenue Today</Typography.Text>
                        <div style={{ fontSize: 24, fontWeight: 600 }}>Rp 0</div>
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Typography.Text type="secondary">Check-ins Today</Typography.Text>
                        <div style={{ fontSize: 24, fontWeight: 600 }}>–</div>
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Typography.Text type="secondary">Rooms Needing Attention</Typography.Text>
                        <div style={{ fontSize: 24, fontWeight: 600 }}>
                            {roomStatusSummary.dirty + roomStatusSummary.cleaning}
                        </div>
                    </Card>
                </Col>
            </Row>

            <Card
                title="Room Status"
                extra={<Link href="/housekeeping">View Board</Link>}
                style={{ marginTop: 16 }}
            >
                <div style={{ marginBottom: 16, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {FILTER_OPTIONS.map((option) => (
                        <Link
                            key={option.key}
                            href={option.key === 'all' ? '/housekeeping' : `/housekeeping?filter=${option.key}`}
                        >
                            <Tag
                                color={
                                    option.key === 'dirty'
                                        ? 'red'
                                        : option.key === 'cleaning'
                                          ? 'orange'
                                          : option.key === 'clean'
                                            ? 'lime'
                                            : option.key === 'ready'
                                              ? 'green'
                                              : option.key === 'out_of_order'
                                                ? 'default'
                                                : 'blue'
                                }
                                style={{ cursor: 'pointer' }}
                            >
                                {option.label}
                                {option.key !== 'all' && (
                                    <> ({roomStatusSummary[option.key as keyof RoomStatusSummary] ?? 0})</>
                                )}
                            </Tag>
                        </Link>
                    ))}
                </div>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    {rooms.map((room) => (
                        <Tag key={room.id} color={room.housekeeping_status_color}>
                            {room.number}
                        </Tag>
                    ))}
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
