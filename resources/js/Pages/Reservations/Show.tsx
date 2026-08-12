import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Descriptions, Form, Input, Modal, Space, Table, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CheckInModal from './components/CheckInModal';
import CheckOutModal from './components/CheckOutModal';

interface ReservationShowProps {
    reservation: {
        id: number;
        reservation_code: string;
        status: string;
        status_label: string;
        status_color: string;
        source: string;
        source_label: string;
        arrival_date: string;
        departure_date: string;
        adults: number;
        children_count: number;
        special_requests?: string | null;
        cancelled_reason?: string | null;
        created_by?: { id: number; name: string } | null;
        promotion?: { id: number; name: string; discount_summary: string } | null;
        promotion_redemptions?: Array<{
            promotion_name?: string;
            code?: string;
            discount_amount: string;
        }>;
        guest?: {
            id: number;
            full_name: string;
            phone?: string;
            email?: string;
            id_number?: string;
            id_type?: string;
            nationality?: string;
            address?: string;
            vip_tier?: string;
            is_blacklisted?: boolean;
        };
        reservation_rooms: Array<{
            id: number;
            status: string;
            status_label: string;
            nightly_rate: string;
            gross_nightly_rate?: string | null;
            promotion?: { id: number; name: string; discount_summary: string } | null;
            room?: { id: number; number: string } | null;
            room_type?: { id: number; name: string; code: string } | null;
            rate_plan?: { id: number; name: string } | null;
        }>;
    };
    folio?: { id: number; folio_no: string; status: string } | null;
    canCancel: boolean;
    canCheckIn: boolean;
    canCheckOut: boolean;
    canViewFolio: boolean;
}

