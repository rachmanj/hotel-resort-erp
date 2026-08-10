import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal, Select, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface InventoryRow {
    id: number;
    name: string;
    category: string;
    category_label: string;
    unit: string;
    current_stock: number;
    reorder_level: number;
    is_low_stock: boolean;
}

interface InventoryIndexProps {
    items: { data: InventoryRow[] };
    filters: Record<string, string>;
    categoryOptions: Array<{ value: string; label: string }>;
    unitOptions: Array<{ value: string; label: string }>;
    lowStockCount: number;
}

export default function InventoryIndex({ items, filters, categoryOptions, unitOptions, lowStockCount }: InventoryIndexProps) {
    const [creating, setCreating] = useState(false);
    const form = useForm({
        name: '',
        category: categoryOptions[0]?.value ?? 'other',
        unit: unitOptions[0]?.value ?? 'pcs',
        current_stock: 0,
        reorder_level: 0,
    });

    const columns: ProColumns<InventoryRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Category', dataIndex: 'category_label' },
        { title: 'Unit', dataIndex: 'unit' },
        { title: 'Stock', dataIndex: 'current_stock' },
        { title: 'Reorder Level', dataIndex: 'reorder_level' },
        {
            title: 'Status',
            render: (_, r) => (r.is_low_stock ? <Tag color="red">Low Stock</Tag> : <Tag color="green">OK</Tag>),
        },
    ];

    return (
        <AuthenticatedLayout title="Inventory">
            <Head title="Inventory" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8, justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', gap: 8 }}>
                    <Select
                        allowClear
                        placeholder="Category"
                        style={{ width: 140 }}
                        value={filters.category}
                        options={categoryOptions}
                        onChange={(v) => router.get('/inventory', { ...filters, category: v }, { preserveState: true })}
                    />
                    {lowStockCount > 0 && <Tag color="red">{lowStockCount} low stock items</Tag>}
                </div>
                <Button type="primary" onClick={() => setCreating(true)}>Add Item</Button>
            </div>
            <ProTable rowKey="id" search={false} options={false} pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }} dataSource={items.data} columns={columns} />

            <Modal title="Add Inventory Item" open={creating} onCancel={() => setCreating(false)}
                onOk={() => form.post('/inventory', { onSuccess: () => setCreating(false) })} confirmLoading={form.processing}>
                <Form layout="vertical">
                    <Form.Item label="Name"><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Form.Item>
                    <Form.Item label="Category">
                        <Select value={form.data.category} options={categoryOptions} onChange={(v) => form.setData('category', v)} />
                    </Form.Item>
                    <Form.Item label="Unit">
                        <Select value={form.data.unit} options={unitOptions} onChange={(v) => form.setData('unit', v)} />
                    </Form.Item>
                    <Form.Item label="Current Stock">
                        <InputNumber min={0} style={{ width: '100%' }} value={form.data.current_stock} onChange={(v) => form.setData('current_stock', v ?? 0)} />
                    </Form.Item>
                    <Form.Item label="Reorder Level">
                        <InputNumber min={0} style={{ width: '100%' }} value={form.data.reorder_level} onChange={(v) => form.setData('reorder_level', v ?? 0)} />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
