import { router } from '@inertiajs/react';
import { Alert, Button, Descriptions, Modal, Tag, theme } from 'antd';
import { newIdempotencyKey } from '@/lib/idempotency';

interface CheckOutModalProps {
    open: boolean;
    reservationRoom: {
        id: number;
        room?: { number: string } | null;
        nightly_rate: string;
    };
    folioId?: number | null;
    folioBalance?: number;
    folioChargesTotal?: number;
    folioPaymentsTotal?: number;
    isCompanyBilled?: boolean;
    onClose: () => void;
}

const formatIdr = (amount: number) => `Rp ${amount.toLocaleString('id-ID')}`;

export default function CheckOutModal({
    open,
    reservationRoom,
    folioId,
    folioBalance = 0,
    folioChargesTotal = 0,
    folioPaymentsTotal = 0,
    isCompanyBilled = false,
    onClose,
}: CheckOutModalProps) {
    const { token } = theme.useToken();
    const hasOutstanding = folioBalance > 0;
    const settlementRequired = hasOutstanding && !isCompanyBilled;
    const balanceColor = folioBalance <= 0 ? 'green' : 'orange';

    const handleConfirm = () => {
        router.post(
            `/reservation-rooms/${reservationRoom.id}/checkout`,
            {},
            {
                headers: { 'X-Idempotency-Key': newIdempotencyKey() },
                onSuccess: () => onClose(),
            },
        );
    };

    return (
        <Modal
            title={`Check Out · Room ${reservationRoom.room?.number ?? '–'}`}
            open={open}
            onCancel={onClose}
            footer={[
                <Button key="cancel" onClick={onClose}>
                    Cancel
                </Button>,
                settlementRequired && folioId ? (
                    <Button key="settle" type="primary" href={`/folios/${folioId}`}>
                        Settle Payment
                    </Button>
                ) : null,
                <Button
                    key="checkout"
                    type="primary"
                    danger={settlementRequired}
                    disabled={settlementRequired}
                    onClick={handleConfirm}
                >
                    Confirm Check Out
                </Button>,
            ]}
        >
            <Descriptions column={1} size="small" bordered>
                <Descriptions.Item label="Room">{reservationRoom.room?.number ?? '–'}</Descriptions.Item>
                <Descriptions.Item label="Nightly Rate">
                    {formatIdr(Number(reservationRoom.nightly_rate))}
                </Descriptions.Item>
                <Descriptions.Item label="Charges">{formatIdr(folioChargesTotal)}</Descriptions.Item>
                <Descriptions.Item label="Payments">{formatIdr(folioPaymentsTotal)}</Descriptions.Item>
                <Descriptions.Item label="Balance">
                    <Tag color={balanceColor}>{formatIdr(folioBalance)}</Tag>
                </Descriptions.Item>
            </Descriptions>

            {isCompanyBilled && (
                <Alert
                    style={{ marginTop: 16 }}
                    type="info"
                    showIcon
                    message="Billed to company · no settlement required"
                />
            )}

            {settlementRequired && (
                <Alert
                    style={{ marginTop: 16 }}
                    type="warning"
                    showIcon
                    message={`Outstanding balance: ${formatIdr(folioBalance)} · settle before checkout`}
                />
            )}

            {!settlementRequired && !isCompanyBilled && (
                <p style={{ marginTop: 16, color: token.colorTextSecondary }}>
                    The room will be marked vacant/dirty for housekeeping. The folio will be closed unless billed to a company account.
                </p>
            )}
        </Modal>
    );
}
