import { Head, router, useForm } from '@inertiajs/react';
import { Button, DatePicker, Form, Input, InputNumber, Select, Steps } from 'antd';
import dayjs from 'dayjs';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import GuestSearchSelect from '@/Pages/Reservations/components/GuestSearchSelect';
import RoomMultiSelect, { type RoomSelection } from './components/RoomMultiSelect';

interface CreateProps {
    roomTypes: Array<{ id: number; name: string; code: string; base_rate: string }>;
    companies: Array<{ id: number; name: string }>;
    availability: Array<{
        room_type_id: number;
        name: string;
        code: string;
        available_count: number;
        total_count: number;
    }>;
    groupTypes: Array<{ value: string; label: string }>;
    invoiceModes: Array<{ value: string; label: string }>;
    defaults: { arrival_date: string; departure_date: string };
}

export default function GroupCreate({
    companies,
    availability,
    groupTypes,
    invoiceModes,
    defaults,
}: CreateProps) {
    const [step, setStep] = useState(0);
    const [roomSelections, setRoomSelections] = useState<RoomSelection[]>([]);

    const form = useForm({
        name: '',
        group_type: 'single_multi_room',
        pic_guest_id: null as number | null,
        company_id: null as number | null,
        invoice_mode: 'per_room',
        deposit_amount: 0,
        special_requests: '',
        arrival_date: defaults.arrival_date,
        departure_date: defaults.departure_date,
    });

    const isTypeA = form.data.group_type === 'single_multi_room';
    const totalRooms = useMemo(
        () => roomSelections.reduce((sum, s) => sum + s.quantity, 0),
        [roomSelections],
    );

    const refreshAvailability = () => {
        router.get(
            '/groups/create',
            {
                arrival_date: form.data.arrival_date,
                departure_date: form.data.departure_date,
            },
            { preserveState: true, only: ['availability'] },
        );
    };

    const buildRoomSelectionsPayload = () =>
        roomSelections.flatMap((selection) =>
            Array.from({ length: selection.quantity }, () => ({
                room_type_id: selection.room_type_id,
            })),
        );

    const submit = () => {
        form.transform((data) => ({
            ...data,
            room_selections: isTypeA ? buildRoomSelectionsPayload() : undefined,
            reservation_data: isTypeA
                ? {
                      guest_id: data.pic_guest_id,
                      adults: 1,
                      children: 0,
                      special_requests: data.special_requests || null,
                  }
                : undefined,
        })).post('/groups');
    };

    const steps = [
        {
            title: 'Group Info',
            content: (
                <Form layout="vertical">
                    <Form.Item label="Group Name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Group Type" required>
                        <Select
                            value={form.data.group_type}
                            onChange={(value) => form.setData('group_type', value)}
                            options={groupTypes}
                        />
                    </Form.Item>
                    <Form.Item label="PIC Guest">
                        <GuestSearchSelect
                            value={form.data.pic_guest_id}
                            onChange={(id) => form.setData('pic_guest_id', id)}
                        />
                    </Form.Item>
                    <Form.Item label="Company (Type C billing)">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            value={form.data.company_id}
                            onChange={(value) => form.setData('company_id', value ?? null)}
                            options={companies.map((c) => ({ value: c.id, label: c.name }))}
                        />
                    </Form.Item>
                    <Form.Item label="Invoice Mode">
                        <Select
                            value={form.data.invoice_mode}
                            onChange={(value) => form.setData('invoice_mode', value)}
                            options={invoiceModes}
                        />
                    </Form.Item>
                    <Form.Item label="Special Requests">
                        <Input.TextArea
                            rows={3}
                            value={form.data.special_requests}
                            onChange={(e) => form.setData('special_requests', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            ),
        },
        ...(isTypeA
            ? [
                  {
                      title: 'Rooms',
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
                              <RoomMultiSelect
                                  availability={availability}
                                  selections={roomSelections}
                                  onChange={setRoomSelections}
                              />
                              <div style={{ marginTop: 8 }}>Total rooms selected: {totalRooms}</div>
                          </Form>
                      ),
                  },
              ]
            : []),
        {
            title: 'Deposit',
            content: (
                <Form layout="vertical">
                    <Form.Item label="Required Deposit (IDR)">
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            value={form.data.deposit_amount}
                            onChange={(value) => form.setData('deposit_amount', value ?? 0)}
                            formatter={(value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                        />
                    </Form.Item>
                    <p style={{ color: '#666' }}>
                        Deposit can be collected after the group is created from the group detail page.
                    </p>
                </Form>
            ),
        },
    ];

    const canNext = () => {
        if (step === 0) {
            return form.data.name.trim().length > 0;
        }
        if (isTypeA && step === 1) {
            return totalRooms > 0;
        }
        return true;
    };

    return (
        <AuthenticatedLayout title="New Group Booking">
            <Head title="New Group Booking" />
            <Steps current={step} items={steps.map((s) => ({ title: s.title }))} style={{ marginBottom: 24 }} />
            <div style={{ marginBottom: 24 }}>{steps[step].content}</div>
            <Space>
                {step > 0 && <Button onClick={() => setStep(step - 1)}>Back</Button>}
                {step < steps.length - 1 ? (
                    <Button type="primary" disabled={!canNext()} onClick={() => setStep(step + 1)}>
                        Next
                    </Button>
                ) : (
                    <Button type="primary" loading={form.processing} onClick={submit}>
                        Create Group
                    </Button>
                )}
            </Space>
        </AuthenticatedLayout>
    );
}