export default function ReservationShow({
    reservation,
    folio,
    canCancel,
    canCheckIn,
    canCheckOut,
    canViewFolio,
}: ReservationShowProps) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const [checkInOpen, setCheckInOpen] = useState(false);
    const [checkOutRoom, setCheckOutRoom] = useState<ReservationShowProps['reservation']['reservation_rooms'][0] | null>(null);
    const cancelForm = useForm({ cancelled_reason: '' });

    const submitCancel = () => {
        cancelForm.post(`/reservations/${reservation.id}/cancel`, {
            onSuccess: () => setCancelOpen(false),
        });
    };

    return (
        <AuthenticatedLayout title={reservation.reservation_code}>
            <Head title={reservation.reservation_code} />
            <Space style={{ marginBottom: 16 }} wrap>
                <Link href="/reservations">
                    <Button>Back to list</Button>
                </Link>
                {canCheckIn && reservation.status === 'confirmed' && (
                    <Button type="primary" onClick={() => setCheckInOpen(true)}>
                        Check In
                    </Button>
                )}
                {canViewFolio && folio && (
                    <Link href={`/folios/${folio.id}`}>
                        <Button>View Folio ({folio.folio_no})</Button>
                    </Link>
                )}
                {canCancel && reservation.status !== 'cancelled' && (
                    <Button danger onClick={() => setCancelOpen(true)}>
                        Cancel reservation
                    </Button>
                )}
            </Space>

            <Descriptions bordered column={2} size="small" style={{ marginBottom: 24 }}>
                <Descriptions.Item label="Status">
                    <Tag color={reservation.status_color}>{reservation.status_label}</Tag>
                </Descriptions.Item>
                <Descriptions.Item label="Source">{reservation.source_label}</Descriptions.Item>
                <Descriptions.Item label="Arrival">{reservation.arrival_date}</Descriptions.Item>
                <Descriptions.Item label="Departure">{reservation.departure_date}</Descriptions.Item>
                <Descriptions.Item label="Adults">{reservation.adults}</Descriptions.Item>
                <Descriptions.Item label="Children">{reservation.children_count}</Descriptions.Item>
                {reservation.special_requests && (
                    <Descriptions.Item label="Special requests" span={2}>
                        {reservation.special_requests}
                    </Descriptions.Item>
                )}
                {reservation.cancelled_reason && (
                    <Descriptions.Item label="Cancel reason" span={2}>
                        {reservation.cancelled_reason}
                    </Descriptions.Item>
                )}
                {reservation.promotion && (
                    <Descriptions.Item label="Promotion" span={2}>
                        <Tag color="green">
                            {reservation.promotion.name} — {reservation.promotion.discount_summary}
                        </Tag>
                    </Descriptions.Item>
                )}
            </Descriptions>

            <h3>Guest</h3>
            <Descriptions bordered column={2} size="small" style={{ marginBottom: 24 }}>
                <Descriptions.Item label="Name">
                    {reservation.guest?.full_name}
                    {reservation.guest?.vip_tier && reservation.guest.vip_tier !== 'none' && (
                        <Tag color="gold" style={{ marginLeft: 8 }}>
                            {String(reservation.guest.vip_tier).toUpperCase()}
                        </Tag>
                    )}
                    {reservation.guest?.is_blacklisted && (
                        <Tag color="red" style={{ marginLeft: 8 }}>BLACKLISTED</Tag>
                    )}
                </Descriptions.Item>
                <Descriptions.Item label="Phone">{reservation.guest?.phone ?? '—'}</Descriptions.Item>
                <Descriptions.Item label="Email">{reservation.guest?.email ?? '—'}</Descriptions.Item>
                <Descriptions.Item label="ID">{reservation.guest?.id_number ?? '—'}</Descriptions.Item>
            </Descriptions>

            <h3>Rooms</h3>
            <Table
                rowKey="id"
                size="small"
                pagination={false}
                childrenColumnName="rowChildren"
                dataSource={reservation.reservation_rooms}
                columns={[
                    { title: 'Room', dataIndex: ['room', 'number'], render: (v) => v ?? '—' },
                    { title: 'Type', dataIndex: ['room_type', 'name'] },
                    { title: 'Rate plan', dataIndex: ['rate_plan', 'name'], render: (v) => v ?? 'Base' },
                    {
                        title: 'Promotion',
                        dataIndex: ['promotion', 'name'],
                        render: (v, record) =>
                            record.promotion ? (
                                <Tag color="green">{record.promotion.name}</Tag>
                            ) : (
                                '—'
                            ),
                    },
                    {
                        title: 'Nightly rate',
                        dataIndex: 'nightly_rate',
                        render: (v, record) => {
                            const net = `Rp ${Number(v).toLocaleString('id-ID')}`;
                            if (
                                record.gross_nightly_rate &&
                                Number(record.gross_nightly_rate) > Number(v)
                            ) {
                                return (
                                    <>
                                        <s style={{ marginRight: 8 }}>
                                            Rp {Number(record.gross_nightly_rate).toLocaleString('id-ID')}
                                        </s>
                                        {net}
                                    </>
                                );
                            }
                            return net;
                        },
                    },
                    { title: 'Status', dataIndex: 'status_label' },
                    {
                        title: 'Actions',
                        render: (_, record) =>
                            canCheckOut && record.status === 'checked_in' ? (
                                <Button size="small" onClick={() => setCheckOutRoom(record)}>
                                    Check Out
                                </Button>
                            ) : null,
                    },
                ]}
            />

            <Modal
                title="Cancel reservation"
                open={cancelOpen}
                onCancel={() => setCancelOpen(false)}
                onOk={submitCancel}
                confirmLoading={cancelForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Reason">
                        <Input.TextArea
                            value={cancelForm.data.cancelled_reason}
                            onChange={(e) => cancelForm.setData('cancelled_reason', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <CheckInModal
                open={checkInOpen}
                reservation={reservation}
                onClose={() => setCheckInOpen(false)}
            />

            {checkOutRoom && (
                <CheckOutModal
                    open={!!checkOutRoom}
                    reservationRoom={checkOutRoom}
                    onClose={() => setCheckOutRoom(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
