import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface Floor {
    id: number;
    name: string;
    level: number;
}

interface FloorsIndexProps {
    floors: Paginated<Floor>;
    filters: { search?: string };
}

export default function FloorsIndex({ floors, filters }: FloorsIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Floor | null>(null);

    const form = useForm({
        name: '',
        level: 1,
    });

    const openCreate = () => {
        setEditing(null);
        form.setData({ name: '', level: 1 });
        setModalOpen(true);
    };

    const openEdit = (record: Floor) => {
        setEditing(record);
        form.setData({ name: record.name, level: record.level });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/floors/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/floors', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const columns: ProColumns<Floor>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Level', dataIndex: 'level' },
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
        <AuthenticatedLayout title="Floors">
            <Head title="Floors" />
            <ProTable<Floor>
                rowKey="id"
                columns={columns}
                dataSource={floors.data}
                search={false}
                options={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Floor
                    </Button>,
                ]}
                pagination={{
                    current: floors.current_page,
                    pageSize: floors.per_page,
                    total: floors.total,
                    onChange: (page) =>
                        router.get('/floors', { ...filters, page }, { preserveState: true }),
                }}
            />

            <Modal
                title={editing ? 'Edit Floor' : 'New Floor'}
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
                    <Form.Item label="Level" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.level}
                            onChange={(v) => form.setData('level', v ?? 1)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
