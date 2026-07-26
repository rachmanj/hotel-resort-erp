import { Head, useForm } from '@inertiajs/react';
import { ProForm, ProFormText, ProFormTimePicker, ProFormUploadButton } from '@ant-design/pro-form';
import { Card } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Hotel {
    id: number;
    name: string;
    address?: string | null;
    logo_path?: string | null;
    currency: string;
    timezone: string;
    default_checkin_time: string;
    default_checkout_time: string;
}

interface HotelSettingsEditProps {
    hotel: Hotel;
}

export default function HotelSettingsEdit({ hotel }: HotelSettingsEditProps) {
    const form = useForm({
        name: hotel.name,
        address: hotel.address ?? '',
        currency: hotel.currency,
        timezone: hotel.timezone,
        default_checkin_time: hotel.default_checkin_time?.substring(0, 5) ?? '14:00',
        default_checkout_time: hotel.default_checkout_time?.substring(0, 5) ?? '12:00',
        logo: null as File | null,
    });

    return (
        <AuthenticatedLayout title="Hotel Settings">
            <Head title="Hotel Settings" />
            <Card>
                <ProForm
                    initialValues={{
                        name: form.data.name,
                        address: form.data.address,
                        currency: form.data.currency,
                        timezone: form.data.timezone,
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
                            _method: 'put',
                        };
                        form.transform(() => payload);
                        form.post('/admin/hotel-settings', { forceFormData: true });
                    }}
                    submitter={{ searchConfig: { submitText: 'Save Settings' } }}
                >
                    <ProFormText name="name" label="Hotel Name" rules={[{ required: true }]} />
                    <ProFormText name="address" label="Address" />
                    <ProFormUploadButton
                        name="logo"
                        label="Logo"
                        max={1}
                        fieldProps={{
                            beforeUpload: () => false,
                            accept: 'image/*',
                        }}
                    />
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
                </ProForm>
            </Card>
        </AuthenticatedLayout>
    );
}
