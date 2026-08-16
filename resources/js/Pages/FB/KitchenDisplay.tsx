import { Head, router } from '@inertiajs/react';
import { Button, Card, Col, Row, Tag, Typography } from 'antd';
import { useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';

interface KdsOrderItem {
    id: number;
    name: string;
    quantity: number;
    notes: string | null;
    status: string;
}

interface KdsOrder {
    id: number;
    order_no: string;
    order_type: string;
    table: string | null;
    guest: string | null;
    opened_by: string | null;
    total_amount: number;
    created_at: string;
    items: KdsOrderItem[];
}

interface KdsColumn {
    key: string;
    label: string;
    color: string;
    orders: KdsOrder[];
}

interface KitchenDisplayProps {
    columns: KdsColumn[];
    hotelId: number | null;
}

const itemStatusColors: Record<string, string> = {
    new: 'blue',
    preparing: 'orange',
    ready: 'green',
    served: 'default',
};

function updateItemStatus(orderId: number, itemId: number, status: string): void {
    router.put(
        `/fb/orders/${orderId}/items/${itemId}/status`,
        { status },
        { preserveScroll: true },
    );
}

export default function KitchenDisplay({ columns }: KitchenDisplayProps) {
    const { can } = useAuth();
    const canUpdateItemStatus = can('fb.orders.update_status') || can('fb.manage');

    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['columns'] });
        }, 30000);

        return () => clearInterval(interval);
    }, []);

    return (
        <AuthenticatedLayout title="Kitchen Display">
            <Head title="Kitchen Display" />
            <Typography.Paragraph type="secondary" style={{ marginBottom: 16 }}>
                Auto-refreshes every 30 seconds. Tap items to move them through Preparing → Ready.
            </Typography.Paragraph>
            <Row gutter={[16, 16]}>
                {columns.map((column) => (
                    <Col key={column.key} xs={24} sm={12} lg={6}>
                        <div
                            style={{
                                borderTop: `4px solid ${column.color}`,
                                paddingTop: 8,
                                marginBottom: 8,
                            }}
                        >
                            <Typography.Title level={5}>
                                {column.label}{' '}
                                <Tag>{column.orders.length}</Tag>
                            </Typography.Title>
                        </div>
                        {column.orders.map((order) => (
                            <Card
                                key={order.id}
                                size="small"
                                style={{ marginBottom: 8 }}
                                title={
                                    <span>
                                        {order.order_no}{' '}
                                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                            {order.created_at}
                                        </Typography.Text>
                                    </span>
                                }
                                extra={<Tag>{order.order_type}</Tag>}
                            >
                                <Typography.Text type="secondary">
                                    {order.table ?? order.guest ?? '–'}
                                </Typography.Text>
                                <ul style={{ margin: '8px 0 0', paddingLeft: 0, listStyle: 'none' }}>
                                    {order.items.map((item) => (
                                        <li
                                            key={item.id}
                                            style={{
                                                marginBottom: 8,
                                                padding: '4px 0',
                                                borderBottom: '1px solid #f0f0f0',
                                            }}
                                        >
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    justifyContent: 'space-between',
                                                    alignItems: 'center',
                                                    gap: 8,
                                                }}
                                            >
                                                <span>
                                                    <Tag color={itemStatusColors[item.status] ?? 'default'}>
                                                        {item.status}
                                                    </Tag>
                                                    <strong>{item.quantity}x</strong> {item.name}
                                                    {item.notes && (
                                                        <Typography.Text type="warning">
                                                            {' '}
                                                            ({item.notes})
                                                        </Typography.Text>
                                                    )}
                                                </span>
                                                {canUpdateItemStatus && (
                                                    <span style={{ display: 'flex', gap: 4 }}>
                                                        {item.status === 'new' && (
                                                            <Button
                                                                size="small"
                                                                type="primary"
                                                                onClick={() =>
                                                                    updateItemStatus(
                                                                        order.id,
                                                                        item.id,
                                                                        'preparing',
                                                                    )
                                                                }
                                                            >
                                                                Prepare
                                                            </Button>
                                                        )}
                                                        {item.status === 'preparing' && (
                                                            <Button
                                                                size="small"
                                                                type="primary"
                                                                onClick={() =>
                                                                    updateItemStatus(
                                                                        order.id,
                                                                        item.id,
                                                                        'ready',
                                                                    )
                                                                }
                                                            >
                                                                Ready
                                                            </Button>
                                                        )}
                                                        {can('fb.manage') && item.status === 'ready' && (
                                                            <Button
                                                                size="small"
                                                                onClick={() =>
                                                                    updateItemStatus(
                                                                        order.id,
                                                                        item.id,
                                                                        'served',
                                                                    )
                                                                }
                                                            >
                                                                Served
                                                            </Button>
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </Card>
                        ))}
                    </Col>
                ))}
            </Row>
        </AuthenticatedLayout>
    );
}
