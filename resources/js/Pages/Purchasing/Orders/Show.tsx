import { Head, Link, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Card, Descriptions, Form, InputNumber, Modal, Space, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface OrderItem {
    id: number;
    inventory_item: { id: number; name: string; unit: string } | null;
    quantity_ordered: number;
    quantity_received: number;
    unit_cost: number;
}

interface OrdersShowProps {
    order: {
        id: number;
        po_no: string;
        status: string;
        status_label: string;
        total_amount: number;
        supplier: { id: number; name: string } | null;
        requisition_no: string | null;
        ordered_at: string | null;
        expected_at: string | null;
        items: OrderItem[];
    };
}

const statusColors: Record<string, string> = {
    draft: 'default',
    sent: 'blue',
    partially_received: 'orange',
    received: 'green',
    cancelled: 'red',
};

function getRemainingQty(item: OrderItem): number {
    return Math.max(0, item.quantity_ordered - item.quantity_received);
}

export default function OrdersShow({ order }: OrdersShowProps) {
    const [receiveOpen, setReceiveOpen] = useState(false);

    const receivableItems = order.items.filter((item) => getRemainingQty(item) > 0);

    const receiveForm = useForm({
        items: receivableItems.map((item) => ({
            purchase_order_item_id: item.id,
            quantity_received: getRemainingQty(item),
        })),
    });

    const canReceive =
        (order.status === 'sent' || order.status === 'partially_received') && receivableItems.length > 0;

    const openReceiveModal = () => {
        receiveForm.setData(
            'items',
            receivableItems.map((item) => ({
                purchase_order_item_id: item.id,
                quantity_received: getRemainingQty(item),
            })),
        );
        setReceiveOpen(true);
    };

    const submitReceive = () => {
        receiveForm.post(`/purchasing/orders/${order.id}/receive`, {
            onSuccess: () => setReceiveOpen(false),
        });
    };

    const columns: ProColumns<OrderItem>[] = [
        {
            title: 'Item',
            render: (_, r) => r.inventory_item?.name ?? '—',
        },
        {
            title: 'Unit',
            render: (_, r) => r.inventory_item?.unit ?? '—',
        },
        { title: 'Qty Ordered', dataIndex: 'quantity_ordered' },
        { title: 'Qty Received', dataIndex: 'quantity_received' },
        {
            title: 'Unit Cost',
            render: (_, r) => `Rp ${r.unit_cost.toLocaleString('id-ID')}`,
        },
        {
            title: 'Line Total',
            render: (_, r) =>
                `Rp ${(r.quantity_ordered * r.unit_cost).toLocaleString('id-ID')}`,
        },
    ];

    return (
        <AuthenticatedLayout title={order.po_no}>
            <Head title={order.po_no} />
            <Space style={{ marginBottom: 16 }} wrap>
                <Link href="/purchasing/orders">
                    <Button>Back to list</Button>
                </Link>
                {canReceive && (
                    <Button type="primary" onClick={openReceiveModal}>
                        Receive Items
                    </Button>
                )}
            </Space>

            <Card>
                <Descriptions bordered column={{ xs: 1, sm: 2 }}>
                    <Descriptions.Item label="PO Number">{order.po_no}</Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag color={statusColors[order.status]}>{order.status_label}</Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="Supplier">
                        {order.supplier?.name ?? '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Requisition">
                        {order.requisition_no ?? '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Ordered At">
                        {order.ordered_at ?? '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Expected Delivery">
                        {order.expected_at ?? '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Total Amount">
                        Rp {order.total_amount.toLocaleString('id-ID')}
                    </Descriptions.Item>
                </Descriptions>
            </Card>

            <Card title="Items" style={{ marginTop: 16 }}>
                <ProTable
                    rowKey="id"
                    search={false}
                    options={false}
                    pagination={false}
                    dataSource={order.items}
                    columns={columns}
                    footer={() => (
                        <div style={{ textAlign: 'right', fontWeight: 600 }}>
                            Total: Rp {order.total_amount.toLocaleString('id-ID')}
                        </div>
                    )}
                />
            </Card>

            <Modal
                title="Receive Items"
                open={receiveOpen}
                onCancel={() => setReceiveOpen(false)}
                onOk={submitReceive}
                confirmLoading={receiveForm.processing}
                okText="Confirm Receipt"
            >
                <Form layout="vertical">
                    {receivableItems.map((item, index) => {
                        const remaining = getRemainingQty(item);

                        return (
                            <Form.Item
                                key={item.id}
                                label={`${item.inventory_item?.name ?? 'Item'} (remaining: ${remaining} ${item.inventory_item?.unit ?? ''})`}
                            >
                                <InputNumber
                                    min={0}
                                    max={remaining}
                                    value={receiveForm.data.items[index]?.quantity_received ?? 0}
                                    onChange={(v) => {
                                        const next = [...receiveForm.data.items];
                                        next[index] = {
                                            purchase_order_item_id: item.id,
                                            quantity_received: v ?? 0,
                                        };
                                        receiveForm.setData('items', next);
                                    }}
                                />
                            </Form.Item>
                        );
                    })}
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
