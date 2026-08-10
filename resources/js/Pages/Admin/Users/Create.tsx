import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Card, Form, Input, Select } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface UsersCreateProps {
    hotels: Array<{ id: number; name: string; code: string }>;
    roles: Array<{ id: number; name: string }>;
}

export default function UsersCreate({ hotels, roles }: UsersCreateProps) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        hotel_id: null as number | null,
        roles: [] as string[],
    });

    return (
        <AuthenticatedLayout title="New User">
            <Head title="New User" />
            <Link href="/admin/users" style={{ display: 'inline-block', marginBottom: 16 }}>
                &larr; Back to Users
            </Link>
            <Card>
                <Form layout="vertical" onFinish={() => form.post('/admin/users')}>
                    <Form.Item label="Name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Email" required>
                        <Input
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Password" required>
                        <Input.Password
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Hotel">
                        <Select
                            allowClear
                            placeholder="Select hotel"
                            value={form.data.hotel_id}
                            onChange={(v) => form.setData('hotel_id', v ?? null)}
                            options={hotels.map((h) => ({
                                value: h.id,
                                label: `${h.name} (${h.code})`,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item label="Roles">
                        <Select
                            mode="multiple"
                            placeholder="Select roles"
                            value={form.data.roles}
                            onChange={(v) => form.setData('roles', v)}
                            options={roles.map((r) => ({ value: r.name, label: r.name }))}
                        />
                    </Form.Item>
                    <Button type="primary" htmlType="submit" loading={form.processing}>
                        Create User
                    </Button>
                </Form>
            </Card>
        </AuthenticatedLayout>
    );
}
