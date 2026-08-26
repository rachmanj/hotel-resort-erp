import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal, Select, Switch } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface OtaFeeRow {
    id: number;
    code: string;
    name: string;
    fee_type: string;
    fee_type_label: string;
    base_fee_pct: string | null;
    variable_fee_pct: string | null;
    flat_fee_per_room_night: string | null;
    is_active: boolean;
}

interface OtaFeesIndexProps {
    otaFees: Paginated<OtaFeeRow>;
    filters: { search?: string };
}

const feeTypeOptions = [
    { value: 'percent', label: 'Percent' },
    { value: 'flat', label: 'Flat' },
];

export default function OtaFeesIndex({ otaFees, filters }: OtaFeesIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<OtaFeeRow | null>(null);

    const form = useForm({
        code: '',
        name: '',
        fee_type: 'percent' as string,
        base_fee_pct: null as number | null,
        variable_fee_pct: null as number | null,
        flat_fee_per_room_night: null as number | null,
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            code: '',
            name: '',
            fee_type: 'percent',
            base_fee_pct: null,
            variable_fee_pct: null,
            flat_fee_per_room_night: null,
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEdit = (record: OtaFeeRow) => {
        setEditing(record);
        form.setData({
            code: record.code,
            name: record.name,
            fee_type: record.fee_type,
            base_fee_pct: record.base_fee_pct !== null ? Number(record.base_fee_pct) : null,
            variable_fee_pct: record.variable_fee_pct !== null ? Number(record.variable_fee_pct) : null,
            flat_fee_per_room_night:
                record.flat_fee_per_room_night !== null ? Number(record.flat_fee_per_room_night) : null,
            is_active: record.is_active,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/ota-fees/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/ota-fees', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const deleteFee = (record: OtaFeeRow) => {
        Modal.confirm({
            title: 'Delete OTA fee?',
            content: `Delete "${record.name}"? This cannot be undone.`,
            onOk: () => router.delete(`/admin/ota-fees/${record.id}`),
        });
    };

    const columns: ProColumns<OtaFeeRow>[] = [
        { title: 'Code', dataIndex: 'code', fieldProps: { placeholder: 'Code' } },
        { title: 'Name', dataIndex: 'name', fieldProps: { placeholder: 'Name' } },
        {
            title: 'Type',
            dataIndex: 'fee_type',
            search: false,
            render: (_, record) => record.fee_type_label,
        },
        {
            title: 'Base %',
            dataIndex: 'base_fee_pct',
            search: false,
            render: (value) => (value !== null && value !== undefined ? `${value}%` : '–'),
        },
        {
            title: 'Variable %',
            dataIndex: 'variable_fee_pct',
            search: false,
            render: (value) => (value !== null && value !== undefined ? `${value}%` : '–'),
        },
        {
            title: 'Flat/Room-Night',
            dataIndex: 'flat_fee_per_room_night',
            search: false,
            render: (value) => (value !== null && value !== undefined ? Number(value).toLocaleString() : '–'),
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
                <Button key="delete" type="link" danger onClick={() => deleteFee(record)}>
                    Delete
                </Button>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="OTA Fees">
            <Head title="OTA Fees" />
            <ProTable<OtaFeeRow>
                rowKey="id"
                columns={columns}
                dataSource={otaFees.data}
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
                        '/admin/ota-fees',
                        { search: params.search || undefined },
                        { preserveState: true },
                    )
                }
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New OTA Fee
                    </Button>,
                ]}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: otaFees.current_page,
                    pageSize: otaFees.per_page,
                    total: otaFees.total,
                    onChange: (page) =>
                        router.get('/admin/ota-fees', { ...filters, page }, { preserveState: true }),
                }}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={editing ? 'Edit OTA Fee' : 'New OTA Fee'}
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
                    <Form.Item label="Fee Type" required>
                        <Select
                            value={form.data.fee_type}
                            onChange={(v) => form.setData('fee_type', v)}
                            options={feeTypeOptions}
                        />
                    </Form.Item>
                    {form.data.fee_type === 'percent' && (
                        <>
                            <Form.Item label="Base %" required>
                                <InputNumber
                                    min={0}
                                    style={{ width: '100%' }}
                                    value={form.data.base_fee_pct}
                                    onChange={(v) => form.setData('base_fee_pct', v)}
                                />
                            </Form.Item>
                            <Form.Item label="Variable %">
                                <InputNumber
                                    min={0}
                                    style={{ width: '100%' }}
                                    value={form.data.variable_fee_pct}
                                    onChange={(v) => form.setData('variable_fee_pct', v)}
                                />
                            </Form.Item>
                        </>
                    )}
                    {form.data.fee_type === 'flat' && (
                        <Form.Item label="Flat Fee per Room-Night" required>
                            <InputNumber
                                min={0}
                                style={{ width: '100%' }}
                                value={form.data.flat_fee_per_room_night}
                                onChange={(v) => form.setData('flat_fee_per_room_night', v)}
                            />
                        </Form.Item>
                    )}
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
