import { Table, Tag } from 'antd';

interface AvailabilityRow {
    room_type_id: number;
    name: string;
    code: string;
    available_count: number;
    total_count: number;
}

interface AvailabilityGridProps {
    availability: AvailabilityRow[];
    selectedRoomTypeId?: number | null;
    onSelect?: (roomTypeId: number) => void;
}

export default function AvailabilityGrid({
    availability,
    selectedRoomTypeId,
    onSelect,
}: AvailabilityGridProps) {
    return (
        <Table
            size="small"
            rowKey="room_type_id"
            dataSource={availability}
            pagination={false}
            onRow={(record) => ({
                onClick: () => onSelect?.(record.room_type_id),
                style: {
                    cursor: 'pointer',
                    background:
                        selectedRoomTypeId === record.room_type_id ? '#e6f4ff' : undefined,
                },
            })}
            columns={[
                { title: 'Room Type', dataIndex: 'name' },
                { title: 'Code', dataIndex: 'code' },
                {
                    title: 'Available',
                    dataIndex: 'available_count',
                    render: (count: number, record) => (
                        <Tag color={count > 0 ? 'green' : 'red'}>
                            {count} / {record.total_count}
                        </Tag>
                    ),
                },
            ]}
        />
    );
}
