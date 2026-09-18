import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, DatePicker, Form, InputNumber, Modal, Select, Switch } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface AgentTierRateRow {
    id: number;
    rate_category?: string;
    rate_category_label?: string;
    room_type?: { id: number; name: string; code: string };
    nightly_rate?: string;
    valid_from?: string;
    valid_to?: string;
    is_active: boolean;
}

interface AgentTierRatesIndexProps {
    rates: Paginated<AgentTierRateRow>;
    roomTypes: Array<{ id: number; name: string; code: string }>;
    tiers: Array<{ value: string; label: string }>;
}

export default function AgentTierRatesIndex({ rates, roomTypes, tiers }: AgentTierRatesIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<AgentTierRateRow | null>(null);

    const form = useForm({
        rate_category: tiers[0]?.value ?? 'A',
        room_type_id: roomTypes[0]?.id ?? null,
        nightly_rate: null as number | null,
        valid_from: dayjs().format('YYYY-MM-DD'),
        valid_to: dayjs().add(1, 'year').format('YYYY-MM-DD'),
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            rate_category: tiers[0]?.value ?? 'A',
            room_type_id: roomTypes[0]?.id ?? null,
            nightly_rate: null,
            valid_from: dayjs().format('YYYY-MM-DD'),
            valid_to: dayjs().add(1, 'year').format('YYYY-MM-DD'),
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEdit = (record: AgentTierRateRow) => {
        setEditing(record);
        form.setData({
            rate_category: record.rate_category ?? tiers[0]?.value ?? 'A',
            room_type_id: record.room_type?.id ?? null,
            nightly_rate: record.nightly_rate ? Number(record.nightly_rate) : null,
            valid_from: record.valid_from ?? dayjs().format('YYYY-MM-DD'),
            valid_to: record.valid_to ?? dayjs().add(1, 'year').format('YYYY-MM-DD'),
            is_active: record.is_active,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/agent-tier-rates/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/agent-tier-rates', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const columns: ProColumns<AgentTierRateRow>[] = [
        { title: 'Tier', dataIndex: 'rate_category_label' },
        { title: 'Room Type', dataIndex: ['room_type', 'name'] },
        {
            title: 'Nightly Rate',
            dataIndex: 'nightly_rate',
            render: (v) => (v ? `Rp ${Number(v).toLocaleString('id-ID')}` : '–'),
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
        <AuthenticatedLayout title="Agent Tier Rates">
            <Head title="Agent Tier Rates" />
            <p style={{ marginBottom: 16 }}>
                <Link href="/admin/agents">← Back to Agents</Link>
            </p>
            <ProTable<AgentTierRateRow>
                rowKey="id"
                columns={columns}
                dataSource={rates.data}
                search={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Tier Rate
                    </Button>,
                ]}
                pagination={{
                    current: rates.current_page,
                    pageSize: rates.per_page,
                    total: rates.total,
                    onChange: (page) =>
                        router.get('/admin/agent-tier-rates', { page }, { preserveState: true }),
                }}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={editing ? 'Edit Agent Tier Rate' : 'New Agent Tier Rate'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
                width={640}
            >
                <Form layout="vertical">
                    <Form.Item label="Tier" required>
                        <Select
                            value={form.data.rate_category}
                            onChange={(v) => form.setData('rate_category', v)}
                            options={tiers.map((tier) => ({ value: tier.value, label: tier.label }))}
                        />
                    </Form.Item>
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
                    <Form.Item label="Nightly Rate" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.nightly_rate}
                            onChange={(v) => form.setData('nightly_rate', v)}
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
