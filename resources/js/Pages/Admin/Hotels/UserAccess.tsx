import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Card, Select } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface UserOption {
    id: number;
    name: string;
    email: string;
}

interface UserAccessProps {
    hotel: { id: number; name: string; code: string };
    assignedUserIds: number[];
    users: UserOption[];
}

export default function UserAccess({ hotel, assignedUserIds, users }: UserAccessProps) {
    const form = useForm({
        user_ids: assignedUserIds,
    });

    return (
        <AuthenticatedLayout title={`User Access · ${hotel.name}`}>
            <Head title={`User Access · ${hotel.name}`} />
            <Link href="/admin/hotels" style={{ display: 'inline-block', marginBottom: 16 }}>
                &larr; Back to Hotels
            </Link>
            <Card title={`Manage access for ${hotel.name} (${hotel.code})`}>
                <Select
                    mode="multiple"
                    style={{ width: '100%', marginBottom: 16 }}
                    placeholder="Select users with access"
                    value={form.data.user_ids}
                    onChange={(value) => form.setData('user_ids', value)}
                    options={users.map((user) => ({
                        value: user.id,
                        label: `${user.name} (${user.email})`,
                    }))}
                />
                <Button
                    type="primary"
                    loading={form.processing}
                    onClick={() => form.post(`/admin/hotels/${hotel.id}/users`)}
                >
                    Save Access
                </Button>
            </Card>
        </AuthenticatedLayout>
    );
}
