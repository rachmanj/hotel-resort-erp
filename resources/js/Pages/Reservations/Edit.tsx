import { Head, router, useForm } from '@inertiajs/react';
import { Button, DatePicker, Form, Input, InputNumber, Select, Steps } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AvailabilityGrid from './components/AvailabilityGrid';
import GuestSearchSelect from './components/GuestSearchSelect';
import RateSelector from './components/RateSelector';

interface RoomType {
    id: number;
    name: string;
    code: string;
    base_rate: string;
    max_occupancy: number;
}

interface RatePlan {
    id: number;
    name: string;
    room_type_id: number;
    nightly_rate: string;
    rate_type: string;
    season?: { id: number; name: string } | null;
}

interface AvailabilityRow {
    room_type_id: number;
    name: string;
    code: string;
    available_count: number;
    total_count: number;
}

interface ReservationData {
    id: number;
    reservation_code: string;
    arrival_date: string;
    departure_date: string;
    adults: number;
    children: number;
    special_requests?: string | null;
    source: string;
    agent_id: number | null;
    guest_id: number | null;
    guest?: {
        full_name: string;
        phone: string;
        email: string;
        id_number: string;
        nationality: string;
    } | null;
    room_type_id: number | null;
    room_id: number | null;
    rate_plan_id: number | null;
}

interface EditProps {
    reservation: ReservationData;
    roomTypes: RoomType[];
    ratePlans: RatePlan[];
    availability: AvailabilityRow[];
    sources: Array<{ value: string; label: string }>;
    agents: Array<{ value: number; label: string; code: string }>;
}

