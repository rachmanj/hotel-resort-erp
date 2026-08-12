import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import {
    Button,
    DatePicker,
    Form,
    Input,
    InputNumber,
    Modal,
    Select,
    Space,
    Switch,
    Table,
    Tag,
    message,
} from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface PromotionRow {
    id: number;
    name: string;
    promo_type: string;
    promo_type_label: string;
    discount_summary: string;
    valid_from: string;
    valid_to: string;
    used_count: number;
    max_uses?: number | null;
    is_active: boolean;
    requires_code: boolean;
    codes_count: number;
}

interface PromoCodeRow {
    id: number;
    code: string;
    max_uses?: number | null;
    used_count: number;
    is_active: boolean;
    expires_at?: string | null;
}

interface PromotionsIndexProps {
    promotions: Paginated<PromotionRow>;
    roomTypes: Array<{ id: number; name: string; code: string }>;
    companies: Array<{ id: number; name: string }>;
    ratePlans: Array<{ id: number; name: string; nightly_rate: string }>;
    promoTypes: Array<{ value: string; label: string }>;
    discountTypes: Array<{ value: string; label: string }>;
    menuItems: Array<{ id: number; name: string; price: string }>;
    spaTreatments: Array<{ id: number; name: string; price: string }>;
    filters: { search?: string };
}

const defaultForm = {
    name: '',
    promo_type: 'seasonal',
    discount_type: 'percent',
    discount_value: 0,
    rate_plan_id: null as number | null,
    company_id: null as number | null,
    lead_time_min_days: null as number | null,
    lead_time_max_days: null as number | null,
    min_nights: null as number | null,
    max_nights: null as number | null,
    valid_from: dayjs().format('YYYY-MM-DD'),
    valid_to: dayjs().add(30, 'day').format('YYYY-MM-DD'),
    is_stackable: false,
    requires_code: false,
    max_uses: null as number | null,
    is_active: true,
    room_type_ids: [] as number[],
};

