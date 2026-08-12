import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, InputNumber, Modal, Select, Switch, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface AgentRow {
    id: number;
    name: string;
    code: string;
    agent_type: string;
    agent_type_label: string;
    channel_code?: string | null;
    contact_person?: string | null;
    phone?: string | null;
    email?: string | null;
    commission_percent: string;
    commission_basis: string;
    commission_basis_label: string;
    payment_terms_days: number;
    company?: { id: number; name: string } | null;
    user?: { id: number; name: string; email: string } | null;
    is_active: boolean;
}

interface AgentsIndexProps {
    agents: Paginated<AgentRow>;
    companies: Array<{ id: number; name: string }>;
    users: Array<{ id: number; name: string; email: string }>;
    agentTypes: Array<{ value: string; label: string }>;
    commissionBases: Array<{ value: string; label: string }>;
    filters: { search?: string };
}

export default function AgentsIndex({
    agents,
    companies,
    users,
    agentTypes,
    commissionBases,
    filters,
}: AgentsIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<AgentRow | null>(null);

    const form = useForm({
        agent_type: 'travel',
        name: '',
        code: '',
        channel_code: '',
        contact_person: '',
        phone: '',
        email: '',
        commission_percent: 10,
        commission_basis: 'net_room',
        payment_terms_days: 30,
        company_id: null as number | null,
        user_id: null as number | null,
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            agent_type: 'travel',
            name: '',
            code: '',
            channel_code: '',
            contact_person: '',
            phone: '',
            email: '',
            commission_percent: 10,
            commission_basis: 'net_room',
            payment_terms_days: 30,
            company_id: null,
            user_id: null,
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEdit = (record: AgentRow) => {
        setEditing(record);
        form.setData({
            agent_type: record.agent_type,
            name: record.name,
            code: record.code,
            channel_code: record.channel_code ?? '',
            contact_person: record.contact_person ?? '',
            phone: record.phone ?? '',
            email: record.email ?? '',
            commission_percent: Number(record.commission_percent),
            commission_basis: record.commission_basis,
            payment_terms_days: record.payment_terms_days,
            company_id: record.company?.id ?? null,
            user_id: record.user?.id ?? null,
            is_active: record.is_active,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/agents/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/agents', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const columns: ProColumns<AgentRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Code', dataIndex: 'code' },
        {
            title: 'Type',
            dataIndex: 'agent_type_label',
            render: (_, r) => <Tag>{r.agent_type_label}</Tag>,
        },
        {
            title: 'Channel',
            dataIndex: 'channel_code',
            render: (v) => v ?? '—',
        },
        {
            title: 'Commission',
            render: (_, r) => `${r.commission_percent}% (${r.commission_basis_label})`,
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
                <Link key="rates" href={`/admin/agents/${record.id}/rates`}>
                    Rates
                </Link>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Agents">
            <Head title="Agents" />
            <ProTable<AgentRow>
                rowKey="id"
                columns={columns}
                dataSource={agents.data}
                search={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Agent
                    </Button>,
                ]}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: agents.current_page,
                    pageSize: agents.per_page,
                    total: agents.total,
                    onChange: (page) =>
                        router.get('/admin/agents', { ...filters, page }, { preserveState: true }),
                }}
            />

            <Modal
                title={editing ? 'Edit Agent' : 'New Agent'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
                width={640}
            >
                <Form layout="vertical">
                    <Form.Item label="Type" required>
                        <Select
                            value={form.data.agent_type}
                            onChange={(v) => form.setData('agent_type', v)}
                            options={agentTypes.map((t) => ({ value: t.value, label: t.label }))}
                        />
                    </Form.Item>
                    <Form.Item label="Name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Code" required>
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="OTA Channel Code">
                        <Input
                            placeholder="booking_com, traveloka, agoda, expedia"
                            value={form.data.channel_code}
                            onChange={(e) => form.setData('channel_code', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Contact Person">
                        <Input
                            value={form.data.contact_person}
                            onChange={(e) => form.setData('contact_person', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Phone">
                        <Input
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Email">
                        <Input
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Commission %" required>
                        <InputNumber
                            min={0}
                            max={100}
                            style={{ width: '100%' }}
                            value={form.data.commission_percent}
                            onChange={(v) => form.setData('commission_percent', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Commission Basis" required>
                        <Select
                            value={form.data.commission_basis}
                            onChange={(v) => form.setData('commission_basis', v)}
                            options={commissionBases.map((b) => ({ value: b.value, label: b.label }))}
                        />
                    </Form.Item>
                    <Form.Item label="Payment Terms (days)" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.payment_terms_days}
                            onChange={(v) => form.setData('payment_terms_days', v ?? 30)}
                        />
                    </Form.Item>
                    <Form.Item label="Company (City Ledger)">
                        <Select
                            allowClear
                            value={form.data.company_id}
                            onChange={(v) => form.setData('company_id', v)}
                            options={companies.map((c) => ({ value: c.id, label: c.name }))}
                        />
                    </Form.Item>
                    <Form.Item label="Portal User">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            value={form.data.user_id}
                            onChange={(v) => form.setData('user_id', v)}
                            options={users.map((u) => ({
                                value: u.id,
                                label: `${u.name} (${u.email})`,
                            }))}
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
