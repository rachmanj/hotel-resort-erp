import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal, Switch } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface BoatUnitRow {
    id: number;
    code: string;
    name: string;
    capacity: number;
    engine_hp: number;
    is_own: boolean;
    is_active: boolean;
}

interface BoatUnitsIndexProps {
    boatUnits: Paginated<BoatUnitRow>;
    filters: { search?: string };
}

export default function BoatUnitsIndex({ boatUnits, filters }: BoatUnitsIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<BoatUnitRow | null>(null);

    const form = useForm({
        code: '',
        name: '',
        capacity: 1,
        engine_hp: 1,
        is_own: true,
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            code: '',
            name: '',
            capacity: 1,
            engine_hp: 1,
            is_own: true,
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEdit = (record: BoatUnitRow) => {
        setEditing(record);
        form.setData({
            code: record.code,
            name: record.name,
            capacity: record.capacity,
            engine_hp: record.engine_hp,
            is_own: record.is_own,
            is_active: record.is_active,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/boat-units/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/boat-units', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const deleteUnit = (record: BoatUnitRow) => {
        Modal.confirm({
            title: 'Delete boat unit?',
            content: `Delete "${record.name}"? This cannot be undone.`,
            onOk: () => router.delete(`/admin/boat-units/${record.id}`),
        });
    };

    const columns: ProColumns<BoatUnitRow>[] = [
        { title: 'Code', dataIndex: 'code', fieldProps: { placeholder: 'Code' } },
        { title: 'Name', dataIndex: 'name', fieldProps: { placeholder: 'Name' } },
        { title: 'Capacity', dataIndex: 'capacity', search: false },
        { title: 'Engine HP', dataIndex: 'engine_hp', search: false },
        {
            title: 'Own/Vendor',
            dataIndex: 'is_own',
            search: false,
            render: (_, record) => (record.is_own ? 'Own' : 'Vendor'),
        },
        {
            title: 'Active',
            dataIndex: 'is_active',
            search: false,
            render: (_, record) => (record.is_active ? 'Yes' : 'No'),
        },
        {
            title: 'Search',
            dataIndex: 'search',
            hideInTable: true,
            fieldProps: { placeholder: 'Code or name' },
        },
        {
            title: 'Actions',
            valueType: 'option',
            render: (_, record) => [
                <Button key="edit" type="link" onClick={() => openEdit(record)}>
                    Edit
                </Button>,
                <Button key="delete" type="link" danger onClick={() => deleteUnit(record)}>
                    Delete
                </Button>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Boat Units">
            <Head title="Boat Units" />
            <ProTable<BoatUnitRow>
                rowKey="id"
                columns={columns}
                dataSource={boatUnits.data}
                options={false}
                search={{
                    searchText: 'Search',
                    resetText: 'Reset',
                    labelWidth: 'auto',
                    defaultCollapsed: false,
                }}
                form={{
                    initialValues: { search: filters.search },
                }}
                onSubmit={(params) =>
                    router.get(
                        '/admin/boat-units',
                        { search: params.search || undefined },
                        { preserveState: true },
                    )
                }
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Boat Unit
                    </Button>,
                ]}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: boatUnits.current_page,
                    pageSize: boatUnits.per_page,
                    total: boatUnits.total,
                    onChange: (page) =>
                        router.get(
                            '/admin/boat-units',
                            { ...filters, page },
                            { preserveState: true },
                        ),
                }}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={editing ? 'Edit Boat Unit' : 'New Boat Unit'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Code" required>
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Capacity" required>
                        <InputNumber
                            min={1}
                            style={{ width: '100%' }}
                            value={form.data.capacity}
                            onChange={(v) => form.setData('capacity', v ?? 1)}
                        />
                    </Form.Item>
                    <Form.Item label="Engine HP" required>
                        <InputNumber
                            min={1}
                            style={{ width: '100%' }}
                            value={form.data.engine_hp}
                            onChange={(v) => form.setData('engine_hp', v ?? 1)}
                        />
                    </Form.Item>
                    <Form.Item label="Own Boat">
                        <Switch
                            checked={form.data.is_own}
                            onChange={(checked) => form.setData('is_own', checked)}
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
