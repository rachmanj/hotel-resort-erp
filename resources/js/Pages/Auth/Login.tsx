import { Head, useForm } from '@inertiajs/react';
import { LockOutlined, UserOutlined } from '@ant-design/icons';
import { Button, Card, Checkbox, Form, Input, Typography } from 'antd';
import resortOne from '@/assets/login/resort-1.jpg';
import resortTwo from '@/assets/login/resort-2.jpg';
import resortThree from '@/assets/login/resort-3.jpg';
import resortFour from '@/assets/login/resort-4.jpg';
import GuestLayout from '@/Layouts/GuestLayout';

const resortSlides = [
    { src: resortOne, alt: 'Pratasaba Resort over the water' },
    { src: resortTwo, alt: 'Pratasaba Resort room interior', scale: 0.8 },
    { src: resortThree, alt: 'Pratasaba Resort beach' },
    { src: resortFour, alt: 'Pratasaba Resort over-water jetty' },
];

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
        <GuestLayout slides={resortSlides}>
            <Head title="Login" />
            <Card>
                <Typography.Title level={3} style={{ textAlign: 'center', marginBottom: 24 }}>
                    Pratasaba ERP
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
