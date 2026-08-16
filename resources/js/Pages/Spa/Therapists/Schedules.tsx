import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, DatePicker, Form, Modal, Select, TimePicker } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';

interface ScheduleRow {
    id: number;
    spa_therapist_id: number;
    therapist_name: string;
    work_date: string;
    start_time: string;
    end_time: string;
}

interface SchedulesProps {
    schedules: ScheduleRow[];
    therapists: Array<{ id: number; name: string }>;
    filters: { therapist_id: number | null; work_date: string };
}

export default function Schedules({ schedules, therapists, filters }: SchedulesProps) {
    const { can } = useAuth();
    const [creating, setCreating] = useState(false);

    const createForm = useForm({
        spa_therapist_id: therapists[0]?.id ?? null,
        work_date: filters.work_date,
        start_time: '09:00',
        end_time: '17:00',
    });

    const columns: ProColumns<ScheduleRow>[] = [
        { title: 'Therapist', dataIndex: 'therapist_name' },
        { title: 'Date', dataIndex: 'work_date' },
        { title: 'Start', dataIndex: 'start_time' },
        { title: 'End', dataIndex: 'end_time' },
        can('spa.manage') && {
            title: 'Actions',
            render: (_, record) => (
                <Button
                    size="small"
                    danger
                    onClick={() => router.delete(`/spa/therapists/schedules/${record.id}`)}
                >
                    Remove
                </Button>
            ),
        },
    ].filter(Boolean) as ProColumns<ScheduleRow>[];

    return (
        <AuthenticatedLayout title="Therapist Schedules">
            <Head title="Therapist Schedules" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 12, flexWrap: 'wrap', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', gap: 8 }}>
                    <Select
                        allowClear
                        placeholder="Therapist"
                        style={{ width: 180 }}
                        value={filters.therapist_id}
                        options={therapists.map((t) => ({ value: t.id, label: t.name }))}
                        onChange={(v) => router.get('/spa/therapists/schedules', { ...filters, therapist_id: v }, { preserveState: true })}
                    />
                    <DatePicker
                        value={dayjs(filters.work_date)}
                        onChange={(d) => router.get('/spa/therapists/schedules', { ...filters, work_date: d?.format('YYYY-MM-DD') }, { preserveState: true })}
                    />
                </div>
                {can('spa.manage') && (
                    <Button type="primary" onClick={() => setCreating(true)}>Add Schedule</Button>
                )}
            </div>
            <ProTable
                rowKey="id"
                search={false}
                options={false}
                pagination={false}
                dataSource={schedules}
                columns={columns}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title="Add Schedule"
                open={creating}
                onCancel={() => setCreating(false)}
                onOk={() => createForm.post('/spa/therapists/schedules', { onSuccess: () => setCreating(false) })}
                confirmLoading={createForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Therapist" required>
                        <Select
                            value={createForm.data.spa_therapist_id}
                            options={therapists.map((t) => ({ value: t.id, label: t.name }))}
                            onChange={(v) => createForm.setData('spa_therapist_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Work Date" required>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={dayjs(createForm.data.work_date)}
                            onChange={(d) => createForm.setData('work_date', d?.format('YYYY-MM-DD') ?? '')}
                        />
                    </Form.Item>
                    <Form.Item label="Start Time" required>
                        <TimePicker
                            format="HH:mm"
                            style={{ width: '100%' }}
                            value={dayjs(createForm.data.start_time, 'HH:mm')}
                            onChange={(t) => createForm.setData('start_time', t?.format('HH:mm') ?? '09:00')}
                        />
                    </Form.Item>
                    <Form.Item label="End Time" required>
                        <TimePicker
                            format="HH:mm"
                            style={{ width: '100%' }}
                            value={dayjs(createForm.data.end_time, 'HH:mm')}
                            onChange={(t) => createForm.setData('end_time', t?.format('HH:mm') ?? '17:00')}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
