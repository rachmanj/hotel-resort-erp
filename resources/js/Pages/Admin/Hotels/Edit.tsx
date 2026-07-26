import { Head, Link, useForm } from '@inertiajs/react';
import { ProForm, ProFormSwitch, ProFormText, ProFormTimePicker } from '@ant-design/pro-form';
import { Card } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Hotel {
    id: number;
    code: string;
    name: string;
    address?: string | null;
    currency: string;
    timezone: string;
    default_checkin_time: string;
    default_checkout_time: string;
    phone?: string | null;
    email?: string | null;
    is_active: boolean;
}

interface HotelsEditProps {
    hotel: Hotel;
}

export default function HotelsEdit({ hotel }: HotelsEditProps) {
    const form = useForm({
        code: hotel.code,
        name: hotel.name,
        address: hotel.address ?? '',
        currency: hotel.currency,
        timezone: hotel.timezone,
        default_checkin_time: hotel.default_checkin_time?.substring(0, 5) ?? '14:00',
        default_checkout_time: hotel.default_checkout_time?.substring(0, 5) ?? '12:00',
        phone: hotel.phone ?? '',
        email: hotel.email ?? '',
        is_active: hotel.is_active,
    });

    return (
        <AuthenticatedLayout title={`Edit ${hotel.name}`}>
            <Head title={`Edit ${hotel.name}`} />
            <Link href="/admin/hotels" style={{ display: 'inline-block', marginBottom: 16 }}>
                &larr; Back to Hotels
            </Link>
            <Card>
                <ProForm
                    initialValues={{
                        ...form.data,
                        default_checkin_time: dayjs(form.data.default_checkin_time, 'HH:mm'),
                        default_checkout_time: dayjs(form.data.default_checkout_time, 'HH:mm'),
                    }}
                    onFinish={async (values) => {
                        const payload = {
                            ...values,
                            default_checkin_time: dayjs.isDayjs(values.default_checkin_time)
                                ? values.default_checkin_time.format('HH:mm')
                                : values.default_checkin_time,
                            default_checkout_time: dayjs.isDayjs(values.default_checkout_time)
                                ? values.default_checkout_time.format('HH:mm')
                                : values.default_checkout_time,
                        };
                        form.setData(payload as typeof form.data);
                        form.put(`/admin/hotels/${hotel.id}`);
                    }}
                    submitter={{ searchConfig: { submitText: 'Save Changes' } }}
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
                    />
                    <ProFormTimePicker
                        name="default_checkout_time"
                        label="Default Check-out"
                        fieldProps={{ format: 'HH:mm' }}
                    />
                    <ProFormText name="phone" label="Phone" />
                    <ProFormText name="email" label="Email" />
                    <ProFormSwitch name="is_active" label="Active" />
                </ProForm>
            </Card>
        </AuthenticatedLayout>
    );
}
