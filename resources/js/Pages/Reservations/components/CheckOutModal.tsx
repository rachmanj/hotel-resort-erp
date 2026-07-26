import { router } from '@inertiajs/react';
import { Descriptions, Modal } from 'antd';

interface CheckOutModalProps {
    open: boolean;
    reservationRoom: {
        id: number;
        room?: { number: string } | null;
        nightly_rate: string;
    };
    onClose: () => void;
}

export default function CheckOutModal({ open, reservationRoom, onClose }: CheckOutModalProps) {
    const handleConfirm = () => {
        router.post(
            `/reservation-rooms/${reservationRoom.id}/checkout`,
            {},
            { onSuccess: () => onClose() },
        );
    };

    return (
        <Modal
            title={`Check Out — Room ${reservationRoom.room?.number ?? '—'}`}
            open={open}
            onCancel={onClose}
            onOk={handleConfirm}
            okText="Confirm Check Out"
        >
            <Descriptions column={1} size="small" bordered>
                <Descriptions.Item label="Room">{reservationRoom.room?.number ?? '—'}</Descriptions.Item>
                <Descriptions.Item label="Nightly Rate">
                    Rp {Number(reservationRoom.nightly_rate).toLocaleString('id-ID')}
                </Descriptions.Item>
            </Descriptions>
            <p style={{ marginTop: 16, color: '#666' }}>
                The room will be marked vacant/dirty for housekeeping. The folio will be closed unless billed to a company account.
            </p>
        </Modal>
    );
}
