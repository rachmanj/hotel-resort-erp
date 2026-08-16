import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, DatePicker, Form, InputNumber, Modal, Select, Switch } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface AgentRateRow {
    id: number;
    room_type?: { id: number; name: string; code: string };
    rate_plan?: { id: number; name: string } | null;
    nightly_rate?: string | null;
    discount_type?: string | null;
    discount_type_label?: string | null;
    discount_value?: string | null;
    valid_from?: string;
    valid_to?: string;
    is_active: boolean;
}

interface AgentRatesProps {
    agent: { id: number; name: string; code: string };
    rates: Paginated<AgentRateRow>;
    roomTypes: Array<{ id: number; name: string; code: string }>;
    ratePlans: Array<{ id: number; name: string }>;
    discountTypes: Array<{ value: string; label: string }>;
}

export default function AgentRates({ agent, rates, roomTypes, ratePlans, discountTypes }: AgentRatesProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<AgentRateRow | null>(null);

    const form = useForm({
        room_type_id: roomTypes[0]?.id ?? null,
        rate_plan_id: null as number | null,
        nightly_rate: null as number | null,
        discount_type: null as string | null,
        discount_value: null as number | null,
        valid_from: dayjs().format('YYYY-MM-DD'),
        valid_to: dayjs().add(1, 'year').format('YYYY-MM-DD'),
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            room_type_id: roomTypes[0]?.id ?? null,
            rate_plan_id: null,
            nightly_rate: null,
            discount_type: null,
            discount_value: null,
            valid_from: dayjs().format('YYYY-MM-DD'),
            valid_to: dayjs().add(1, 'year').format('YYYY-MM-DD'),
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEdit = (record: AgentRateRow) => {
        setEditing(record);
        form.setData({
            room_type_id: record.room_type?.id ?? null,
            rate_plan_id: record.rate_plan?.id ?? null,
            nightly_rate: record.nightly_rate ? Number(record.nightly_rate) : null,
            discount_type: record.discount_type ?? null,
            discount_value: record.discount_value ? Number(record.discount_value) : null,
            valid_from: record.valid_from ?? dayjs().format('YYYY-MM-DD'),
            valid_to: record.valid_to ?? dayjs().add(1, 'year').format('YYYY-MM-DD'),
            is_active: record.is_active,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/agents/rates/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post(`/admin/agents/${agent.id}/rates`, {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const columns: ProColumns<AgentRateRow>[] = [
        { title: 'Room Type', dataIndex: ['room_type', 'name'] },
        { title: 'Rate Plan', dataIndex: ['rate_plan', 'name'], render: (v) => v ?? '–' },
        {
            title: 'Nightly Rate',
            dataIndex: 'nightly_rate',
            render: (v) => (v ? `Rp ${Number(v).toLocaleString('id-ID')}` : '–'),
        },
        {
            title: 'Discount',
            render: (_, r) =>
                r.discount_type
                    ? `${r.discount_type_label}: ${r.discount_value}`
                    : '–',
        },
        {
            title: 'Valid',
            render: (_, r) => `${r.valid_from} → ${r.valid_to}`,
        },
        {
            title: 'Active',
            dataIndex: 'is_active',
            render: (_, r) => (r.is_active ? 'Yes' : 'No'),
        },
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
        <AuthenticatedLayout title={`Agent Rates · ${agent.name}`}>
            <Head title={`Agent Rates · ${agent.name}`} />
            <p style={{ marginBottom: 16 }}>
                <Link href="/admin/agents">← Back to Agents</Link>
            </p>
            <ProTable<AgentRateRow>
                rowKey="id"
                columns={columns}
                dataSource={rates.data}
                search={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Rate
                    </Button>,
                ]}
                pagination={{
                    current: rates.current_page,
                    pageSize: rates.per_page,
                    total: rates.total,
                    onChange: (page) =>
                        router.get(`/admin/agents/${agent.id}/rates`, { page }, { preserveState: true }),
                }}
            />

            <Modal
                title={editing ? 'Edit Agent Rate' : 'New Agent Rate'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
                width={640}
            >
                <Form layout="vertical">
                    <Form.Item label="Room Type" required>
                        <Select
                            value={form.data.room_type_id}
                            onChange={(v) => form.setData('room_type_id', v)}
                            options={roomTypes.map((rt) => ({
                                value: rt.id,
                                label: `${rt.name} (${rt.code})`,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item label="Rate Plan">
                        <Select
                            allowClear
                            value={form.data.rate_plan_id}
                            onChange={(v) => form.setData('rate_plan_id', v)}
                            options={ratePlans.map((rp) => ({ value: rp.id, label: rp.name }))}
                        />
                    </Form.Item>
                    <Form.Item label="Flat Nightly Rate">
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.nightly_rate}
                            onChange={(v) => form.setData('nightly_rate', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Discount Type">
                        <Select
                            allowClear
                            value={form.data.discount_type}
                            onChange={(v) => form.setData('discount_type', v)}
                            options={discountTypes.map((t) => ({ value: t.value, label: t.label }))}
                        />
                    </Form.Item>
                    <Form.Item label="Discount Value">
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.discount_value}
                            onChange={(v) => form.setData('discount_value', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Valid From" required>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={dayjs(form.data.valid_from)}
                            onChange={(d) => form.setData('valid_from', d?.format('YYYY-MM-DD') ?? '')}
                        />
                    </Form.Item>
                    <Form.Item label="Valid To" required>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={dayjs(form.data.valid_to)}
                            onChange={(d) => form.setData('valid_to', d?.format('YYYY-MM-DD') ?? '')}
                        />
                    </Form.Item>
                    <Form.Item label="Active">
                        <Switch
                            checked={form.data.is_active}
                            onChange={(c) => form.setData('is_active', c)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
