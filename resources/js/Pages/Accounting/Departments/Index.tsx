import { Head, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, Modal, Select, Switch, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface DepartmentRow {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    is_global: boolean;
}

interface DepartmentsIndexProps {
    departments: DepartmentRow[];
}

export default function DepartmentsIndex({ departments }: DepartmentsIndexProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editRow, setEditRow] = useState<DepartmentRow | null>(null);

    const createForm = useForm({ code: '', name: '' });
    const editForm = useForm({ name: '', is_active: true });

    const columns: ProColumns<DepartmentRow>[] = [
        { title: 'Code', dataIndex: 'code', width: 140 },
        { title: 'Name', dataIndex: 'name' },
        {
            title: 'Scope',
            width: 100,
            render: (_, r) => (r.is_global ? <Tag color="blue">Global</Tag> : <Tag>Hotel</Tag>),
        },
        {
            title: 'Status',
            width: 90,
            render: (_, r) => (r.is_active ? <Tag color="green">Active</Tag> : <Tag color="red">Inactive</Tag>),
        },
        {
            title: 'Actions',
            width: 90,
            render: (_, r) => (
                <Button
                    size="small"
                    onClick={() => {
                        setEditRow(r);
                        editForm.setData({ name: r.name, is_active: r.is_active });
                    }}
                >
                    Edit
                </Button>
            ),
        },
    ];

    return (
        <AuthenticatedLayout title="Departments">
            <Head title="Departments" />
            <div style={{ marginBottom: 16 }}>
                <Button type="primary" onClick={() => setCreateOpen(true)}>New Department</Button>
            </div>
            <ProTable
                rowKey="id"
                search={false}
                options={false}
                pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }}
                dataSource={departments}
                columns={columns}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title="New Department"
                open={createOpen}
                onCancel={() => setCreateOpen(false)}
                onOk={() => createForm.post('/accounting/departments', { onSuccess: () => { setCreateOpen(false); createForm.reset(); } })}
                confirmLoading={createForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Code" required>
                        <Input
                            placeholder="e.g. kitchen"
                            value={createForm.data.code}
                            onChange={(e) => createForm.setData('code', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Name" required>
                        <Input
                            placeholder="Department name"
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title="Edit Department"
                open={editRow !== null}
                onCancel={() => setEditRow(null)}
                onOk={() => {
                    if (!editRow) return;
                    editForm.put(`/accounting/departments/${editRow.id}`, { onSuccess: () => setEditRow(null) });
                }}
                confirmLoading={editForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Name" required>
                        <Input
                            placeholder="Department name"
                            value={editForm.data.name}
                            onChange={(e) => editForm.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Active">
                        <Switch
                            checked={editForm.data.is_active}
                            onChange={(v) => editForm.setData('is_active', v)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
