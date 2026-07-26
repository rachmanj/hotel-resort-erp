import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Form, Input, Select, Space, Switch } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface GuestsEditProps {
    guest: {
        id: number;
        full_name: string;
        id_number?: string;
        id_type?: string;
        phone?: string;
        email?: string;
        address?: string;
        nationality?: string;
        vip_tier: string;
        is_blacklisted: boolean;
        blacklist_reason?: string;
    };
    idTypes: Array<{ value: string; label: string }>;
    vipTiers: Array<{ value: string; label: string }>;
}

export default function GuestsEdit({ guest, idTypes, vipTiers }: GuestsEditProps) {
    const form = useForm({
        full_name: guest.full_name,
        id_number: guest.id_number ?? '',
        id_type: guest.id_type ?? null,
        phone: guest.phone ?? '',
        email: guest.email ?? '',
        address: guest.address ?? '',
        nationality: guest.nationality ?? '',
        vip_tier: guest.vip_tier,
        is_blacklisted: guest.is_blacklisted,
        blacklist_reason: guest.blacklist_reason ?? '',
    });

    const submit = () => {
        form.put(`/guests/${guest.id}`);
    };

    return (
        <AuthenticatedLayout title={`Edit ${guest.full_name}`}>
            <Head title={`Edit ${guest.full_name}`} />
            <Link href={`/guests/${guest.id}`}>
                <Button style={{ marginBottom: 16 }}>Back</Button>
            </Link>
            <Form layout="vertical" onFinish={submit} style={{ maxWidth: 600 }}>
                <Form.Item label="Full Name" required>
                    <Input value={form.data.full_name} onChange={(e) => form.setData('full_name', e.target.value)} />
                </Form.Item>
                <Space style={{ width: '100%' }} size="large">
                    <Form.Item label="ID Type" style={{ flex: 1 }}>
                        <Select
                            allowClear
                            value={form.data.id_type}
                            onChange={(v) => form.setData('id_type', v)}
                            options={idTypes}
                        />
                    </Form.Item>
                    <Form.Item label="ID Number" style={{ flex: 1 }}>
                        <Input value={form.data.id_number} onChange={(e) => form.setData('id_number', e.target.value)} />
                    </Form.Item>
                </Space>
                <Form.Item label="Phone">
                    <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </Form.Item>
                <Form.Item label="Email">
                    <Input value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                </Form.Item>
                <Form.Item label="Address">
                    <Input.TextArea value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                </Form.Item>
                <Form.Item label="Nationality">
                    <Input value={form.data.nationality} onChange={(e) => form.setData('nationality', e.target.value)} />
                </Form.Item>
                <Form.Item label="VIP Tier">
                    <Select value={form.data.vip_tier} onChange={(v) => form.setData('vip_tier', v)} options={vipTiers} />
                </Form.Item>
                <Form.Item label="Blacklisted">
                    <Switch checked={form.data.is_blacklisted} onChange={(v) => form.setData('is_blacklisted', v)} />
                </Form.Item>
                {form.data.is_blacklisted && (
                    <Form.Item label="Blacklist Reason">
                        <Input.TextArea
                            value={form.data.blacklist_reason}
                            onChange={(e) => form.setData('blacklist_reason', e.target.value)}
                        />
                    </Form.Item>
                )}
                <Button type="primary" htmlType="submit" loading={form.processing}>
                    Save Changes
                </Button>
            </Form>
        </AuthenticatedLayout>
    );
}
