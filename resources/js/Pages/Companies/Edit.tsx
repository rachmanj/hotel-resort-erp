import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Form, Input, InputNumber, Switch } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface CompaniesEditProps {
    company: {
        id: number;
        name: string;
        tax_id?: string;
        billing_address?: string;
        phone?: string;
        email?: string;
        credit_limit: string | number;
        payment_terms_days: number;
        is_active: boolean;
    };
}

export default function CompaniesEdit({ company }: CompaniesEditProps) {
    const form = useForm({
        name: company.name,
        tax_id: company.tax_id ?? '',
        billing_address: company.billing_address ?? '',
        phone: company.phone ?? '',
        email: company.email ?? '',
        credit_limit: Number(company.credit_limit),
        payment_terms_days: company.payment_terms_days,
        is_active: company.is_active,
    });

    const submit = () => {
        form.put(`/companies/${company.id}`);
    };

    return (
        <AuthenticatedLayout title={company.name}>
            <Head title={company.name} />
            <Link href="/companies">
                <Button style={{ marginBottom: 16 }}>Back</Button>
            </Link>
            <Form layout="vertical" onFinish={submit} style={{ maxWidth: 600 }}>
                <Form.Item label="Company Name" required>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </Form.Item>
                <Form.Item label="NPWP (Tax ID)">
                    <Input value={form.data.tax_id} onChange={(e) => form.setData('tax_id', e.target.value)} />
                </Form.Item>
                <Form.Item label="Billing Address">
                    <Input.TextArea
                        value={form.data.billing_address}
                        onChange={(e) => form.setData('billing_address', e.target.value)}
                    />
                </Form.Item>
                <Form.Item label="Phone">
                    <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </Form.Item>
                <Form.Item label="Email">
                    <Input value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                </Form.Item>
                <Form.Item label="Credit Limit">
                    <InputNumber
                        min={0}
                        style={{ width: '100%' }}
                        value={form.data.credit_limit}
                        onChange={(v) => form.setData('credit_limit', v ?? 0)}
                    />
                </Form.Item>
                <Form.Item label="Payment Terms (days)">
                    <InputNumber
                        min={0}
                        style={{ width: '100%' }}
                        value={form.data.payment_terms_days}
                        onChange={(v) => form.setData('payment_terms_days', v ?? 30)}
                    />
                </Form.Item>
                <Form.Item label="Active">
                    <Switch checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} />
                </Form.Item>
                <Button type="primary" htmlType="submit" loading={form.processing}>
                    Save Changes
                </Button>
            </Form>
        </AuthenticatedLayout>
    );
}
