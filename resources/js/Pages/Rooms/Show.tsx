import { Head, Link } from '@inertiajs/react';
import { Card, Descriptions, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface RoomShowProps {
    room: {
        id: number;
        number: string;
        status: string;
        status_label: string;
        status_color: string;
        notes?: string | null;
        room_type?: {
            id: number;
            name: string;
            code: string;
            base_rate: string;
            max_occupancy: number;
        };
        floor?: { id: number; name: string; level: number };
        hotel?: { id: number; name: string; code: string };
    };
}

export default function RoomShow({ room }: RoomShowProps) {
    return (
        <AuthenticatedLayout title={`Room ${room.number}`}>
            <Head title={`Room ${room.number}`} />
            <Link href="/rooms" style={{ display: 'inline-block', marginBottom: 16 }}>
                &larr; Back to Rooms
            </Link>
            <Card>
                <Descriptions bordered column={1}>
                    <Descriptions.Item label="Room Number">{room.number}</Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag color={room.status_color}>{room.status_label}</Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="Room Type">
                        {room.room_type?.name} ({room.room_type?.code})
                    </Descriptions.Item>
                    <Descriptions.Item label="Floor">
                        {room.floor?.name} (Level {room.floor?.level})
                    </Descriptions.Item>
                    <Descriptions.Item label="Base Rate">
                        Rp {Number(room.room_type?.base_rate ?? 0).toLocaleString('id-ID')}
                    </Descriptions.Item>
                    <Descriptions.Item label="Max Occupancy">
                        {room.room_type?.max_occupancy}
                    </Descriptions.Item>
                    <Descriptions.Item label="Notes">{room.notes || '-'}</Descriptions.Item>
                </Descriptions>
            </Card>
        </AuthenticatedLayout>
    );
}
