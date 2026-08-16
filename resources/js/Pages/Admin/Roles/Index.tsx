import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, Modal, Select, Tag } from 'antd';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface RoleRow {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
    permissions: string[];
}

interface PermissionOption {
    id: number;
    name: string;
}

interface PermissionGroup {
    category: string;
    permissions: PermissionOption[];
}

interface RolesIndexProps {
    roles: RoleRow[];
    permissionGroups: PermissionGroup[];
}

export default function RolesIndex({ roles, permissionGroups }: RolesIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<RoleRow | null>(null);

    const form = useForm({
        name: '',
        permissions: [] as string[],
    });

    const permissionOptions = useMemo(
        () =>
            permissionGroups.map((group) => ({
                label: group.category,
                options: group.permissions.map((p) => ({
                    value: p.name,
                    label: p.name,
                })),
            })),
        [permissionGroups],
    );

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({ name: '', permissions: [] });
        setModalOpen(true);
    };

    const openEdit = (record: RoleRow) => {
        setEditing(record);
        form.setData({
            name: record.name,
            permissions: record.permissions,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/roles/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/roles', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const handleDelete = (record: RoleRow) => {
        if (record.users_count > 0) {
            Modal.warning({
                title: 'Cannot delete role',
                content: `The "${record.name}" role has ${record.users_count} user(s) assigned. Reassign users before deleting.`,
            });
            return;
        }

        router.delete(`/admin/roles/${record.id}`);
    };

    const columns: ProColumns<RoleRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        {
            title: 'Permissions',
            dataIndex: 'permissions_count',
            render: (_, record) => <Tag color="blue">{record.permissions_count}</Tag>,
        },
        {
            title: 'Actions',
            valueType: 'option',
            render: (_, record) => [
                <Button key="edit" type="link" onClick={() => openEdit(record)}>
                    Edit
                </Button>,
                <Button key="delete" type="link" danger onClick={() => handleDelete(record)}>
                    Delete
                </Button>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Roles">
            <Head title="Roles" />
            <ProTable<RoleRow>
                rowKey="id"
                columns={columns}
                dataSource={roles}
                search={false}
                pagination={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Role
                    </Button>,
                ]}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={editing ? 'Edit Role' : 'New Role'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
                width={640}
            >
                <Form layout="vertical">
                    <Form.Item label="Name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Permissions">
                        <Select
                            mode="multiple"
                            placeholder="Select permissions"
                            value={form.data.permissions}
                            onChange={(v) => form.setData('permissions', v)}
                            options={permissionOptions}
                            optionFilterProp="label"
                            style={{ width: '100%' }}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
