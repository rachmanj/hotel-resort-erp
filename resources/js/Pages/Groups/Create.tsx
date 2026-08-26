import { Head, router, useForm } from '@inertiajs/react';
import { Alert, Button, DatePicker, Form, Input, InputNumber, Select, Space, Steps, message, theme } from 'antd';
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
    const { token } = theme.useToken();
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
    const depositStep = isTypeA ? 2 : 1;
    const totalRooms = useMemo(
        () => roomSelections.reduce((sum, s) => sum + s.quantity, 0),
        [roomSelections],
    );

    const getError = (field: string): string | undefined => {
        const direct = form.errors[field as keyof typeof form.errors];
        if (direct) {
            return String(direct);
        }

        const nested = Object.entries(form.errors).find(([key]) => key.startsWith(`${field}.`));

        return nested ? String(nested[1]) : undefined;
    };

    const stepForErrorKey = (key: string): number => {
        const root = key.split('.')[0];
        if (root === 'deposit_amount') {
            return depositStep;
        }
        if (['arrival_date', 'departure_date', 'room_selections'].includes(root)) {
            return isTypeA ? 1 : 0;
        }

        return 0;
    };

    const errorSummary = useMemo(() => Object.values(form.errors).map(String), [form.errors]);

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
            deposit_amount: Number(data.deposit_amount) || 0,
            room_selections: isTypeA ? buildRoomSelectionsPayload() : undefined,
            reservation_data: isTypeA
                ? {
                      guest_id: data.pic_guest_id,
                      adults: 1,
                      children: 0,
                      special_requests: data.special_requests || null,
                  }
                : undefined,
        })).post('/groups', {
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                message.error(typeof firstError === 'string' ? firstError : 'Please fix the errors below.');

                const errorSteps = Object.keys(errors).map(stepForErrorKey);
                if (errorSteps.length > 0) {
                    setStep(Math.min(...errorSteps));
                }
            },
        });
    };

    const steps = [
        {
            title: 'Group Info',
            content: (
                <Form layout="vertical">
                    <Form.Item
                        label="Group Name"
                        required
                        validateStatus={getError('name') ? 'error' : ''}
                        help={getError('name')}
                    >
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item
                        label="Group Type"
                        required
                        validateStatus={getError('group_type') ? 'error' : ''}
                        help={getError('group_type')}
                    >
                        <Select
                            value={form.data.group_type}
                            onChange={(value) => form.setData('group_type', value)}
                            options={groupTypes}
                        />
                    </Form.Item>
                    <Form.Item
                        label="PIC Guest"
                        required={isTypeA}
                        extra={isTypeA ? 'Required for single multi-room groups' : undefined}
                        validateStatus={getError('pic_guest_id') ? 'error' : ''}
                        help={getError('pic_guest_id')}
                    >
                        <GuestSearchSelect
                            value={form.data.pic_guest_id}
                            onChange={(id) => form.setData('pic_guest_id', id)}
                        />
                    </Form.Item>
                    <Form.Item
                        label="Company (Type C billing)"
                        validateStatus={getError('company_id') ? 'error' : ''}
                        help={getError('company_id')}
                    >
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            value={form.data.company_id}
                            onChange={(value) => form.setData('company_id', value ?? null)}
                            options={companies.map((c) => ({ value: c.id, label: c.name }))}
                        />
                    </Form.Item>
                    <Form.Item
                        label="Invoice Mode"
                        validateStatus={getError('invoice_mode') ? 'error' : ''}
                        help={getError('invoice_mode')}
                    >
                        <Select
                            value={form.data.invoice_mode}
                            onChange={(value) => form.setData('invoice_mode', value)}
                            options={invoiceModes}
                        />
                    </Form.Item>
                    <Form.Item
                        label="Special Requests"
                        validateStatus={getError('special_requests') ? 'error' : ''}
                        help={getError('special_requests')}
                    >
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
                              <Form.Item
                                  label="Arrival"
                                  required
                                  validateStatus={getError('arrival_date') ? 'error' : ''}
                                  help={getError('arrival_date')}
                              >
                                  <DatePicker
                                      style={{ width: '100%' }}
                                      value={dayjs(form.data.arrival_date)}
                                      onChange={(d) => {
                                          form.setData('arrival_date', d?.format('YYYY-MM-DD') ?? '');
                                          refreshAvailability();
                                      }}
                                  />
                              </Form.Item>
                              <Form.Item
                                  label="Departure"
                                  required
                                  validateStatus={getError('departure_date') ? 'error' : ''}
                                  help={getError('departure_date')}
                              >
                                  <DatePicker
                                      style={{ width: '100%' }}
                                      value={dayjs(form.data.departure_date)}
                                      onChange={(d) => {
                                          form.setData('departure_date', d?.format('YYYY-MM-DD') ?? '');
                                          refreshAvailability();
                                      }}
                                  />
                              </Form.Item>
                              <Form.Item
                                  label="Rooms"
                                  required
                                  validateStatus={getError('room_selections') ? 'error' : ''}
                                  help={getError('room_selections')}
                              >
                                  <RoomMultiSelect
                                      availability={availability}
                                      selections={roomSelections}
                                      onChange={setRoomSelections}
                                  />
                              </Form.Item>
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
                    <Form.Item
                        label="Required Deposit (IDR)"
                        validateStatus={getError('deposit_amount') ? 'error' : ''}
                        help={getError('deposit_amount')}
                    >
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            value={form.data.deposit_amount}
                            onChange={(value) => form.setData('deposit_amount', value ?? 0)}
                            formatter={(value) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                            parser={(value) => Number(value?.replace(/,/g, '') || 0)}
                        />
                    </Form.Item>
                    <p style={{ color: token.colorTextSecondary }}>
                        Deposit can be collected after the group is created from the group detail page.
                    </p>
                </Form>
            ),
        },
    ];

    const canNext = () => {
        if (step === 0) {
            const hasName = form.data.name.trim().length > 0;
            const needsPic = isTypeA && (form.data.pic_guest_id === null || form.data.pic_guest_id === undefined);

            return hasName && !needsPic;
        }
        if (isTypeA && step === 1) {
            return totalRooms > 0;
        }
        return true;
    };

    const nextDisabledReason = (): string | null => {
        if (step === 0) {
            if (form.data.name.trim().length === 0) {
                return 'Enter a group name to continue.';
            }
            if (isTypeA && (form.data.pic_guest_id === null || form.data.pic_guest_id === undefined)) {
                return 'Select a PIC guest to continue.';
            }
        }
        if (isTypeA && step === 1 && totalRooms === 0) {
            return 'Select at least one room to continue.';
        }

        return null;
    };

    return (
        <AuthenticatedLayout title="New Group Booking">
            <Head title="New Group Booking" />
            {errorSummary.length > 0 && (
                <Alert
                    type="error"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message="Could not create group"
                    description={
                        <ul style={{ margin: 0, paddingLeft: 20 }}>
                            {errorSummary.map((error) => (
                                <li key={error}>{error}</li>
                            ))}
                        </ul>
                    }
                />
            )}
            <Steps current={step} items={steps.map((s) => ({ title: s.title }))} style={{ marginBottom: 24 }} />
            <div style={{ marginBottom: 24 }}>{steps[step].content}</div>
            <Space direction="vertical">
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
                {!canNext() && nextDisabledReason() && (
                    <div style={{ color: token.colorWarning }}>{nextDisabledReason()}</div>
                )}
            </Space>
        </AuthenticatedLayout>
    );
}
