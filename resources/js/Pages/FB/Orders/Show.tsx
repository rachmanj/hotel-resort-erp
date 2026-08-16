import { Head, router, useForm } from '@inertiajs/react';
import { Button, Card, Col, Descriptions, Form, Modal, Row, Select, Space, Table, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';

interface OrderItem {
    id: number;
    name: string;
    quantity: number;
    unit_price: number;
    line_total: number;
    notes: string | null;
    status: string;
    status_label: string;
}

interface OrderDetail {
    id: number;
    order_no: string;
    order_type: string;
    order_type_label: string;
    status: string;
    status_label: string;
    total_amount: number;
    charged_to_room: boolean;
    table: string | null;
    guest: string | null;
    opened_by: string | null;
    created_at: string;
    items: OrderItem[];
    folio_item_id: number | null;
}

interface CheckedInReservation {
    id: number;
    reservation_code: string;
    guest_name: string;
}

interface OrdersShowProps {
    order: OrderDetail;
    statusOptions: Array<{ value: string; label: string }>;
    checkedInReservations: CheckedInReservation[];
}

const statusColors: Record<string, string> = {
    new: 'blue',
    preparing: 'orange',
    ready: 'green',
    served: 'default',
    cancelled: 'red',
};

function updateItemStatus(orderId: number, itemId: number, status: string): void {
    router.put(`/fb/orders/${orderId}/items/${itemId}/status`, { status }, { preserveScroll: true });
}

export default function OrdersShow({ order, statusOptions, checkedInReservations }: OrdersShowProps) {
    const { can } = useAuth();
    const canManage = can('fb.manage');
    const canUpdateItemStatus = can('fb.orders.update_status') || canManage;
    const isActive = !['served', 'cancelled'].includes(order.status);
    const canChargeToRoom = canManage && !order.charged_to_room && order.folio_item_id === null;
    const [chargeModalOpen, setChargeModalOpen] = useState(false);

    const chargeForm = useForm({
        reservation_id: null as number | null,
    });

    const renderItemActions = (item: OrderItem) => {
        if (!canUpdateItemStatus || !isActive) {
            return null;
        }

        return (
            <Space size="small">
                {item.status === 'new' && (
                    <Button size="small" onClick={() => updateItemStatus(order.id, item.id, 'preparing')}>
                        Preparing
                    </Button>
                )}
                {item.status === 'preparing' && (
                    <Button size="small" onClick={() => updateItemStatus(order.id, item.id, 'ready')}>
                        Ready
                    </Button>
                )}
                {canManage && item.status === 'ready' && (
                    <Button size="small" onClick={() => updateItemStatus(order.id, item.id, 'served')}>
                        Served
                    </Button>
                )}
            </Space>
        );
    };

    return (
        <AuthenticatedLayout title={`Order ${order.order_no}`}>
            <Head title={order.order_no} />
            <Row gutter={16}>
                <Col span={24}>
                    <Card>
                        <Descriptions bordered column={{ xs: 1, sm: 2 }}>
                            <Descriptions.Item label="Order #">{order.order_no}</Descriptions.Item>
                            <Descriptions.Item label="Status">
                                <Tag color={statusColors[order.status]}>{order.status_label}</Tag>
                            </Descriptions.Item>
                            <Descriptions.Item label="Type">{order.order_type_label}</Descriptions.Item>
                            <Descriptions.Item label="Total">
                                Rp {order.total_amount.toLocaleString('id-ID')}
                            </Descriptions.Item>
                            <Descriptions.Item label="Table">{order.table ?? '–'}</Descriptions.Item>
                            <Descriptions.Item label="Guest">{order.guest ?? '–'}</Descriptions.Item>
                            <Descriptions.Item label="Opened By">{order.opened_by}</Descriptions.Item>
                            <Descriptions.Item label="Created">{order.created_at}</Descriptions.Item>
                            <Descriptions.Item label="Charged to Room">
                                {order.charged_to_room ? <Tag color="purple">Yes</Tag> : 'No'}
                            </Descriptions.Item>
                        </Descriptions>

                        {(canManage && isActive) || canChargeToRoom ? (
                            <div style={{ marginTop: 16, display: 'flex', gap: 8 }}>
                                {canManage && isActive && (
                                    <>
                                        <Select
                                            style={{ width: 160 }}
                                            placeholder="Update status"
                                            options={statusOptions}
                                            onChange={(status) =>
                                                router.put(`/fb/orders/${order.id}/status`, { status })
                                            }
                                        />
                                        <Button danger onClick={() => router.post(`/fb/orders/${order.id}/cancel`)}>
                                            Cancel Order
                                        </Button>
                                    </>
                                )}
                                {canChargeToRoom && (
                                    <Button
                                        onClick={() => {
                                            chargeForm.setData('reservation_id', null);
                                            setChargeModalOpen(true);
                                        }}
                                    >
                                        Charge to Room
                                    </Button>
                                )}
                            </div>
                        ) : null}
                    </Card>
                </Col>
                <Col span={24} style={{ marginTop: 16 }}>
                    <Card title="Items">
                        <Table
                            rowKey="id"
                            pagination={false}
                            dataSource={order.items}
                            columns={[
                                { title: 'Item', dataIndex: 'name' },
                                { title: 'Qty', dataIndex: 'quantity' },
                                {
                                    title: 'Unit Price',
                                    render: (_, r) => `Rp ${r.unit_price.toLocaleString('id-ID')}`,
                                },
                                {
                                    title: 'Line Total',
                                    render: (_, r) => `Rp ${r.line_total.toLocaleString('id-ID')}`,
                                },
                                { title: 'Notes', dataIndex: 'notes', render: (v) => v ?? '–' },
                                {
                                    title: 'Status',
                                    render: (_, r) => (
                                        <Tag color={statusColors[r.status]}>{r.status_label}</Tag>
                                    ),
                                },
                                ...(canUpdateItemStatus
                                    ? [
                                          {
                                              title: 'Actions',
                                              render: (_: unknown, r: OrderItem) => renderItemActions(r),
                                          },
                                      ]
                                    : []),
                            ]}
                        />
                    </Card>
                </Col>
            </Row>

            <Modal
                title="Charge to Room"
                open={chargeModalOpen}
                onCancel={() => setChargeModalOpen(false)}
                onOk={() =>
                    chargeForm.post(`/fb/orders/${order.id}/charge-to-room`, {
                        onSuccess: () => setChargeModalOpen(false),
                    })
                }
                confirmLoading={chargeForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Reservation" required>
                        <Select
                            placeholder="Select checked-in guest"
                            value={chargeForm.data.reservation_id}
                            options={checkedInReservations.map((r) => ({
                                value: r.id,
                                label: `${r.reservation_code} · ${r.guest_name}`,
                            }))}
                            onChange={(v) => chargeForm.setData('reservation_id', v)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
