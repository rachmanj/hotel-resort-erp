import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';

interface TreatmentRow {
    id: number;
    name: string;
    duration_minutes: number;
    price: number;
    description: string | null;
}

interface TreatmentsIndexProps {
    treatments: TreatmentRow[];
}

export default function TreatmentsIndex({ treatments }: TreatmentsIndexProps) {
    const { can } = useAuth();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<TreatmentRow | null>(null);

    const createForm = useForm({
        name: '',
        duration_minutes: 60,
        price: 0,
        description: '',
    });

    const editForm = useForm({
        name: '',
        duration_minutes: 60,
        price: 0,
        description: '',
    });

    const openEdit = (record: TreatmentRow) => {
        setEditing(record);
        editForm.setData({
            name: record.name,
            duration_minutes: record.duration_minutes,
            price: record.price,
            description: record.description ?? '',
        });
    };

    const columns: ProColumns<TreatmentRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        {
            title: 'Duration',
            dataIndex: 'duration_minutes',
            render: (v) => `${v} min`,
        },
        {
            title: 'Price',
            dataIndex: 'price',
            render: (v) => `Rp ${Number(v).toLocaleString('id-ID')}`,
        },
        { title: 'Description', dataIndex: 'description', ellipsis: true },
        can('spa.manage') && {
            title: 'Actions',
            render: (_, record) => (
                <div style={{ display: 'flex', gap: 8 }}>
                    <Button size="small" onClick={() => openEdit(record)}>Edit</Button>
                    <Button
                        size="small"
                        danger
                        onClick={() => router.delete(`/spa/treatments/${record.id}`)}
                    >
                        Delete
                    </Button>
                </div>
            ),
        },
    ].filter(Boolean) as ProColumns<TreatmentRow>[];

    return (
        <AuthenticatedLayout title="Spa Treatments">
            <Head title="Spa Treatments" />
            {can('spa.manage') && (
                <div style={{ marginBottom: 16 }}>
                    <Button type="primary" onClick={() => setCreating(true)}>Add Treatment</Button>
                </div>
            )}
            <ProTable
                rowKey="id"
                search={false}
                options={false}
                pagination={false}
                dataSource={treatments}
                columns={columns}
            />

            <Modal
                title="Add Treatment"
                open={creating}
                onCancel={() => setCreating(false)}
                onOk={() => createForm.post('/spa/treatments', { onSuccess: () => setCreating(false) })}
                confirmLoading={createForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Name" required>
                        <Input value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Duration (minutes)" required>
                        <InputNumber
                            min={15}
                            max={480}
                            style={{ width: '100%' }}
                            value={createForm.data.duration_minutes}
                            onChange={(v) => createForm.setData('duration_minutes', v ?? 60)}
                        />
                    </Form.Item>
                    <Form.Item label="Price" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={createForm.data.price}
                            onChange={(v) => createForm.setData('price', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Description">
                        <Input.TextArea value={createForm.data.description} onChange={(e) => createForm.setData('description', e.target.value)} />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title="Edit Treatment"
                open={!!editing}
                onCancel={() => setEditing(null)}
                onOk={() => editing && editForm.put(`/spa/treatments/${editing.id}`, { onSuccess: () => setEditing(null) })}
                confirmLoading={editForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Name" required>
                        <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Duration (minutes)" required>
                        <InputNumber
                            min={15}
                            max={480}
                            style={{ width: '100%' }}
                            value={editForm.data.duration_minutes}
                            onChange={(v) => editForm.setData('duration_minutes', v ?? 60)}
                        />
                    </Form.Item>
                    <Form.Item label="Price" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={editForm.data.price}
                            onChange={(v) => editForm.setData('price', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Description">
                        <Input.TextArea value={editForm.data.description} onChange={(e) => editForm.setData('description', e.target.value)} />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
