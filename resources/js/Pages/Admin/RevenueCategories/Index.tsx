import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal, Switch } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface RevenueCategoryRow {
    id: number;
    code: string;
    name: string;
    coa_account_code?: string | null;
    sort_order: number;
    is_active: boolean;
}

interface RevenueCategoriesIndexProps {
    revenueCategories: Paginated<RevenueCategoryRow>;
    filters: { search?: string };
}

export default function RevenueCategoriesIndex({
    revenueCategories,
    filters,
}: RevenueCategoriesIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<RevenueCategoryRow | null>(null);

    const form = useForm({
        code: '',
        name: '',
        coa_account_code: '',
        sort_order: 0,
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            code: '',
            name: '',
            coa_account_code: '',
            sort_order: 0,
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEdit = (record: RevenueCategoryRow) => {
        setEditing(record);
        form.setData({
            code: record.code,
            name: record.name,
            coa_account_code: record.coa_account_code ?? '',
            sort_order: record.sort_order,
            is_active: record.is_active,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/revenue-categories/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/revenue-categories', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const deleteCategory = (record: RevenueCategoryRow) => {
        Modal.confirm({
            title: 'Delete revenue category?',
            content: `Delete "${record.name}"? This cannot be undone.`,
            onOk: () => router.delete(`/admin/revenue-categories/${record.id}`),
        });
    };

    const columns: ProColumns<RevenueCategoryRow>[] = [
        { title: 'Code', dataIndex: 'code', fieldProps: { placeholder: 'Code' } },
        { title: 'Name', dataIndex: 'name', fieldProps: { placeholder: 'Name' } },
        {
            title: 'COA Account',
            dataIndex: 'coa_account_code',
            fieldProps: { placeholder: 'COA account code' },
            render: (value) => value ?? '–',
        },
        { title: 'Sort Order', dataIndex: 'sort_order', search: false },
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
            fieldProps: { placeholder: 'Code, name, or COA account' },
        },
        {
            title: 'Actions',
            valueType: 'option',
            render: (_, record) => [
                <Button key="edit" type="link" onClick={() => openEdit(record)}>
                    Edit
                </Button>,
                <Button key="delete" type="link" danger onClick={() => deleteCategory(record)}>
                    Delete
                </Button>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Revenue Categories">
            <Head title="Revenue Categories" />
            <ProTable<RevenueCategoryRow>
                rowKey="id"
                columns={columns}
                dataSource={revenueCategories.data}
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
                        '/admin/revenue-categories',
                        { search: params.search || undefined },
                        { preserveState: true },
                    )
                }
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Revenue Category
                    </Button>,
                ]}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: revenueCategories.current_page,
                    pageSize: revenueCategories.per_page,
                    total: revenueCategories.total,
                    onChange: (page) =>
                        router.get(
                            '/admin/revenue-categories',
                            { ...filters, page },
                            { preserveState: true },
                        ),
                }}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={editing ? 'Edit Revenue Category' : 'New Revenue Category'}
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
                    <Form.Item label="COA Account Code">
                        <Input
                            placeholder="e.g. 4-2100"
                            value={form.data.coa_account_code}
                            onChange={(e) => form.setData('coa_account_code', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Sort Order">
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.sort_order}
                            onChange={(v) => form.setData('sort_order', v ?? 0)}
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
