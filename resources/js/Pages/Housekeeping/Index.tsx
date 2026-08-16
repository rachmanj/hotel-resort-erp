import { Head, Link, router } from '@inertiajs/react';
import { Card, Col, Row, Tag, theme, Typography } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface BoardRoom {
    id: number;
    number: string;
    room_type?: { id: number; name: string };
    housekeeping_status: string;
    housekeeping_status_label: string;
    housekeeper?: { id: number; name: string };
    last_updated_human?: string;
}

interface BoardColumn {
    key: string;
    label: string;
    color: string;
    rooms: BoardRoom[];
}

interface HousekeepingIndexProps {
    columns: BoardColumn[];
    outOfOrderRooms: BoardRoom[];
    filters: { filter: string };
    summary: {
        total: number;
        dirty: number;
        cleaning: number;
        clean: number;
        ready: number;
        out_of_order: number;
    };
}

const COLUMN_HEADER_COLORS: Record<string, string> = {
    dirty: '#ff4d4f',
    cleaning: '#fa8c16',
    clean: '#a0d911',
    inspected: '#1677ff',
    ready: '#52c41a',
};

export default function HousekeepingIndex({ columns, outOfOrderRooms, filters, summary }: HousekeepingIndexProps) {
    const { token } = theme.useToken();
    const activeFilter = filters.filter || 'all';

    return (
        <AuthenticatedLayout title="Housekeeping Status Board">
            <Head title="Housekeeping" />
            <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 12 }}>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {[
                        { key: 'all', label: `All (${summary.total})` },
                        { key: 'dirty', label: `Dirty (${summary.dirty})` },
                        { key: 'cleaning', label: `Cleaning (${summary.cleaning})` },
                        { key: 'clean', label: `Clean (${summary.clean})` },
                        { key: 'ready', label: `Ready (${summary.ready})` },
                    ].map((f) => (
                        <Tag
                            key={f.key}
                            color={activeFilter === f.key ? 'blue' : 'default'}
                            style={{ cursor: 'pointer' }}
                            onClick={() =>
                                router.get(
                                    '/housekeeping',
                                    f.key === 'all' ? {} : { filter: f.key },
                                    { preserveState: true },
                                )
                            }
                        >
                            {f.label}
                        </Tag>
                    ))}
                </div>
                <Link href="/housekeeping/assignments">
                    <Tag color="purple" style={{ cursor: 'pointer' }}>Manage Assignments</Tag>
                </Link>
            </div>

            <Row gutter={[16, 16]}>
                {columns.map((column) => (
                    <Col key={column.key} xs={24} sm={12} lg={8} xl={4}>
                        <Card
                            size="small"
                            title={
                                <span>
                                    <Tag color={column.color}>{column.rooms.length}</Tag> {column.label}
                                </span>
                            }
                            styles={{
                                header: { borderTop: `3px solid ${COLUMN_HEADER_COLORS[column.key] ?? '#d9d9d9'}` },
                                body: { minHeight: 200, maxHeight: 500, overflowY: 'auto' },
                            }}
                        >
                            {column.rooms.length === 0 ? (
                                <Typography.Text type="secondary">No rooms</Typography.Text>
                            ) : (
                                column.rooms.map((room) => (
                                    <Card
                                        key={room.id}
                                        size="small"
                                        style={{ marginBottom: 8 }}
                                        styles={{ body: { padding: '8px 12px' } }}
                                    >
                                        <div style={{ fontWeight: 600 }}>Room {room.number}</div>
                                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                            {room.room_type?.name}
                                        </Typography.Text>
                                        {room.housekeeper && (
                                            <div style={{ fontSize: 12, marginTop: 4 }}>
                                                👤 {room.housekeeper.name}
                                            </div>
                                        )}
                                        {room.last_updated_human && (
                                            <div style={{ fontSize: 11, color: token.colorTextSecondary, marginTop: 2 }}>
                                                {room.last_updated_human}
                                            </div>
                                        )}
                                    </Card>
                                ))
                            )}
                        </Card>
                    </Col>
                ))}
            </Row>

            {outOfOrderRooms.length > 0 && (
                <Card title="Out of Order" size="small" style={{ marginTop: 16 }}>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                        {outOfOrderRooms.map((room) => (
                            <Tag key={room.id} color="default">
                                Room {room.number}
                            </Tag>
                        ))}
                    </div>
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