export default function PromotionsIndex({
    promotions,
    roomTypes,
    companies,
    ratePlans,
    promoTypes,
    discountTypes,
    filters,
}: PromotionsIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [codesModalOpen, setCodesModalOpen] = useState(false);
    const [editing, setEditing] = useState<PromotionRow | null>(null);
    const [codesPromotion, setCodesPromotion] = useState<PromotionRow | null>(null);
    const [codes, setCodes] = useState<PromoCodeRow[]>([]);
    const [loadingCodes, setLoadingCodes] = useState(false);

    const form = useForm({ ...defaultForm });
    const codeForm = useForm({
        code: '',
        max_uses: null as number | null,
        expires_at: null as string | null,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({ ...defaultForm });
        setModalOpen(true);
    };

    const openEdit = async (record: PromotionRow) => {
        setEditing(record);
        const res = await fetch(`/admin/promotions/${record.id}`);
        const data = await res.json();
        const p = data.promotion;
        form.setData({
            name: p.name,
            promo_type: p.promo_type,
            discount_type: p.discount_type,
            discount_value: p.discount_value,
            rate_plan_id: p.rate_plan_id,
            company_id: p.company_id,
            lead_time_min_days: p.lead_time_min_days,
            lead_time_max_days: p.lead_time_max_days,
            min_nights: p.min_nights,
            max_nights: p.max_nights,
            valid_from: p.valid_from,
            valid_to: p.valid_to,
            is_stackable: p.is_stackable,
            requires_code: p.requires_code,
            max_uses: p.max_uses,
            is_active: p.is_active,
            room_type_ids: p.room_type_ids ?? [],
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/promotions/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/promotions', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const openCodes = async (record: PromotionRow) => {
        setCodesPromotion(record);
        setCodesModalOpen(true);
        setLoadingCodes(true);
        try {
            const res = await fetch(`/admin/promotions/${record.id}/codes`);
            const data = await res.json();
            setCodes(data.codes ?? []);
        } finally {
            setLoadingCodes(false);
        }
    };

    const generateCode = () => {
        if (!codesPromotion) return;
        codeForm.post(`/admin/promotions/${codesPromotion.id}/codes`, {
            preserveScroll: true,
            onSuccess: () => {
                codeForm.reset();
                openCodes(codesPromotion);
            },
        });
    };

    const copyCode = (code: string) => {
        navigator.clipboard.writeText(code);
        message.success('Code copied to clipboard');
    };

    const deletePromotion = (record: PromotionRow) => {
        Modal.confirm({
            title: 'Delete promotion?',
            content: `Delete "${record.name}"? This cannot be undone.`,
            onOk: () => router.delete(`/admin/promotions/${record.id}`),
        });
    };

    const deleteCode = (code: PromoCodeRow) => {
        Modal.confirm({
            title: 'Delete code?',
            onOk: () =>
                router.delete(`/admin/promotions/codes/${code.id}`, {
                    onSuccess: () => codesPromotion && openCodes(codesPromotion),
                }),
        });
    };

    const columns: ProColumns<PromotionRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        {
            title: 'Type',
            dataIndex: 'promo_type_label',
            render: (_, r) => <Tag>{r.promo_type_label}</Tag>,
        },
        { title: 'Discount', dataIndex: 'discount_summary' },
        {
            title: 'Valid',
            render: (_, r) => `${r.valid_from} → ${r.valid_to}`,
        },
        {
            title: 'Used',
            render: (_, r) =>
                r.max_uses ? `${r.used_count} / ${r.max_uses}` : String(r.used_count),
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
                <Button key="codes" type="link" onClick={() => openCodes(record)}>
                    Codes ({record.codes_count})
                </Button>,
                <Button key="delete" type="link" danger onClick={() => deletePromotion(record)}>
                    Delete
                </Button>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Promotions">
            <Head title="Promotions" />
            <ProTable<PromotionRow>
                rowKey="id"
                columns={columns}
                dataSource={promotions.data}
                search={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Promotion
                    </Button>,
                ]}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: promotions.current_page,
                    pageSize: promotions.per_page,
                    total: promotions.total,
                    onChange: (page) =>
                        router.get('/admin/promotions', { ...filters, page }, { preserveState: true }),
                }}
            />

            <Modal
                title={editing ? 'Edit Promotion' : 'New Promotion'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
                width={720}
            >
                <Form layout="vertical">
                    <Form.Item label="Name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Space style={{ width: '100%' }} size="large">
                        <Form.Item label="Promo type" required style={{ flex: 1 }}>
                            <Select
                                value={form.data.promo_type}
                                onChange={(v) => form.setData('promo_type', v)}
                                options={promoTypes}
                                style={{ width: 200 }}
                            />
                        </Form.Item>
                        <Form.Item label="Discount type" required style={{ flex: 1 }}>
                            <Select
                                value={form.data.discount_type}
                                onChange={(v) => form.setData('discount_type', v)}
                                options={discountTypes}
                                style={{ width: 200 }}
                            />
                        </Form.Item>
                        <Form.Item label="Discount value" required>
                            <InputNumber
                                min={0}
                                value={form.data.discount_value}
                                onChange={(v) => form.setData('discount_value', v ?? 0)}
                            />
                        </Form.Item>
                    </Space>
                    <Space style={{ width: '100%' }} size="large">
                        <Form.Item label="Valid from" required>
                            <DatePicker
                                value={dayjs(form.data.valid_from)}
                                onChange={(d) =>
                                    form.setData('valid_from', d?.format('YYYY-MM-DD') ?? '')
                                }
                            />
                        </Form.Item>
                        <Form.Item label="Valid to" required>
                            <DatePicker
                                value={dayjs(form.data.valid_to)}
                                onChange={(d) =>
                                    form.setData('valid_to', d?.format('YYYY-MM-DD') ?? '')
                                }
                            />
                        </Form.Item>
                    </Space>
                    {form.data.promo_type === 'corporate' && (
                        <Form.Item label="Company">
                            <Select
                                allowClear
                                value={form.data.company_id}
                                onChange={(v) => form.setData('company_id', v)}
                                options={companies.map((c) => ({ value: c.id, label: c.name }))}
                                style={{ width: '100%' }}
                            />
                        </Form.Item>
                    )}
                    {(form.data.promo_type === 'early_bird' ||
                        form.data.promo_type === 'last_minute') && (
                        <Space>
                            {form.data.promo_type === 'early_bird' && (
                                <Form.Item label="Min lead days">
                                    <InputNumber
                                        min={0}
                                        value={form.data.lead_time_min_days}
                                        onChange={(v) => form.setData('lead_time_min_days', v)}
                                    />
                                </Form.Item>
                            )}
                            {form.data.promo_type === 'last_minute' && (
                                <Form.Item label="Max lead days">
                                    <InputNumber
                                        min={0}
                                        value={form.data.lead_time_max_days}
                                        onChange={(v) => form.setData('lead_time_max_days', v)}
                                    />
                                </Form.Item>
                            )}
                        </Space>
                    )}
                    <Space>
                        <Form.Item label="Min nights">
                            <InputNumber
                                min={1}
                                value={form.data.min_nights}
                                onChange={(v) => form.setData('min_nights', v)}
                            />
                        </Form.Item>
                        <Form.Item label="Max nights">
                            <InputNumber
                                min={1}
                                value={form.data.max_nights}
                                onChange={(v) => form.setData('max_nights', v)}
                            />
                        </Form.Item>
                        <Form.Item label="Max uses">
                            <InputNumber
                                min={1}
                                value={form.data.max_uses}
                                onChange={(v) => form.setData('max_uses', v)}
                            />
                        </Form.Item>
                    </Space>
                    <Form.Item label="Rate plan (optional)">
                        <Select
                            allowClear
                            value={form.data.rate_plan_id}
                            onChange={(v) => form.setData('rate_plan_id', v)}
                            options={ratePlans.map((rp) => ({
                                value: rp.id,
                                label: `${rp.name} (Rp ${Number(rp.nightly_rate).toLocaleString('id-ID')})`,
                            }))}
                            style={{ width: '100%' }}
                        />
                    </Form.Item>
                    <Form.Item label="Room types (empty = all)">
                        <Select
                            mode="multiple"
                            allowClear
                            value={form.data.room_type_ids}
                            onChange={(v) => form.setData('room_type_ids', v)}
                            options={roomTypes.map((rt) => ({
                                value: rt.id,
                                label: `${rt.name} (${rt.code})`,
                            }))}
                            style={{ width: '100%' }}
                        />
                    </Form.Item>
                    <Space>
                        <Form.Item label="Stackable">
                            <Switch
                                checked={form.data.is_stackable}
                                onChange={(c) => form.setData('is_stackable', c)}
                            />
                        </Form.Item>
                        <Form.Item label="Requires code">
                            <Switch
                                checked={form.data.requires_code}
                                onChange={(c) => form.setData('requires_code', c)}
                            />
                        </Form.Item>
                        <Form.Item label="Active">
                            <Switch
                                checked={form.data.is_active}
                                onChange={(c) => form.setData('is_active', c)}
                            />
                        </Form.Item>
                    </Space>
                </Form>
            </Modal>

            <Modal
                title={`Promo Codes — ${codesPromotion?.name ?? ''}`}
                open={codesModalOpen}
                onCancel={() => setCodesModalOpen(false)}
                footer={null}
                width={640}
            >
                <Form layout="inline" style={{ marginBottom: 16 }}>
                    <Form.Item label="Custom code">
                        <Input
                            placeholder="Auto-generate if empty"
                            value={codeForm.data.code}
                            onChange={(e) => codeForm.setData('code', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Max uses">
                        <InputNumber
                            min={1}
                            value={codeForm.data.max_uses}
                            onChange={(v) => codeForm.setData('max_uses', v)}
                        />
                    </Form.Item>
                    <Button type="primary" onClick={generateCode} loading={codeForm.processing}>
                        Generate
                    </Button>
                </Form>
                <Table<PromoCodeRow>
                    rowKey="id"
                    size="small"
                    loading={loadingCodes}
                    dataSource={codes}
                    pagination={false}
                    columns={[
                        {
                            title: 'Code',
                            dataIndex: 'code',
                            render: (code: string) => (
                                <Button type="link" onClick={() => copyCode(code)}>
                                    {code}
                                </Button>
                            ),
                        },
                        {
                            title: 'Used',
                            render: (_, r) =>
                                r.max_uses ? `${r.used_count}/${r.max_uses}` : r.used_count,
                        },
                        {
                            title: 'Active',
                            dataIndex: 'is_active',
                            render: (v) => (v ? 'Yes' : 'No'),
                        },
                        {
                            title: 'Actions',
                            render: (_, r) => (
                                <Button type="link" danger onClick={() => deleteCode(r)}>
                                    Delete
                                </Button>
                            ),
                        },
                    ]}
                />
            </Modal>
        </AuthenticatedLayout>
    );
}
