import { Head, Link, useForm } from '@inertiajs/react';
import { ProForm, ProFormSwitch, ProFormText, ProFormTimePicker } from '@ant-design/pro-form';
import { Card } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function HotelsCreate() {
    const form = useForm({
        code: '',
        name: '',
        address: '',
        currency: 'IDR',
        timezone: 'Asia/Makassar',
        default_checkin_time: '14:00',
        default_checkout_time: '12:00',
        phone: '',
        email: '',
        is_active: true,
    });

    return (
        <AuthenticatedLayout title="New Hotel">
            <Head title="New Hotel" />
            <Link href="/admin/hotels" style={{ display: 'inline-block', marginBottom: 16 }}>
                &larr; Back to Hotels
            </Link>
            <Card>
                <ProForm
                    initialValues={form.data}
                    onFinish={async (values) => {
                        form.setData(values as typeof form.data);
                        form.post('/admin/hotels');
                    }}
                    submitter={{ searchConfig: { submitText: 'Create Hotel' } }}
                >
                    <ProFormText name="code" label="Code" rules={[{ required: true }]} />
                    <ProFormText name="name" label="Name" rules={[{ required: true }]} />
                    <ProFormText name="address" label="Address" />
                    <ProFormText name="currency" label="Currency" rules={[{ required: true }]} />
                    <ProFormText name="timezone" label="Timezone" rules={[{ required: true }]} />
                    <ProFormTimePicker
                        name="default_checkin_time"
                        label="Default Check-in"
                        fieldProps={{ format: 'HH:mm' }}
                        transform={(value) => ({
                            default_checkin_time: dayjs.isDayjs(value)
                                ? value.format('HH:mm')
                                : value,
                        })}
                    />
                    <ProFormTimePicker
                        name="default_checkout_time"
                        label="Default Check-out"
                        fieldProps={{ format: 'HH:mm' }}
                        transform={(value) => ({
                            default_checkout_time: dayjs.isDayjs(value)
                                ? value.format('HH:mm')
                                : value,
                        })}
                    />
                    <ProFormText name="phone" label="Phone" />
                    <ProFormText name="email" label="Email" />
                    <ProFormSwitch name="is_active" label="Active" />
                </ProForm>
            </Card>
        </AuthenticatedLayout>
    );
}
