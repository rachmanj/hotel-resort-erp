import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal, Switch } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface RoomType {
    id: number;
    name: string;
    code: string;
    max_occupancy: number;
    base_rate: string;
    description?: string | null;
    is_active: boolean;
}

interface RoomTypesIndexProps {
    roomTypes: Paginated<RoomType>;
    filters: { search?: string };
}

export default function RoomTypesIndex({ roomTypes, filters }: RoomTypesIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<RoomType | null>(null);

    const form = useForm({
        name: '',
        code: '',
        max_occupancy: 2,
        base_rate: 0,
        description: '',
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            name: '',
            code: '',
            max_occupancy: 2,
            base_rate: 0,
            description: '',
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEdit = (record: RoomType) => {
        setEditing(record);
        form.setData({
            name: record.name,
            code: record.code,
            max_occupancy: record.max_occupancy,
            base_rate: Number(record.base_rate),
            description: record.description ?? '',
            is_active: record.is_active,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/room-types/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/room-types', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const columns: ProColumns<RoomType>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Code', dataIndex: 'code' },
        { title: 'Max Occupancy', dataIndex: 'max_occupancy' },
        {
            title: 'Base Rate',
            dataIndex: 'base_rate',
            render: (_, record) => `Rp ${Number(record.base_rate).toLocaleString('id-ID')}`,
        },
        {
            title: 'Active',
            dataIndex: 'is_active',
            render: (_, record) => (record.is_active ? 'Yes' : 'No'),
        },
        {
            title: 'Actions',
            valueType: 'option',
            render: (_, record) => [
                <Button key="edit" type="link" onClick={() => openEdit(record)}>
                    Edit
                </Button>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Room Types">
            <Head title="Room Types" />
            <ProTable<RoomType>
                rowKey="id"
                columns={columns}
                dataSource={roomTypes.data}
                search={false}
                options={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Room Type
                    </Button>,
                ]}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: roomTypes.current_page,
                    pageSize: roomTypes.per_page,
                    total: roomTypes.total,
                    onChange: (page) =>
                        router.get('/room-types', { ...filters, page }, { preserveState: true }),
                }}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={editing ? 'Edit Room Type' : 'New Room Type'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Code" required>
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Max Occupancy" required>
                        <InputNumber
                            min={1}
                            style={{ width: '100%' }}
                            value={form.data.max_occupancy}
                            onChange={(v) => form.setData('max_occupancy', v ?? 2)}
                        />
                    </Form.Item>
                    <Form.Item label="Base Rate" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.base_rate}
                            onChange={(v) => form.setData('base_rate', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Description">
                        <Input.TextArea
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Active">
                        <Switch
                            checked={form.data.is_active}
                            onChange={(checked) => form.setData('is_active', checked)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
