import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, Modal, Select, Space, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface UserRow {
    id: number;
    name: string;
    email: string;
    hotel_id: number | null;
    roles: string[];
    roles_label: string;
    home_hotel?: { id: number; name: string; code: string } | null;
}

interface UsersIndexProps {
    users: Paginated<UserRow>;
    hotels: Array<{ id: number; name: string; code: string }>;
    roles: Array<{ id: number; name: string }>;
    filters: { search?: string };
}

export default function UsersIndex({ users, hotels, roles, filters }: UsersIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<UserRow | null>(null);

    const form = useForm({
        name: '',
        email: '',
        password: '',
        hotel_id: null as number | null,
        roles: [] as string[],
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            name: '',
            email: '',
            password: '',
            hotel_id: null,
            roles: [],
        });
        setModalOpen(true);
    };

    const openEdit = (record: UserRow) => {
        setEditing(record);
        form.setData({
            name: record.name,
            email: record.email,
            password: '',
            hotel_id: record.hotel_id,
            roles: record.roles,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/users/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/users', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const columns: ProColumns<UserRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Email', dataIndex: 'email' },
        {
            title: 'Hotel',
            dataIndex: ['home_hotel', 'name'],
            render: (_, record) => record.home_hotel?.name ?? '—',
        },
        {
            title: 'Roles',
            dataIndex: 'roles',
            render: (_, record) => (
                <Space size={[0, 4]} wrap>
                    {record.roles.map((role) => (
                        <Tag key={role}>{role}</Tag>
                    ))}
                </Space>
            ),
        },
        {
            title: 'Search',
            dataIndex: 'search',
            hideInTable: true,
            fieldProps: { placeholder: 'Name or email' },
        },
        {
            title: 'Actions',
            valueType: 'option',
            render: (_, record) => [
                <Button key="edit" type="link" onClick={() => openEdit(record)}>
                    Edit
                </Button>,
                <Button
                    key="delete"
                    type="link"
                    danger
                    onClick={() => router.delete(`/admin/users/${record.id}`)}
                >
                    Delete
                </Button>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Users">
            <Head title="Users" />
            <ProTable<UserRow>
                rowKey="id"
                columns={columns}
                dataSource={users.data}
                search={{
                    labelWidth: 'auto',
                    defaultCollapsed: false,
                }}
                form={{
                    initialValues: { search: filters.search },
                }}
                onSubmit={(params) =>
                    router.get(
                        '/admin/users',
                        { search: params.search || undefined },
                        { preserveState: true },
                    )
                }
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New User
                    </Button>,
                ]}
                pagination={{
                    current: users.current_page,
                    pageSize: users.per_page,
                    total: users.total,
                    onChange: (page) =>
                        router.get('/admin/users', { ...filters, page }, { preserveState: true }),
                }}
            />

            <Modal
                title={editing ? 'Edit User' : 'New User'}
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
                    <Form.Item label="Email" required>
                        <Input
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Password" required={!editing}>
                        <Input.Password
                            placeholder={editing ? 'Leave blank to keep' : undefined}
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Hotel">
                        <Select
                            allowClear
                            placeholder="Select hotel"
                            value={form.data.hotel_id}
                            onChange={(v) => form.setData('hotel_id', v ?? null)}
                            options={hotels.map((h) => ({
                                value: h.id,
                                label: `${h.name} (${h.code})`,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item label="Roles">
                        <Select
                            mode="multiple"
                            placeholder="Select roles"
                            value={form.data.roles}
                            onChange={(v) => form.setData('roles', v)}
                            options={roles.map((r) => ({ value: r.name, label: r.name }))}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
