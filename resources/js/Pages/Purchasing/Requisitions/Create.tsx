import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Card, Form, Input, InputNumber, Select, Space, Table } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface InventoryItem {
    id: number;
    name: string;
    unit: string;
    current_stock: number;
}

interface ItemRow {
    key: string;
    inventory_item_id: number | null;
    quantity_requested: number;
    unit: string;
    current_stock: number;
}

interface RequisitionsCreateProps {
    inventoryItems: InventoryItem[];
    canApprove: boolean;
}

export default function RequisitionsCreate({ inventoryItems }: RequisitionsCreateProps) {
    const [rows, setRows] = useState<ItemRow[]>([
        { key: '1', inventory_item_id: null, quantity_requested: 1, unit: '', current_stock: 0 },
    ]);

    const form = useForm({
        department: '',
        notes: '',
        items: [] as Array<{ inventory_item_id: number; quantity_requested: number }>,
    });

    const itemOptions = inventoryItems.map((item) => ({
        value: item.id,
        label: `${item.name} (${item.unit})`,
    }));

    const updateRow = (index: number, patch: Partial<ItemRow>) => {
        setRows((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], ...patch };
            return next;
        });
    };

    const onItemSelect = (index: number, itemId: number) => {
        const item = inventoryItems.find((i) => i.id === itemId);
        updateRow(index, {
            inventory_item_id: itemId,
            unit: item?.unit ?? '',
            current_stock: item?.current_stock ?? 0,
        });
    };

    const addRow = () => {
        setRows((prev) => [
            ...prev,
            {
                key: String(Date.now()),
                inventory_item_id: null,
                quantity_requested: 1,
                unit: '',
                current_stock: 0,
            },
        ]);
    };

    const removeRow = (index: number) => {
        setRows((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== index)));
    };

    const submit = () => {
        form.setData(
            'items',
            rows
                .filter((r) => r.inventory_item_id !== null)
                .map((r) => ({
                    inventory_item_id: r.inventory_item_id!,
                    quantity_requested: r.quantity_requested,
                })),
        );
        form.post('/purchasing/requisitions');
    };

    return (
        <AuthenticatedLayout title="New Purchase Requisition">
            <Head title="New Requisition" />
            <Link href="/purchasing/requisitions" style={{ marginBottom: 16, display: 'inline-block' }}>
                ← Back to list
            </Link>
            <Card>
                <Form layout="vertical">
                    <Form.Item label="Department" required>
                        <Input
                            value={form.data.department}
                            onChange={(e) => form.setData('department', e.target.value)}
                            placeholder="e.g. Kitchen, Housekeeping"
                        />
                        {form.errors.department && (
                            <div style={{ color: 'red' }}>{form.errors.department}</div>
                        )}
                    </Form.Item>
                    <Form.Item label="Notes">
                        <Input.TextArea
                            rows={3}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                    </Form.Item>
                </Form>

                <Table
                    rowKey="key"
                    pagination={false}
                    dataSource={rows}
                    columns={[
                        {
                            title: 'Inventory Item',
                            render: (_, row, index) => (
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    placeholder="Select item"
                                    style={{ width: 260 }}
                                    value={row.inventory_item_id}
                                    options={itemOptions}
                                    onChange={(v) => onItemSelect(index, v)}
                                />
                            ),
                        },
                        {
                            title: 'Quantity',
                            width: 120,
                            render: (_, row, index) => (
                                <InputNumber
                                    min={1}
                                    value={row.quantity_requested}
                                    onChange={(v) => updateRow(index, { quantity_requested: v ?? 1 })}
                                />
                            ),
                        },
                        {
                            title: 'Unit',
                            width: 100,
                            render: (_, row) => row.unit || '—',
                        },
                        {
                            title: 'Current Stock',
                            width: 120,
                            render: (_, row) => row.inventory_item_id ? row.current_stock : '—',
                        },
                        {
                            title: '',
                            width: 80,
                            render: (_, __, index) => (
                                <Button type="link" danger onClick={() => removeRow(index)}>
                                    Remove
                                </Button>
                            ),
                        },
                    ]}
                    footer={() => (
                        <Button type="dashed" onClick={addRow}>
                            Add Item
                        </Button>
                    )}
                />

                {form.errors.items && <div style={{ color: 'red', marginTop: 8 }}>{form.errors.items}</div>}

                <Space style={{ marginTop: 16 }}>
                    <Button type="primary" loading={form.processing} onClick={submit}>
                        Create Requisition
                    </Button>
                    <Link href="/purchasing/requisitions">
                        <Button>Cancel</Button>
                    </Link>
                </Space>
            </Card>
        </AuthenticatedLayout>
    );
}
