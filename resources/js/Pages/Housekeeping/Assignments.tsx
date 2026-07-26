import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, DatePicker, Form, Modal, Select, Space } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';
import type { Paginated } from '@/types';

interface AssignmentRow {
    id: number;
    room?: { id: number; number: string };
    room_type?: string;
    housekeeper?: { id: number; name: string };
    assignment_date: string;
    shift: string;
    shift_label: string;
    status: string;
    status_label: string;
    assigned_by?: { id: number; name: string };
}

interface HousekeepingAssignmentsProps {
    assignments: Paginated<AssignmentRow>;
    housekeepers: Array<{ id: number; name: string }>;
    rooms: Array<{ id: number; number: string; room_type?: string; status: string }>;
    shifts: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
    filters: { date: string };
}

export default function HousekeepingAssignments({
    assignments,
    housekeepers,
    rooms,
    shifts,
    statuses,
    filters,
}: HousekeepingAssignmentsProps) {
    const { can } = useAuth();
    const [assignModalOpen, setAssignModalOpen] = useState(false);
    const form = useForm({
        housekeeper_id: null as number | null,
        room_ids: [] as number[],
        assignment_date: filters.date,
        shift: 'morning',
    });

    const columns: ProColumns<AssignmentRow>[] = [
        {
            title: 'Room',
            render: (_, record) => record.room?.number ?? '—',
        },
        { title: 'Type', dataIndex: 'room_type', render: (v) => v ?? '—' },
        {
            title: 'Housekeeper',
            render: (_, record) => record.housekeeper?.name ?? '—',
        },
        { title: 'Shift', dataIndex: 'shift_label' },
        { title: 'Status', dataIndex: 'status_label' },
        {
            title: 'Actions',
            render: (_, record) =>
                can('housekeeping.manage') ? (
                    <Select
                        size="small"
                        value={record.status}
                        style={{ width: 130 }}
                        options={statuses.map((s) => ({ value: s.value, label: s.label }))}
                        onChange={(value) =>
                            router.put(`/housekeeping/assignments/${record.id}`, { status: value })
                        }
                    />
                ) : null,
        },
    ];

    const submitAssign = () => {
        form.post('/housekeeping/assignments', {
            onSuccess: () => {
                setAssignModalOpen(false);
                form.reset();
                form.setData('assignment_date', filters.date);
                form.setData('shift', 'morning');
            },
        });
    };

    return (
        <AuthenticatedLayout title="Housekeeping Assignments">
            <Head title="Housekeeping Assignments" />
            <Space style={{ marginBottom: 16 }} wrap>
                <DatePicker
                    value={dayjs(filters.date)}
                    onChange={(date) =>
                        router.get('/housekeeping/assignments', {
                            date: date?.format('YYYY-MM-DD') ?? filters.date,
                        })
                    }
                />
                {can('housekeeping.manage') && (
                    <>
                        <Button type="primary" onClick={() => setAssignModalOpen(true)}>
                            Assign Rooms
                        </Button>
                        <Button
                            onClick={() =>
                                router.post('/housekeeping/assignments/generate', {
                                    date: filters.date,
                                })
                            }
                        >
                            Generate Daily Assignments
                        </Button>
                    </>
                )}
            </Space>

            <ProTable<AssignmentRow>
                rowKey="id"
                search={false}
                options={false}
                pagination={{
                    current: assignments.current_page,
                    pageSize: assignments.per_page,
                    total: assignments.total,
                    onChange: (page) =>
                        router.get('/housekeeping/assignments', { ...filters, page }, { preserveState: true }),
                }}
                dataSource={assignments.data}
                columns={columns}
            />

            <Modal
                title="Assign Rooms to Housekeeper"
                open={assignModalOpen}
                onCancel={() => setAssignModalOpen(false)}
                onOk={submitAssign}
                confirmLoading={form.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Housekeeper" required>
                        <Select
                            placeholder="Select housekeeper"
                            options={housekeepers.map((h) => ({ value: h.id, label: h.name }))}
                            value={form.data.housekeeper_id}
                            onChange={(value) => form.setData('housekeeper_id', value)}
                        />
                    </Form.Item>
                    <Form.Item label="Rooms" required>
                        <Select
                            mode="multiple"
                            placeholder="Select rooms"
                            options={rooms.map((r) => ({
                                value: r.id,
                                label: `Room ${r.number} (${r.room_type ?? '—'})`,
                            }))}
                            value={form.data.room_ids}
                            onChange={(value) => form.setData('room_ids', value)}
                        />
                    </Form.Item>
                    <Form.Item label="Date">
                        <DatePicker
                            style={{ width: '100%' }}
                            value={dayjs(form.data.assignment_date)}
                            onChange={(date) =>
                                form.setData('assignment_date', date?.format('YYYY-MM-DD') ?? filters.date)
                            }
                        />
                    </Form.Item>
                    <Form.Item label="Shift">
                        <Select
                            options={shifts.map((s) => ({ value: s.value, label: s.label }))}
                            value={form.data.shift}
                            onChange={(value) => form.setData('shift', value)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
