import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Checkbox, DatePicker, Form, Modal, Select, Tag } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';

interface AppointmentRow {
    id: number;
    treatment: { id: number; name: string; duration_minutes: number; price: number } | null;
    therapist: { id: number; name: string } | null;
    guest: { id: number; full_name: string } | null;
    reservation: { id: number; reservation_code: string } | null;
    scheduled_at: string;
    status: string;
    status_label: string;
    charged_to_room: boolean;
    price: number;
}

interface AppointmentsIndexProps {
    appointments: {
        data: AppointmentRow[];
        total?: number;
        current_page?: number;
        per_page?: number;
    };
    filters: { status?: string; date?: string; therapist_id?: number };
    statusOptions: Array<{ value: string; label: string }>;
    treatments: Array<{ id: number; name: string; duration_minutes: number; price: number }>;
    therapists: Array<{ id: number; name: string }>;
    guests: Array<{ id: number; full_name: string }>;
    checkedInReservations: Array<{ id: number; reservation_code: string; guest_id: number; guest_name: string }>;
}

const statusColors: Record<string, string> = {
    booked: 'blue',
    confirmed: 'cyan',
    in_progress: 'orange',
    completed: 'green',
    cancelled: 'red',
    no_show: 'default',
};

export default function AppointmentsIndex({
    appointments,
    filters,
    statusOptions,
    treatments,
    therapists,
    guests,
    checkedInReservations,
}: AppointmentsIndexProps) {
    const { can } = useAuth();
    const [creating, setCreating] = useState(false);
    const [statusModal, setStatusModal] = useState<AppointmentRow | null>(null);
    const [chargeModal, setChargeModal] = useState<AppointmentRow | null>(null);

    const createForm = useForm({
        spa_treatment_id: treatments[0]?.id ?? null,
        spa_therapist_id: therapists[0]?.id ?? null,
        scheduled_at: dayjs().format('YYYY-MM-DD HH:mm'),
        guest_id: null as number | null,
        reservation_id: null as number | null,
        charged_to_room: false,
    });

    const statusForm = useForm({ status: 'confirmed' });
    const chargeForm = useForm({ reservation_id: null as number | null });

    const onReservationChange = (reservationId: number | null) => {
        createForm.setData('reservation_id', reservationId);
        if (reservationId) {
            const res = checkedInReservations.find((r) => r.id === reservationId);
            if (res) {
                createForm.setData('guest_id', res.guest_id);
            }
        }
    };

    const columns: ProColumns<AppointmentRow>[] = [
        { title: 'Date/Time', dataIndex: 'scheduled_at' },
        { title: 'Treatment', dataIndex: ['treatment', 'name'] },
        { title: 'Therapist', dataIndex: ['therapist', 'name'] },
        { title: 'Guest', dataIndex: ['guest', 'full_name'], render: (_, r) => r.guest?.full_name ?? '—' },
        {
            title: 'Status',
            dataIndex: 'status_label',
            render: (_, record) => <Tag color={statusColors[record.status]}>{record.status_label}</Tag>,
        },
        {
            title: 'Price',
            dataIndex: 'price',
            render: (v) => `Rp ${Number(v).toLocaleString('id-ID')}`,
        },
        {
            title: 'Room Charge',
            dataIndex: 'charged_to_room',
            render: (v) => (v ? <Tag color="purple">Yes</Tag> : '—'),
        },
        can('spa.manage') && {
            title: 'Actions',
            render: (_, record) => (
                <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                    {record.status !== 'cancelled' && record.status !== 'completed' && (
                        <>
                            <Button size="small" onClick={() => { setStatusModal(record); statusForm.setData('status', record.status); }}>
                                Status
                            </Button>
                            <Button size="small" danger onClick={() => router.post(`/spa/appointments/${record.id}/cancel`)}>
                                Cancel
                            </Button>
                        </>
                    )}
                    {!record.charged_to_room && record.status !== 'cancelled' && (
                        <Button size="small" onClick={() => { setChargeModal(record); chargeForm.setData('reservation_id', record.reservation?.id ?? null); }}>
                            Charge Room
                        </Button>
                    )}
                </div>
            ),
        },
    ].filter(Boolean) as ProColumns<AppointmentRow>[];

    return (
        <AuthenticatedLayout title="Spa Appointments">
            <Head title="Spa Appointments" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 12, flexWrap: 'wrap', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', gap: 8 }}>
                    <Select
                        allowClear
                        placeholder="Status"
                        style={{ width: 140 }}
                        value={filters.status}
                        options={statusOptions}
                        onChange={(v) => router.get('/spa/appointments', { ...filters, status: v }, { preserveState: true })}
                    />
                    <Select
                        allowClear
                        placeholder="Therapist"
                        style={{ width: 160 }}
                        value={filters.therapist_id}
                        options={therapists.map((t) => ({ value: t.id, label: t.name }))}
                        onChange={(v) => router.get('/spa/appointments', { ...filters, therapist_id: v }, { preserveState: true })}
                    />
                    <DatePicker
                        value={filters.date ? dayjs(filters.date) : null}
                        onChange={(d) => router.get('/spa/appointments', { ...filters, date: d?.format('YYYY-MM-DD') }, { preserveState: true })}
                    />
                </div>
                {can('spa.manage') && (
                    <Button type="primary" onClick={() => setCreating(true)}>Book Appointment</Button>
                )}
            </div>
            <ProTable<AppointmentRow>
                rowKey="id"
                search={false}
                options={false}
                dataSource={appointments.data}
                columns={columns}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    total: appointments.total,
                    current: appointments.current_page,
                    pageSize: appointments.per_page ?? 20,
                    onChange: (page) => router.get('/spa/appointments', { ...filters, page }, { preserveState: true }),
                }}
            />

            <Modal
                title="Book Appointment"
                open={creating}
                onCancel={() => setCreating(false)}
                onOk={() => createForm.post('/spa/appointments', { onSuccess: () => setCreating(false) })}
                confirmLoading={createForm.processing}
                width={520}
            >
                <Form layout="vertical">
                    <Form.Item label="Treatment" required>
                        <Select
                            value={createForm.data.spa_treatment_id}
                            options={treatments.map((t) => ({ value: t.id, label: `${t.name} (${t.duration_minutes} min)` }))}
                            onChange={(v) => createForm.setData('spa_treatment_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Therapist" required>
                        <Select
                            value={createForm.data.spa_therapist_id}
                            options={therapists.map((t) => ({ value: t.id, label: t.name }))}
                            onChange={(v) => createForm.setData('spa_therapist_id', v)}
                        />
                    </Form.Item>
                    <Form.Item label="Scheduled At" required>
                        <DatePicker
                            showTime
                            style={{ width: '100%' }}
                            format="YYYY-MM-DD HH:mm"
                            value={dayjs(createForm.data.scheduled_at)}
                            onChange={(d) => createForm.setData('scheduled_at', d?.format('YYYY-MM-DD HH:mm') ?? '')}
                        />
                    </Form.Item>
                    <Form.Item label="Guest">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            value={createForm.data.guest_id}
                            options={guests.map((g) => ({ value: g.id, label: g.full_name }))}
                            onChange={(v) => createForm.setData('guest_id', v ?? null)}
                        />
                    </Form.Item>
                    <Form.Item label="Reservation (checked-in)">
                        <Select
                            allowClear
                            value={createForm.data.reservation_id}
                            options={checkedInReservations.map((r) => ({
                                value: r.id,
                                label: `${r.reservation_code} — ${r.guest_name}`,
                            }))}
                            onChange={onReservationChange}
                        />
                    </Form.Item>
                    <Form.Item>
                        <Checkbox
                            checked={createForm.data.charged_to_room}
                            onChange={(e) => createForm.setData('charged_to_room', e.target.checked)}
                        >
                            Charge to room
                        </Checkbox>
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title="Update Status"
                open={!!statusModal}
                onCancel={() => setStatusModal(null)}
                onOk={() => statusModal && statusForm.put(`/spa/appointments/${statusModal.id}/status`, { onSuccess: () => setStatusModal(null) })}
                confirmLoading={statusForm.processing}
            >
                <Select
                    style={{ width: '100%' }}
                    value={statusForm.data.status}
                    options={statusOptions}
                    onChange={(v) => statusForm.setData('status', v)}
                />
            </Modal>

            <Modal
                title="Charge to Room"
                open={!!chargeModal}
                onCancel={() => setChargeModal(null)}
                onOk={() => chargeModal && chargeForm.post(`/spa/appointments/${chargeModal.id}/charge`, { onSuccess: () => setChargeModal(null) })}
                confirmLoading={chargeForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Reservation" required>
                        <Select
                            value={chargeForm.data.reservation_id}
                            options={checkedInReservations.map((r) => ({
                                value: r.id,
                                label: `${r.reservation_code} — ${r.guest_name}`,
                            }))}
                            onChange={(v) => chargeForm.setData('reservation_id', v)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
