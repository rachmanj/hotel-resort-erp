import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, Descriptions, Form, InputNumber, Modal, Select, Space, Table, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface GroupShowProps {
    group: {
        id: number;
        group_code: string;
        name: string;
        group_type_label: string;
        status: string;
        status_label: string;
        status_color: string;
        invoice_mode: string;
        invoice_mode_label: string;
        arrival_date?: string | null;
        departure_date?: string | null;
        deposit_amount: number;
        deposit_paid_at?: string | null;
        deposit_balance: number;
        consolidated_balance: number;
        special_requests?: string | null;
        room_count: number;
        pic_guest?: { id: number; full_name: string; phone?: string; email?: string } | null;
        company?: { id: number; name: string } | null;
    };
    reservations: Array<{
        id: number;
        reservation_code: string;
        status: string;
        status_label: string;
        status_color: string;
        arrival_date: string;
        departure_date: string;
        guest?: { full_name: string } | null;
        can_check_in: boolean;
        reservation_rooms: Array<{
            id: number;
            status_label: string;
            room_number?: string | null;
            room_type?: string | null;
            can_check_out: boolean;
        }>;
    }>;
    canManage: boolean;
    canCheckIn: boolean;
    canCheckOut: boolean;
    canInvoice: boolean;
}

const formatIdr = (v: number) => `Rp ${v.toLocaleString('id-ID')}`;

export default function GroupShow({
    group,
    reservations,
    canManage,
    canCheckIn,
    canCheckOut,
    canInvoice,
}: GroupShowProps) {
    const [depositOpen, setDepositOpen] = useState(false);
    const depositForm = useForm({
        amount: 0,
        method: 'cash',
        reference_no: '',
    });

    const checkInAll = () => {
        router.post(`/groups/${group.id}/checkin`);
    };

    const checkOutAll = () => {
        router.post(`/groups/${group.id}/checkout`);
    };

    const generateInvoice = () => {
        router.post(`/groups/${group.id}/invoice/generate`, {
            mode: group.invoice_mode,
        });
    };

    const submitDeposit = () => {
        depositForm.post(`/groups/${group.id}/deposit`, {
            onSuccess: () => setDepositOpen(false),
        });
    };

    const canCheckInAll = canCheckIn && reservations.some((r) => r.can_check_in);
    const canCheckOutAll =
        canCheckOut &&
        reservations.some((r) => r.reservation_rooms.some((rr) => rr.can_check_out));

    return (
        <AuthenticatedLayout title={group.group_code}>
            <Head title={group.group_code} />
            <Space style={{ marginBottom: 16 }} wrap>
                <Link href="/groups">
                    <Button>Back to list</Button>
                </Link>
                {canCheckInAll && (
                    <Button type="primary" onClick={checkInAll}>
                        Check In All
                    </Button>
                )}
                {canCheckOutAll && (
                    <Button type="primary" onClick={checkOutAll}>
                        Check Out All
                    </Button>
                )}
                {canManage && (
                    <Button onClick={() => setDepositOpen(true)}>Collect Deposit</Button>
                )}
                {canInvoice && group.company && (
                    <Button onClick={generateInvoice}>Generate Invoice</Button>
                )}
            </Space>

            <Descriptions bordered column={2} size="small" style={{ marginBottom: 24 }}>
                <Descriptions.Item label="Code">{group.group_code}</Descriptions.Item>
                <Descriptions.Item label="Status">
                    <Tag color={group.status_color}>{group.status_label}</Tag>
                </Descriptions.Item>
                <Descriptions.Item label="Name">{group.name}</Descriptions.Item>
                <Descriptions.Item label="Type">{group.group_type_label}</Descriptions.Item>
                <Descriptions.Item label="PIC">{group.pic_guest?.full_name ?? '–'}</Descriptions.Item>
                <Descriptions.Item label="Company">{group.company?.name ?? '–'}</Descriptions.Item>
                <Descriptions.Item label="Arrival">{group.arrival_date ?? '–'}</Descriptions.Item>
                <Descriptions.Item label="Departure">{group.departure_date ?? '–'}</Descriptions.Item>
                <Descriptions.Item label="Rooms">{group.room_count}</Descriptions.Item>
                <Descriptions.Item label="Invoice Mode">{group.invoice_mode_label}</Descriptions.Item>
                <Descriptions.Item label="Deposit Required">{formatIdr(group.deposit_amount)}</Descriptions.Item>
                <Descriptions.Item label="Deposit Balance">{formatIdr(group.deposit_balance)}</Descriptions.Item>
                <Descriptions.Item label="Folio Balance" span={2}>
                    {formatIdr(group.consolidated_balance)}
                </Descriptions.Item>
                {group.special_requests && (
                    <Descriptions.Item label="Special Requests" span={2}>
                        {group.special_requests}
                    </Descriptions.Item>
                )}
            </Descriptions>

            <Table
                rowKey="id"
                dataSource={reservations}
                pagination={false}
                columns={[
                    {
                        title: 'Reservation',
                        dataIndex: 'reservation_code',
                        render: (code, record) => (
                            <Link href={`/reservations/${record.id}`}>{code}</Link>
                        ),
                    },
                    {
                        title: 'Guest',
                        render: (_, record) => record.guest?.full_name ?? '–',
                    },
                    {
                        title: 'Status',
                        dataIndex: 'status_label',
                        render: (_, record) => (
                            <Tag color={record.status_color}>{record.status_label}</Tag>
                        ),
                    },
                    {
                        title: 'Dates',
                        render: (_, record) => `${record.arrival_date} → ${record.departure_date}`,
                    },
                    {
                        title: 'Rooms',
                        render: (_, record) =>
                            record.reservation_rooms
                                .map((rr) => `${rr.room_number ?? '?'} (${rr.status_label})`)
                                .join(', '),
                    },
                ]}
            />

            <Modal
                title="Collect Group Deposit"
                open={depositOpen}
                onCancel={() => setDepositOpen(false)}
                onOk={submitDeposit}
                confirmLoading={depositForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Amount (IDR)" required>
                        <InputNumber
                            style={{ width: '100%' }}
                            min={1}
                            value={depositForm.data.amount}
                            onChange={(value) => depositForm.setData('amount', value ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Payment Method" required>
                        <Select
                            value={depositForm.data.method}
                            onChange={(value) => depositForm.setData('method', value)}
                            options={[
                                { value: 'cash', label: 'Cash' },
                                { value: 'card', label: 'Card' },
                                { value: 'transfer', label: 'Bank Transfer' },
                                { value: 'ewallet_qris', label: 'E-Wallet / QRIS' },
                            ]}
                        />
                    </Form.Item>
                    <Form.Item label="Reference No">
                        <input
                            className="ant-input"
                            value={depositForm.data.reference_no}
                            onChange={(e) => depositForm.setData('reference_no', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