export default function ReservationEdit({
    reservation,
    roomTypes,
    ratePlans,
    availability,
    sources,
    agents,
}: EditProps) {
    const [step, setStep] = useState(0);

    const form = useForm({
        arrival_date: reservation.arrival_date,
        departure_date: reservation.departure_date,
        room_type_id: reservation.room_type_id,
        room_id: reservation.room_id,
        rate_plan_id: reservation.rate_plan_id,
        adults: reservation.adults,
        children: reservation.children,
        special_requests: reservation.special_requests ?? '',
        source: reservation.source,
        agent_id: reservation.agent_id,
        guest_id: reservation.guest_id,
        guest: {
            full_name: reservation.guest?.full_name ?? '',
            phone: reservation.guest?.phone ?? '',
            email: reservation.guest?.email ?? '',
            id_number: reservation.guest?.id_number ?? '',
            nationality: reservation.guest?.nationality ?? '',
        },
    });

    const selectedRoomType = roomTypes.find((rt) => rt.id === form.data.room_type_id);
    const nights =
        form.data.arrival_date && form.data.departure_date
            ? dayjs(form.data.departure_date).diff(dayjs(form.data.arrival_date), 'day')
            : 0;

    const refreshAvailability = () => {
        router.get(
            `/reservations/${reservation.id}/edit`,
            {
                arrival_date: form.data.arrival_date,
                departure_date: form.data.departure_date,
            },
            { preserveState: true, only: ['availability'] },
        );
    };

    const submit = () => {
        form.put(`/reservations/${reservation.id}`);
    };

    const steps = [
        {
            title: 'Dates',
            content: (
                <Form layout="vertical">
                    <Form.Item label="Arrival" required>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={dayjs(form.data.arrival_date)}
                            onChange={(d) => {
                                form.setData('arrival_date', d?.format('YYYY-MM-DD') ?? '');
                                refreshAvailability();
                            }}
                        />
                    </Form.Item>
                    <Form.Item label="Departure" required>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={dayjs(form.data.departure_date)}
                            onChange={(d) => {
                                form.setData('departure_date', d?.format('YYYY-MM-DD') ?? '');
                                refreshAvailability();
                            }}
                        />
                    </Form.Item>
                    <Form.Item label="Source">
                        <Select
                            value={form.data.source}
                            onChange={(v) => form.setData('source', v)}
                            options={sources.map((s) => ({ value: s.value, label: s.label }))}
                        />
                    </Form.Item>
                    <Form.Item label="Travel Agent" required={form.data.source === 'agent'}>
                        <Select
                            allowClear
                            placeholder="Select travel agent"
                            value={form.data.agent_id}
                            onChange={(v) => form.setData('agent_id', v)}
                            options={agents}
                        />
                    </Form.Item>
                </Form>
            ),
        },
        {
            title: 'Room',
            content: (
                <div>
                    <p style={{ marginBottom: 12 }}>
                        {nights} night(s) · select a room type with availability
                    </p>
                    <AvailabilityGrid
                        availability={availability}
                        selectedRoomTypeId={form.data.room_type_id}
                        onSelect={(id) => form.setData('room_type_id', id)}
                    />
                </div>
            ),
        },
        {
            title: 'Guest',
            content: (
                <Form layout="vertical">
                    <Form.Item label="Search existing guest">
                        <GuestSearchSelect
                            value={form.data.guest_id}
                            newGuestName={form.data.guest.full_name}
                            newGuestPhone={form.data.guest.phone}
                            onChange={(id) => form.setData('guest_id', id)}
                            onNewGuest={(name) => {
                                form.setData({
                                    ...form.data,
                                    guest_id: null,
                                    guest: { ...form.data.guest, full_name: name },
                                });
                            }}
                        />
                    </Form.Item>
                    <Form.Item label="Full name" required>
                        <Input
                            value={form.data.guest.full_name}
                            onChange={(e) =>
                                form.setData('guest', {
                                    ...form.data.guest,
                                    full_name: e.target.value,
                                })
                            }
                        />
                    </Form.Item>
                    <Form.Item label="Phone">
                        <Input
                            value={form.data.guest.phone}
                            onChange={(e) =>
                                form.setData('guest', {
                                    ...form.data.guest,
                                    phone: e.target.value,
                                })
                            }
                        />
                    </Form.Item>
                    <Form.Item label="Email">
                        <Input
                            value={form.data.guest.email}
                            onChange={(e) =>
                                form.setData('guest', {
                                    ...form.data.guest,
                                    email: e.target.value,
                                })
                            }
                        />
                    </Form.Item>
                </Form>
            ),
        },
        {
            title: 'Rate',
            content: (
                <Form layout="vertical">
                    <Form.Item label="Rate plan">
                        <RateSelector
                            ratePlans={ratePlans}
                            roomTypeId={form.data.room_type_id}
                            value={form.data.rate_plan_id}
                            baseRate={Number(selectedRoomType?.base_rate ?? 0)}
                            onChange={(id) => form.setData('rate_plan_id', id)}
                        />
                    </Form.Item>
                    <Form.Item label="Adults">
                        <InputNumber
                            min={1}
                            value={form.data.adults}
                            onChange={(v) => form.setData('adults', v ?? 1)}
                        />
                    </Form.Item>
                    <Form.Item label="Children">
                        <InputNumber
                            min={0}
                            value={form.data.children}
                            onChange={(v) => form.setData('children', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Special requests">
                        <Input.TextArea
                            value={form.data.special_requests}
                            onChange={(e) => form.setData('special_requests', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            ),
        },
        {
            title: 'Confirm',
            content: (
                <div>
                    <p><strong>Code:</strong> {reservation.reservation_code}</p>
                    <p><strong>Arrival:</strong> {form.data.arrival_date}</p>
                    <p><strong>Departure:</strong> {form.data.departure_date}</p>
                    <p><strong>Room type:</strong> {selectedRoomType?.name}</p>
                    <p><strong>Guest:</strong> {form.data.guest.full_name}</p>
                    <p><strong>Phone:</strong> {form.data.guest.phone || '–'}</p>
                    <p><strong>Nights:</strong> {nights}</p>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout title={`Edit ${reservation.reservation_code}`}>
            <Head title={`Edit ${reservation.reservation_code}`} />
            <Steps current={step} items={steps.map((s) => ({ title: s.title }))} style={{ marginBottom: 24 }} />
            <div style={{ maxWidth: 560 }}>{steps[step].content}</div>
            <div style={{ marginTop: 24 }}>
                {step > 0 && (
                    <Button onClick={() => setStep(step - 1)} style={{ marginRight: 8 }}>
                        Back
                    </Button>
                )}
                {step < steps.length - 1 ? (
                    <Button type="primary" onClick={() => setStep(step + 1)}>
                        Next
                    </Button>
                ) : (
                    <Button type="primary" onClick={submit} loading={form.processing}>
                        Save Changes
                    </Button>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
