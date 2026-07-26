import { Head, useForm } from '@inertiajs/react';
import { Button, Card, Checkbox, Col, Form, InputNumber, Row, Select, Table, Typography } from 'antd';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface MenuItem {
    id: number;
    name: string;
    price: number;
    description: string | null;
}

interface MenuCategory {
    id: number;
    name: string;
    items: MenuItem[];
}

interface CartItem {
    menu_item_id: number;
    name: string;
    price: number;
    quantity: number;
    notes: string;
}

interface OrdersCreateProps {
    menuCategories: MenuCategory[];
    tables: Array<{ id: number; name: string; area: string; status: string }>;
    checkedInReservations: Array<{ id: number; reservation_code: string; guest_name: string }>;
    orderTypes: Array<{ value: string; label: string }>;
}

export default function OrdersCreate({
    menuCategories,
    tables,
    checkedInReservations,
    orderTypes,
}: OrdersCreateProps) {
    const [cart, setCart] = useState<CartItem[]>([]);

    const form = useForm({
        order_type: 'dine_in',
        restaurant_table_id: null as number | null,
        reservation_id: null as number | null,
        charged_to_room: false,
        items: [] as Array<{ menu_item_id: number; quantity: number; notes?: string }>,
    });

    const addToCart = (item: MenuItem) => {
        setCart((prev) => {
            const existing = prev.find((c) => c.menu_item_id === item.id);
            if (existing) {
                return prev.map((c) =>
                    c.menu_item_id === item.id ? { ...c, quantity: c.quantity + 1 } : c,
                );
            }
            return [...prev, { menu_item_id: item.id, name: item.name, price: item.price, quantity: 1, notes: '' }];
        });
    };

    const updateQty = (menuItemId: number, quantity: number) => {
        if (quantity <= 0) {
            setCart((prev) => prev.filter((c) => c.menu_item_id !== menuItemId));
        } else {
            setCart((prev) => prev.map((c) => (c.menu_item_id === menuItemId ? { ...c, quantity } : c)));
        }
    };

    const total = useMemo(() => cart.reduce((sum, c) => sum + c.price * c.quantity, 0), [cart]);

    const submit = () => {
        form.setData('items', cart.map((c) => ({
            menu_item_id: c.menu_item_id,
            quantity: c.quantity,
            notes: c.notes || undefined,
        })));
        form.post('/fb/orders');
    };

    const isRoomService = form.data.order_type === 'room_service';

    return (
        <AuthenticatedLayout title="New Order">
            <Head title="New Order" />
            <Row gutter={16}>
                <Col xs={24} lg={14}>
                    {menuCategories.map((cat) => (
                        <Card key={cat.id} title={cat.name} size="small" style={{ marginBottom: 12 }}>
                            <Row gutter={[8, 8]}>
                                {cat.items.map((item) => (
                                    <Col key={item.id} xs={12} sm={8}>
                                        <Card
                                            size="small"
                                            hoverable
                                            onClick={() => addToCart(item)}
                                            style={{ cursor: 'pointer' }}
                                        >
                                            <Typography.Text strong>{item.name}</Typography.Text>
                                            <br />
                                            <Typography.Text type="secondary">
                                                Rp {item.price.toLocaleString('id-ID')}
                                            </Typography.Text>
                                        </Card>
                                    </Col>
                                ))}
                            </Row>
                        </Card>
                    ))}
                </Col>
                <Col xs={24} lg={10}>
                    <Card title="Order Details">
                        <Form layout="vertical">
                            <Form.Item label="Order Type">
                                <Select
                                    value={form.data.order_type}
                                    options={orderTypes}
                                    onChange={(v) => form.setData('order_type', v)}
                                />
                            </Form.Item>
                            {form.data.order_type === 'dine_in' && (
                                <Form.Item label="Table">
                                    <Select
                                        allowClear
                                        placeholder="Select table"
                                        value={form.data.restaurant_table_id}
                                        options={tables.map((t) => ({
                                            value: t.id,
                                            label: `${t.name} (${t.area})`,
                                            disabled: t.status !== 'available',
                                        }))}
                                        onChange={(v) => form.setData('restaurant_table_id', v)}
                                    />
                                </Form.Item>
                            )}
                            {(isRoomService || form.data.charged_to_room) && (
                                <Form.Item label="Reservation">
                                    <Select
                                        allowClear
                                        placeholder="Select checked-in guest"
                                        value={form.data.reservation_id}
                                        options={checkedInReservations.map((r) => ({
                                            value: r.id,
                                            label: `${r.reservation_code} — ${r.guest_name}`,
                                        }))}
                                        onChange={(v) => form.setData('reservation_id', v)}
                                    />
                                </Form.Item>
                            )}
                            {!isRoomService && (
                                <Form.Item>
                                    <Checkbox
                                        checked={form.data.charged_to_room}
                                        onChange={(e) => form.setData('charged_to_room', e.target.checked)}
                                    >
                                        Charge to room
                                    </Checkbox>
                                </Form.Item>
                            )}
                        </Form>

                        <Table
                            size="small"
                            rowKey="menu_item_id"
                            pagination={false}
                            dataSource={cart}
                            columns={[
                                { title: 'Item', dataIndex: 'name' },
                                {
                                    title: 'Qty',
                                    render: (_, record) => (
                                        <InputNumber
                                            min={0}
                                            size="small"
                                            value={record.quantity}
                                            onChange={(v) => updateQty(record.menu_item_id, v ?? 0)}
                                        />
                                    ),
                                },
                                {
                                    title: 'Subtotal',
                                    render: (_, record) =>
                                        `Rp ${(record.price * record.quantity).toLocaleString('id-ID')}`,
                                },
                            ]}
                        />

                        <div style={{ marginTop: 16, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <Typography.Title level={4} style={{ margin: 0 }}>
                                Total: Rp {total.toLocaleString('id-ID')}
                            </Typography.Title>
                            <Button
                                type="primary"
                                disabled={cart.length === 0}
                                loading={form.processing}
                                onClick={submit}
                            >
                                Place Order
                            </Button>
                        </div>
                    </Card>
                </Col>
            </Row>
        </AuthenticatedLayout>
    );
}
