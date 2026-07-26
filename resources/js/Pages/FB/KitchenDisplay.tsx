import { Head, router } from '@inertiajs/react';
import { Card, Col, Row, Tag, Typography } from 'antd';
import { useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

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

export default function KitchenDisplay({ columns }: KitchenDisplayProps) {
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['columns'] });
        }, 10000);

        return () => clearInterval(interval);
    }, []);

    return (
        <AuthenticatedLayout title="Kitchen Display">
            <Head title="Kitchen Display" />
            <Typography.Paragraph type="secondary" style={{ marginBottom: 16 }}>
                Auto-refreshes every 10 seconds. Move orders through New → Preparing → Ready → Served.
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
                                    {order.table ?? order.guest ?? '—'}
                                </Typography.Text>
                                <ul style={{ margin: '8px 0 0', paddingLeft: 16 }}>
                                    {order.items.map((item) => (
                                        <li key={item.id}>
                                            <strong>{item.quantity}x</strong> {item.name}
                                            {item.notes && (
                                                <Typography.Text type="warning"> ({item.notes})</Typography.Text>
                                            )}
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
