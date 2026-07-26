import { Head, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, InputNumber, Modal, Select, Switch } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface TaxRuleRow {
    id: number;
    name: string;
    code: string;
    rate_percent: string;
    applies_to: string;
    is_compounding: boolean;
    is_active: boolean;
    order: number;
}

interface TaxRulesIndexProps {
    taxRules: TaxRuleRow[];
}

const appliesToOptions = [
    { value: 'all', label: 'All' },
    { value: 'room', label: 'Room' },
    { value: 'fb', label: 'F&B' },
    { value: 'spa', label: 'Spa' },
];

export default function TaxRulesIndex({ taxRules }: TaxRulesIndexProps) {
    const [editing, setEditing] = useState<TaxRuleRow | null>(null);
    const form = useForm({
        rate_percent: 0,
        applies_to: 'all',
        is_compounding: false,
        is_active: true,
        order: 0,
    });

    const openEdit = (record: TaxRuleRow) => {
        setEditing(record);
        form.setData({
            rate_percent: Number(record.rate_percent),
            applies_to: record.applies_to,
            is_compounding: record.is_compounding,
            is_active: record.is_active,
            order: record.order,
        });
    };

    const submit = () => {
        if (!editing) return;
        form.put(`/admin/tax-rules/${editing.id}`, {
            onSuccess: () => setEditing(null),
        });
    };

    const columns: ProColumns<TaxRuleRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Code', dataIndex: 'code' },
        { title: 'Rate %', dataIndex: 'rate_percent', render: (v) => `${v}%` },
        { title: 'Applies To', dataIndex: 'applies_to' },
        {
            title: 'Compounding',
            dataIndex: 'is_compounding',
            render: (v) => (v ? 'Yes' : 'No'),
        },
        {
            title: 'Active',
            dataIndex: 'is_active',
            render: (v) => (v ? 'Yes' : 'No'),
        },
        { title: 'Order', dataIndex: 'order' },
        {
            title: 'Actions',
            render: (_, record) => (
                <Button size="small" onClick={() => openEdit(record)}>
                    Edit
                </Button>
            ),
        },
    ];

    return (
        <AuthenticatedLayout title="Tax Rules">
            <Head title="Tax Rules" />
            <ProTable<TaxRuleRow>
                rowKey="id"
                search={false}
                options={false}
                pagination={false}
                dataSource={taxRules}
                columns={columns}
            />

            <Modal
                title={editing ? `Edit ${editing.name}` : 'Edit Tax Rule'}
                open={!!editing}
                onCancel={() => setEditing(null)}
                onOk={submit}
                confirmLoading={form.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Rate %">
                        <InputNumber
                            min={0}
                            max={100}
                            step={0.01}
                            style={{ width: '100%' }}
                            value={form.data.rate_percent}
                            onChange={(v) => form.setData('rate_percent', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Applies To">
                        <Select
                            value={form.data.applies_to}
                            onChange={(v) => form.setData('applies_to', v)}
                            options={appliesToOptions}
                        />
                    </Form.Item>
                    <Form.Item label="Compounding">
                        <Switch
                            checked={form.data.is_compounding}
                            onChange={(v) => form.setData('is_compounding', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Active">
                        <Switch checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} />
                    </Form.Item>
                    <Form.Item label="Calculation Order">
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.order}
                            onChange={(v) => form.setData('order', v ?? 0)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
