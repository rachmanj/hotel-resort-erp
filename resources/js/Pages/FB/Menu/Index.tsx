import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal, Select, Switch, Tag } from 'antd';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';

interface MenuItemRow {
    id: number;
    menu_category_id: number;
    name: string;
    description: string | null;
    price: number;
    is_available: boolean;
}

interface CategoryGroup {
    id: number;
    name: string;
    sort_order: number;
    items: MenuItemRow[];
}

interface MenuIndexProps {
    categories: CategoryGroup[];
    categoryOptions: Array<{ id: number; name: string }>;
}

export default function MenuIndex({ categories, categoryOptions }: MenuIndexProps) {
    const { can } = useAuth();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<MenuItemRow | null>(null);

    const createForm = useForm({
        menu_category_id: categoryOptions[0]?.id ?? null,
        name: '',
        description: '',
        price: 0,
        is_available: true,
    });

    const editForm = useForm({
        menu_category_id: 0,
        name: '',
        description: '',
        price: 0,
        is_available: true,
    });

    const flatItems = useMemo(
        () => categories.flatMap((c) => c.items.map((item) => ({ ...item, category_name: c.name }))),
        [categories],
    );

    const openEdit = (record: MenuItemRow) => {
        setEditing(record);
        editForm.setData({
            menu_category_id: record.menu_category_id,
            name: record.name,
            description: record.description ?? '',
            price: record.price,
            is_available: record.is_available,
        });
    };

    const columns: ProColumns<MenuItemRow & { category_name: string }>[] = [
        { title: 'Category', dataIndex: 'category_name' },
        { title: 'Name', dataIndex: 'name' },
        { title: 'Description', dataIndex: 'description', ellipsis: true },
        {
            title: 'Price',
            dataIndex: 'price',
            render: (v) => `Rp ${Number(v).toLocaleString('id-ID')}`,
        },
        {
            title: 'Available',
            dataIndex: 'is_available',
            render: (v, record) => (
                can('fb.manage') ? (
                    <Switch
                        checked={!!v}
                        onChange={() => router.post(`/fb/menu/${record.id}/toggle`)}
                    />
                ) : (
                    <Tag color={v ? 'green' : 'red'}>{v ? 'Yes' : 'No'}</Tag>
                )
            ),
        },
        can('fb.manage') && {
            title: 'Actions',
            render: (_, record) => (
                <Button size="small" onClick={() => openEdit(record)}>Edit</Button>
            ),
        },
    ].filter(Boolean) as ProColumns<MenuItemRow & { category_name: string }>[];

    return (
        <AuthenticatedLayout title="F&B Menu">
            <Head title="Menu" />
            {can('fb.manage') && (
                <div style={{ marginBottom: 16 }}>
                    <Button type="primary" onClick={() => setCreating(true)}>Add Menu Item</Button>
                </div>
            )}
            <ProTable
                rowKey="id"
                search={false}
                options={false}
                pagination={false}
                dataSource={flatItems}
                columns={columns}
            />

            <Modal
                title="Add Menu Item"
                open={creating}
                onCancel={() => setCreating(false)}
                onOk={() => createForm.post('/fb/menu', { onSuccess: () => setCreating(false) })}
                confirmLoading={createForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Category" required>
                        <Select
                            value={createForm.data.menu_category_id}
                            options={categoryOptions.map((c) => ({ value: c.id, label: c.name }))}
                            onChange={(v) => createForm.setData('menu_category_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Name" required>
                        <Input value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Description">
                        <Input.TextArea value={createForm.data.description} onChange={(e) => createForm.setData('description', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Price" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={createForm.data.price}
                            onChange={(v) => createForm.setData('price', v ?? 0)}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title="Edit Menu Item"
                open={!!editing}
                onCancel={() => setEditing(null)}
                onOk={() => editing && editForm.put(`/fb/menu/${editing.id}`, { onSuccess: () => setEditing(null) })}
                confirmLoading={editForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Category" required>
                        <Select
                            value={editForm.data.menu_category_id}
                            options={categoryOptions.map((c) => ({ value: c.id, label: c.name }))}
                            onChange={(v) => editForm.setData('menu_category_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Name" required>
                        <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Description">
                        <Input.TextArea value={editForm.data.description} onChange={(e) => editForm.setData('description', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Price" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={editForm.data.price}
                            onChange={(v) => editForm.setData('price', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Available">
                        <Switch
                            checked={editForm.data.is_available}
                            onChange={(v) => editForm.setData('is_available', v)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
