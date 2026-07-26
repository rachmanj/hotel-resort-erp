import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal, Select, Switch } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface RatePlanRow {
    id: number;
    name: string;
    rate_type: string;
    rate_type_label: string;
    nightly_rate: string;
    is_active: boolean;
    valid_from?: string | null;
    valid_to?: string | null;
    room_type?: { id: number; name: string; code: string };
    season?: { id: number; name: string } | null;
}

interface RatePlansIndexProps {
    ratePlans: Paginated<RatePlanRow>;
    roomTypes: Array<{ id: number; name: string; code: string }>;
    seasons: Array<{ id: number; name: string }>;
    rateTypes: Array<{ value: string; label: string }>;
    filters: { search?: string };
}

export default function RatePlansIndex({
    ratePlans,
    roomTypes,
    seasons,
    rateTypes,
    filters,
}: RatePlansIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<RatePlanRow | null>(null);

    const form = useForm({
        room_type_id: roomTypes[0]?.id ?? null,
        season_id: null as number | null,
        name: '',
        rate_type: 'standard',
        nightly_rate: 0,
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            room_type_id: roomTypes[0]?.id ?? null,
            season_id: null,
            name: '',
            rate_type: 'standard',
            nightly_rate: 0,
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEdit = (record: RatePlanRow) => {
        setEditing(record);
        form.setData({
            room_type_id: record.room_type?.id ?? null,
            season_id: record.season?.id ?? null,
            name: record.name,
            rate_type: record.rate_type,
            nightly_rate: Number(record.nightly_rate),
            is_active: record.is_active,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/rate-plans/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/rate-plans', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const columns: ProColumns<RatePlanRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Room Type', dataIndex: ['room_type', 'name'] },
        { title: 'Type', dataIndex: 'rate_type_label' },
        {
            title: 'Nightly Rate',
            dataIndex: 'nightly_rate',
            render: (_, r) => `Rp ${Number(r.nightly_rate).toLocaleString('id-ID')}`,
        },
        { title: 'Season', dataIndex: ['season', 'name'], render: (v) => v ?? '—' },
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
        <AuthenticatedLayout title="Rate Plans">
            <Head title="Rate Plans" />
            <ProTable<RatePlanRow>
                rowKey="id"
                columns={columns}
                dataSource={ratePlans.data}
                search={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Rate Plan
                    </Button>,
                ]}
                pagination={{
                    current: ratePlans.current_page,
                    pageSize: ratePlans.per_page,
                    total: ratePlans.total,
                    onChange: (page) =>
                        router.get('/admin/rate-plans', { ...filters, page }, { preserveState: true }),
                }}
            />

            <Modal
                title={editing ? 'Edit Rate Plan' : 'New Rate Plan'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Room type" required>
                        <Select
                            value={form.data.room_type_id}
                            onChange={(v) => form.setData('room_type_id', v)}
                            options={roomTypes.map((rt) => ({
                                value: rt.id,
                                label: `${rt.name} (${rt.code})`,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item label="Season">
                        <Select
                            allowClear
                            value={form.data.season_id}
                            onChange={(v) => form.setData('season_id', v)}
                            options={seasons.map((s) => ({ value: s.id, label: s.name }))}
                        />
                    </Form.Item>
                    <Form.Item label="Name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Rate type" required>
                        <Select
                            value={form.data.rate_type}
                            onChange={(v) => form.setData('rate_type', v)}
                            options={rateTypes.map((t) => ({ value: t.value, label: t.label }))}
                        />
                    </Form.Item>
                    <Form.Item label="Nightly rate" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.nightly_rate}
                            onChange={(v) => form.setData('nightly_rate', v ?? 0)}
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
