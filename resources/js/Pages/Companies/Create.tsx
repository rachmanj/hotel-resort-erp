import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Form, Input, InputNumber, Switch } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function CompaniesCreate() {
    const form = useForm({
        name: '',
        tax_id: '',
        billing_address: '',
        phone: '',
        email: '',
        credit_limit: 0,
        payment_terms_days: 30,
        is_active: true,
    });

    const submit = () => {
        form.post('/companies');
    };

    return (
        <AuthenticatedLayout title="New Company">
            <Head title="New Company" />
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
                    Create Company
                </Button>
            </Form>
        </AuthenticatedLayout>
    );
}
