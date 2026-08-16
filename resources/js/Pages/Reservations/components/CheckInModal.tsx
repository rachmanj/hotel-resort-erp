import { router } from '@inertiajs/react';
import { Descriptions, Modal, message, theme } from 'antd';

interface CheckInModalProps {
    open: boolean;
    reservation: {
        id: number;
        reservation_code: string;
        guest?: { full_name: string; id_number?: string; phone?: string };
        reservation_rooms: Array<{
            room?: { number: string } | null;
            room_type?: { name: string } | null;
            nightly_rate: string;
        }>;
    };
    onClose: () => void;
}

export default function CheckInModal({ open, reservation, onClose }: CheckInModalProps) {
    const { token } = theme.useToken();
    const handleConfirm = () => {
        router.post(
            `/reservations/${reservation.id}/checkin`,
            {},
            {
                onSuccess: () => onClose(),
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    message.error(typeof firstError === 'string' ? firstError : 'Check-in failed.');
                },
            },
        );
    };

    return (
        <Modal
            title="Check In Guest"
            open={open}
            onCancel={onClose}
            onOk={handleConfirm}
            okText="Confirm Check In"
        >
            <Descriptions column={1} size="small" bordered>
                <Descriptions.Item label="Reservation">{reservation.reservation_code}</Descriptions.Item>
                <Descriptions.Item label="Guest">{reservation.guest?.full_name}</Descriptions.Item>
                <Descriptions.Item label="ID">{reservation.guest?.id_number ?? '–'}</Descriptions.Item>
                <Descriptions.Item label="Phone">{reservation.guest?.phone ?? '–'}</Descriptions.Item>
                <Descriptions.Item label="Room(s)">
                    {reservation.reservation_rooms
                        .map((rr) => `${rr.room?.number ?? '–'} (${rr.room_type?.name})`)
                        .join(', ')}
                </Descriptions.Item>
            </Descriptions>
            <p style={{ marginTop: 16, color: token.colorTextSecondary }}>
                Room charges will be posted to the folio with applicable taxes (SC 10% + PPN 11%).
            </p>
        </Modal>
    );
}
