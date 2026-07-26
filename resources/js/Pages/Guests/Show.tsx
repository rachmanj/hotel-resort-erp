import { Head, Link } from '@inertiajs/react';
import { Button, Descriptions, Table, Tabs, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface GuestShowProps {
    guest: {
        id: number;
        full_name: string;
        id_number?: string;
        id_type_label?: string;
        phone?: string;
        email?: string;
        address?: string;
        nationality?: string;
        vip_tier: string;
        vip_tier_label: string;
        is_blacklisted: boolean;
        blacklist_reason?: string;
        preferences: Array<{ id: number; key: string; value: string; notes?: string }>;
        stays: Array<{
            id: number;
            room_number?: string;
            reservation_code?: string;
            check_in_at?: string;
            check_out_at?: string;
            nights: number;
            total_spend: string;
        }>;
        incidents: Array<{
            id: number;
            type_label: string;
            description: string;
            occurred_at?: string;
            reported_by?: { name: string };
        }>;
    };
    canEdit: boolean;
}

const formatIdr = (v: string | number) => `Rp ${Number(v).toLocaleString('id-ID')}`;

export default function GuestShow({ guest, canEdit }: GuestShowProps) {
    const tabItems = [
        {
            key: 'info',
            label: 'Info',
            children: (
                <Descriptions bordered column={2} size="small">
                    <Descriptions.Item label="Name">{guest.full_name}</Descriptions.Item>
                    <Descriptions.Item label="VIP">
                        {guest.vip_tier !== 'none' ? (
                            <Tag color="gold">{guest.vip_tier_label}</Tag>
                        ) : (
                            '—'
                        )}
                    </Descriptions.Item>
                    <Descriptions.Item label="Phone">{guest.phone ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Email">{guest.email ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="ID Type">{guest.id_type_label ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="ID Number">{guest.id_number ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Nationality">{guest.nationality ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Status">
                        {guest.is_blacklisted ? (
                            <Tag color="red">Blacklisted</Tag>
                        ) : (
                            <Tag color="green">Active</Tag>
                        )}
                    </Descriptions.Item>
                    {guest.is_blacklisted && (
                        <Descriptions.Item label="Blacklist Reason" span={2}>
                            {guest.blacklist_reason}
                        </Descriptions.Item>
                    )}
                    <Descriptions.Item label="Address" span={2}>{guest.address ?? '—'}</Descriptions.Item>
                </Descriptions>
            ),
        },
        {
            key: 'stays',
            label: 'Stay History',
            children: (
                <Table
                    rowKey="id"
                    size="small"
                    pagination={false}
                    dataSource={guest.stays}
                    columns={[
                        { title: 'Reservation', dataIndex: 'reservation_code' },
                        { title: 'Room', dataIndex: 'room_number' },
                        { title: 'Check In', dataIndex: 'check_in_at' },
                        { title: 'Check Out', dataIndex: 'check_out_at' },
                        { title: 'Nights', dataIndex: 'nights' },
                        { title: 'Total Spend', dataIndex: 'total_spend', render: formatIdr },
                    ]}
                />
            ),
        },
        {
            key: 'preferences',
            label: 'Preferences',
            children: (
                <Table
                    rowKey="id"
                    size="small"
                    pagination={false}
                    dataSource={guest.preferences}
                    columns={[
                        { title: 'Key', dataIndex: 'key' },
                        { title: 'Value', dataIndex: 'value' },
                        { title: 'Notes', dataIndex: 'notes', render: (v) => v ?? '—' },
                    ]}
                />
            ),
        },
        {
            key: 'incidents',
            label: 'Incidents',
            children: (
                <Table
                    rowKey="id"
                    size="small"
                    pagination={false}
                    dataSource={guest.incidents}
                    columns={[
                        { title: 'Type', dataIndex: 'type_label' },
                        { title: 'Description', dataIndex: 'description' },
                        { title: 'Occurred', dataIndex: 'occurred_at' },
                        { title: 'Reported By', dataIndex: ['reported_by', 'name'] },
                    ]}
                />
            ),
        },
    ];

    return (
        <AuthenticatedLayout title={guest.full_name}>
            <Head title={guest.full_name} />
            <div style={{ marginBottom: 16 }}>
                <Link href="/guests">
                    <Button>Back to list</Button>
                </Link>
                {canEdit && (
                    <Link href={`/guests/${guest.id}/edit`} style={{ marginLeft: 8 }}>
                        <Button type="primary">Edit</Button>
                    </Link>
                )}
            </div>
            <Tabs items={tabItems} />
        </AuthenticatedLayout>
    );
}
