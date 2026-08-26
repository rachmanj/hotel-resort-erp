import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal, Select, Switch } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface DivePackageRow {
    id: number;
    code: string;
    name: string;
    type: string;
    type_label: string;
    price_per_person: number;
    min_pax: number;
    includes?: string | null;
    is_active: boolean;
}

interface OptionItem {
    value: string;
    label: string;
}

interface DivePackagesIndexProps {
    divePackages: Paginated<DivePackageRow>;
    packageTypes: OptionItem[];
    filters: { search?: string };
}

export default function DivePackagesIndex({
    divePackages,
    packageTypes,
    filters,
}: DivePackagesIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<DivePackageRow | null>(null);

    const form = useForm({
        code: '',
        name: '',
        type: '',
        price_per_person: 0,
        min_pax: 1,
        includes: '',
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            code: '',
            name: '',
            type: packageTypes[0]?.value ?? '',
            price_per_person: 0,
            min_pax: 1,
            includes: '',
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEdit = (record: DivePackageRow) => {
        setEditing(record);
        form.setData({
            code: record.code,
            name: record.name,
            type: record.type,
            price_per_person: record.price_per_person,
            min_pax: record.min_pax,
            includes: record.includes ?? '',
            is_active: record.is_active,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/dive-packages/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/dive-packages', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const deletePackage = (record: DivePackageRow) => {
        Modal.confirm({
            title: 'Delete dive package?',
            content: `Delete "${record.name}"? This cannot be undone.`,
            onOk: () => router.delete(`/admin/dive-packages/${record.id}`),
        });
    };

    const columns: ProColumns<DivePackageRow>[] = [
        { title: 'Code', dataIndex: 'code', fieldProps: { placeholder: 'Code' } },
        { title: 'Name', dataIndex: 'name', fieldProps: { placeholder: 'Name' } },
        {
            title: 'Type',
            dataIndex: 'type_label',
            search: false,
        },
        {
            title: 'Price',
            dataIndex: 'price_per_person',
            search: false,
            render: (_, record) => record.price_per_person.toLocaleString('en-US'),
        },
        { title: 'Min Pax', dataIndex: 'min_pax', search: false },
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
                <Button key="delete" type="link" danger onClick={() => deletePackage(record)}>
                    Delete
                </Button>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Dive Packages">
            <Head title="Dive Packages" />
            <ProTable<DivePackageRow>
                rowKey="id"
                columns={columns}
                dataSource={divePackages.data}
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
                        '/admin/dive-packages',
                        { search: params.search || undefined },
                        { preserveState: true },
                    )
                }
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Dive Package
                    </Button>,
                ]}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: divePackages.current_page,
                    pageSize: divePackages.per_page,
                    total: divePackages.total,
                    onChange: (page) =>
                        router.get(
                            '/admin/dive-packages',
                            { ...filters, page },
                            { preserveState: true },
                        ),
                }}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={editing ? 'Edit Dive Package' : 'New Dive Package'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
                width={560}
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
                    <Form.Item label="Type" required>
                        <Select
                            value={form.data.type || undefined}
                            options={packageTypes}
                            onChange={(value) => form.setData('type', value)}
                        />
                    </Form.Item>
                    <Form.Item label="Price Per Person" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.price_per_person}
                            onChange={(v) => form.setData('price_per_person', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Min Pax" required>
                        <InputNumber
                            min={1}
                            style={{ width: '100%' }}
                            value={form.data.min_pax}
                            onChange={(v) => form.setData('min_pax', v ?? 1)}
                        />
                    </Form.Item>
                    <Form.Item label="Includes">
                        <Input.TextArea
                            rows={3}
                            value={form.data.includes}
                            onChange={(e) => form.setData('includes', e.target.value)}
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
