import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, Modal, Select } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';

interface TherapistRow {
    id: number;
    name: string;
    phone: string | null;
    user: { id: number; name: string } | null;
}

interface TherapistsIndexProps {
    therapists: TherapistRow[];
    userOptions: Array<{ id: number; name: string }>;
}

export default function TherapistsIndex({ therapists, userOptions }: TherapistsIndexProps) {
    const { can } = useAuth();
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<TherapistRow | null>(null);

    const createForm = useForm({
        name: '',
        phone: '',
        user_id: null as number | null,
    });

    const editForm = useForm({
        name: '',
        phone: '',
        user_id: null as number | null,
    });

    const openEdit = (record: TherapistRow) => {
        setEditing(record);
        editForm.setData({
            name: record.name,
            phone: record.phone ?? '',
            user_id: record.user?.id ?? null,
        });
    };

    const columns: ProColumns<TherapistRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Phone', dataIndex: 'phone', render: (v) => v ?? '—' },
        { title: 'Linked User', dataIndex: ['user', 'name'], render: (_, r) => r.user?.name ?? '—' },
        can('spa.manage') && {
            title: 'Actions',
            render: (_, record) => (
                <div style={{ display: 'flex', gap: 8 }}>
                    <Button size="small" onClick={() => openEdit(record)}>Edit</Button>
                    <Button
                        size="small"
                        danger
                        onClick={() => router.delete(`/spa/therapists/${record.id}`)}
                    >
                        Delete
                    </Button>
                </div>
            ),
        },
    ].filter(Boolean) as ProColumns<TherapistRow>[];

    return (
        <AuthenticatedLayout title="Spa Therapists">
            <Head title="Spa Therapists" />
            {can('spa.manage') && (
                <div style={{ marginBottom: 16 }}>
                    <Button type="primary" onClick={() => setCreating(true)}>Add Therapist</Button>
                </div>
            )}
            <ProTable
                rowKey="id"
                search={false}
                options={false}
                pagination={false}
                dataSource={therapists}
                columns={columns}
            />

            <Modal
                title="Add Therapist"
                open={creating}
                onCancel={() => setCreating(false)}
                onOk={() => createForm.post('/spa/therapists', { onSuccess: () => setCreating(false) })}
                confirmLoading={createForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Name" required>
                        <Input value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Phone">
                        <Input value={createForm.data.phone} onChange={(e) => createForm.setData('phone', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Linked User">
                        <Select
                            allowClear
                            placeholder="Optional"
                            value={createForm.data.user_id}
                            options={userOptions.map((u) => ({ value: u.id, label: u.name }))}
                            onChange={(v) => createForm.setData('user_id', v ?? null)}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title="Edit Therapist"
                open={!!editing}
                onCancel={() => setEditing(null)}
                onOk={() => editing && editForm.put(`/spa/therapists/${editing.id}`, { onSuccess: () => setEditing(null) })}
                confirmLoading={editForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Name" required>
                        <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Phone">
                        <Input value={editForm.data.phone} onChange={(e) => editForm.setData('phone', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Linked User">
                        <Select
                            allowClear
                            placeholder="Optional"
                            value={editForm.data.user_id}
                            options={userOptions.map((u) => ({ value: u.id, label: u.name }))}
                            onChange={(v) => editForm.setData('user_id', v ?? null)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
