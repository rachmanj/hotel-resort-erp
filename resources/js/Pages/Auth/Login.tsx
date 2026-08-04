import { Head, useForm } from '@inertiajs/react';
import { LockOutlined, UserOutlined } from '@ant-design/icons';
import { Button, Card, Checkbox, Form, Input, Typography } from 'antd';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = () => {
        post('/login');
    };

    return (
        <GuestLayout>
            <Head title="Login" />
            <Card>
                <Typography.Title level={3} style={{ textAlign: 'center', marginBottom: 24 }}>
                    Pratasaba Resort
                </Typography.Title>
                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label="Email"
                        validateStatus={errors.email ? 'error' : ''}
                        help={errors.email}
                    >
                        <Input
                            prefix={<UserOutlined />}
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="username"
                        />
                    </Form.Item>
                    <Form.Item
                        label="Password"
                        validateStatus={errors.password ? 'error' : ''}
                        help={errors.password}
                    >
                        <Input.Password
                            prefix={<LockOutlined />}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
                        />
                    </Form.Item>
                    <Form.Item>
                        <Checkbox
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                        >
                            Remember me
                        </Checkbox>
                    </Form.Item>
                    <Button type="primary" htmlType="submit" block loading={processing}>
                        Sign In
                    </Button>
                </Form>
            </Card>
        </GuestLayout>
    );
}
