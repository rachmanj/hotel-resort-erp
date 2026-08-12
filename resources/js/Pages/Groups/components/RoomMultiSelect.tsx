import { MinusOutlined, PlusOutlined } from '@ant-design/icons';
import { Button, InputNumber, Space, Table, Tag } from 'antd';

interface AvailabilityRow {
    room_type_id: number;
    name: string;
    code: string;
    available_count: number;
    total_count: number;
}

export interface RoomSelection {
    room_type_id: number;
    quantity: number;
}

interface RoomMultiSelectProps {
    availability: AvailabilityRow[];
    selections: RoomSelection[];
    onChange: (selections: RoomSelection[]) => void;
}

export default function RoomMultiSelect({ availability, selections, onChange }: RoomMultiSelectProps) {
    const getQuantity = (roomTypeId: number) =>
        selections.find((s) => s.room_type_id === roomTypeId)?.quantity ?? 0;

    const updateQuantity = (roomTypeId: number, quantity: number) => {
        const next = selections.filter((s) => s.room_type_id !== roomTypeId);
        if (quantity > 0) {
            next.push({ room_type_id: roomTypeId, quantity });
        }
        onChange(next);
    };

    return (
        <Table
            size="small"
            rowKey="room_type_id"
            dataSource={availability}
            pagination={false}
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
                {
                    title: 'Quantity',
                    render: (_, record) => {
                        const qty = getQuantity(record.room_type_id);
                        return (
                            <Space>
                                <Button
                                    size="small"
                                    icon={<MinusOutlined />}
                                    disabled={qty <= 0}
                                    onClick={() => updateQuantity(record.room_type_id, qty - 1)}
                                />
                                <InputNumber
                                    min={0}
                                    max={record.available_count}
                                    value={qty}
                                    onChange={(value) =>
                                        updateQuantity(record.room_type_id, value ?? 0)
                                    }
                                    style={{ width: 64 }}
                                />
                                <Button
                                    size="small"
                                    icon={<PlusOutlined />}
                                    disabled={qty >= record.available_count}
                                    onClick={() => updateQuantity(record.room_type_id, qty + 1)}
                                />
                            </Space>
                        );
                    },
                },
            ]}
        />
    );
}
