import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, Modal, Select, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';
import type { Paginated } from '@/types';

interface RoomRow {
    id: number;
    number: string;
    status: string;
    status_label: string;
    status_color: string;
    room_type_id: number;
    floor_id: number | null;
    room_type?: { id: number; name: string; code: string };
    floor?: { id: number; name: string; level: number };
    notes?: string | null;
}

interface RoomOption {
    id: number;
    name: string;
    code?: string;
    level?: number;
}

interface RoomsIndexProps {
    rooms: Paginated<RoomRow>;
    roomTypes: RoomOption[];
    floors: RoomOption[];
    statuses: Array<{ value: string; label: string; color: string }>;
    filters: { search?: string; status?: string };
}

export default function RoomsIndex({
    rooms,
    roomTypes,
    floors,
    statuses,
    filters,
}: RoomsIndexProps) {
    const { can } = useAuth();
    const canManage = can('rooms.manage');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<RoomRow | null>(null);

    const form = useForm({
        number: '',
        room_type_id: null as number | null,
        floor_id: null as number | null,
        notes: '',
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            number: '',
            room_type_id: null,
            floor_id: null,
            notes: '',
        });
        setModalOpen(true);
    };

    const openEdit = (record: RoomRow) => {
        setEditing(record);
        form.setData({
            number: record.number,
            room_type_id: record.room_type_id,
            floor_id: record.floor_id,
            notes: record.notes ?? '',
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/rooms/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/rooms', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const confirmDelete = (record: RoomRow) => {
        Modal.confirm({
            title: 'Delete room?',
            content: `Are you sure you want to delete room ${record.number}?`,
            okText: 'Delete',
            okType: 'danger',
            onOk: () =>
                new Promise<void>((resolve) => {
                    router.delete(`/rooms/${record.id}`, {
                        onFinish: () => resolve(),
                    });
                }),
        });
    };

    const columns: ProColumns<RoomRow>[] = [
        {
            title: 'Room',
            dataIndex: 'number',
            render: (_, record) => (
                <Link href={`/rooms/${record.id}`}>{record.number}</Link>
            ),
        },
        {
            title: 'Type',
            dataIndex: ['room_type', 'name'],
        },
        {
            title: 'Floor',
            dataIndex: ['floor', 'name'],
        },
        {
            title: 'Status',
            dataIndex: 'status_label',
            render: (_, record) => (
                <Tag color={record.status_color}>{record.status_label}</Tag>
            ),
            valueType: 'select',
            valueEnum: Object.fromEntries(
                statuses.map((s) => [s.value, { text: s.label }]),
            ),
        },
        ...(canManage
            ? [
                  {
                      title: 'Actions',
                      valueType: 'option' as const,
                      render: (_: unknown, record: RoomRow) => [
                          <Button key="edit" type="link" onClick={() => openEdit(record)}>
                              Edit
                          </Button>,
                          <Button
                              key="delete"
                              type="link"
                              danger
                              onClick={() => confirmDelete(record)}
                          >
                              Delete
                          </Button>,
                      ],
                  },
              ]
            : []),
    ];

    return (
        <AuthenticatedLayout title="Rooms">
            <Head title="Rooms" />
            <ProTable<RoomRow>
                rowKey="id"
                columns={columns}
                dataSource={rooms.data}
                search={false}
                options={false}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: rooms.current_page,
                    pageSize: rooms.per_page,
                    total: rooms.total,
                    onChange: (page) =>
                        router.get('/rooms', { ...filters, page }, { preserveState: true }),
                }}
                toolBarRender={() =>
                    canManage
                        ? [
                              <Button key="create" type="primary" onClick={openCreate}>
                                  New Room
                              </Button>,
                          ]
                        : []
                }
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={editing ? 'Edit Room' : 'New Room'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Number" required>
                        <Input
                            value={form.data.number}
                            onChange={(e) => form.setData('number', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Room Type" required>
                        <Select
                            value={form.data.room_type_id}
                            onChange={(v) => form.setData('room_type_id', v)}
                            options={roomTypes.map((rt) => ({
                                value: rt.id,
                                label: rt.code ? `${rt.name} (${rt.code})` : rt.name,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item label="Floor">
                        <Select
                            allowClear
                            value={form.data.floor_id}
                            onChange={(v) => form.setData('floor_id', v ?? null)}
                            options={floors.map((f) => ({
                                value: f.id,
                                label: f.level !== undefined ? `${f.name} (L${f.level})` : f.name,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item label="Notes">
                        <Input.TextArea
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
