import { router, usePage } from '@inertiajs/react';
import { Button, Card, Input, List, Modal, Space, Typography, theme } from 'antd';
import { useState } from 'react';
import type { ReconciliationHeader, SubmitChecklistItem } from '../types';

interface SubmitPanelProps {
    reconciliation: ReconciliationHeader;
    checklist: SubmitChecklistItem[];
    isPreparer: boolean;
    isEditable: boolean;
}

export default function SubmitPanel({ reconciliation, checklist, isPreparer, isEditable }: SubmitPanelProps) {
    const { token } = theme.useToken();
    const permissions = (usePage().props.auth as { permissions?: string[] }).permissions ?? [];
    const [rejectReason, setRejectReason] = useState('');
    const [voidReason, setVoidReason] = useState('');
    const [reopenReason, setReopenReason] = useState('');
    const [rejectOpen, setRejectOpen] = useState(false);
    const [voidOpen, setVoidOpen] = useState(false);
    const [reopenOpen, setReopenOpen] = useState(false);

    const canValidate = permissions.includes('bankrec.validate');
    const canReconcile = permissions.includes('bankrec.reconcile');
    const allPassed = checklist.every((item) => item.passed);
    const isPendingValidation =
        reconciliation.validation_status === 'pending' || reconciliation.status === 'pending_validation';
    const isCompleted = reconciliation.status === 'completed';

    const confirmPost = (title: string, content: string, url: string, data?: Record<string, string>) => {
        Modal.confirm({
            title,
            content,
            onOk: () => router.post(url, data ?? {}),
        });
    };

    return (
        <Card size="small" title="Submit and validation">
            <List
                size="small"
                dataSource={checklist}
                renderItem={(item) => (
                    <List.Item>
                        <Space>
                            <Typography.Text style={{ color: item.passed ? token.colorSuccess : token.colorError }}>
                                {item.passed ? 'Pass' : 'Fail'}
                            </Typography.Text>
                            <div>
                                <div>{item.label}</div>
                                {item.detail && (
                                    <Typography.Text type="secondary" style={{ fontSize: token.fontSizeSM }}>
                                        {item.detail}
                                    </Typography.Text>
                                )}
                            </div>
                        </Space>
                    </List.Item>
                )}
            />

            <Space wrap style={{ marginTop: token.marginMD }}>
                {isEditable && canReconcile && (
                    <Button
                        type="primary"
                        disabled={!allPassed}
                        onClick={() =>
                            confirmPost(
                                'Submit for validation?',
                                'Another user with validation permission must approve this reconciliation.',
                                `/accounting/bank-reconciliation/${reconciliation.id}/submit`,
                            )
                        }
                    >
                        Submit for validation
                    </Button>
                )}

                {isPendingValidation && canValidate && !isPreparer && (
                    <>
                        <Button
                            type="primary"
                            onClick={() =>
                                confirmPost(
                                    'Validate reconciliation?',
                                    'This will mark the session as completed and lock it from further edits.',
                                    `/accounting/bank-reconciliation/${reconciliation.id}/validate`,
                                )
                            }
                        >
                            Validate
                        </Button>
                        <Button danger onClick={() => setRejectOpen(true)}>
                            Reject
                        </Button>
                    </>
                )}

                {isCompleted && canValidate && (
                    <Button onClick={() => setReopenOpen(true)}>
                        Reopen
                    </Button>
                )}

                {isEditable && canValidate && (
                    <Button danger onClick={() => setVoidOpen(true)}>
                        Void
                    </Button>
                )}
            </Space>

            <Modal
                title="Reject reconciliation"
                open={rejectOpen}
                okText="Reject"
                okButtonProps={{ danger: true }}
                onCancel={() => setRejectOpen(false)}
                onOk={() =>
                    router.post(`/accounting/bank-reconciliation/${reconciliation.id}/reject`, {
                        reason: rejectReason,
                    })
                }
            >
                <Typography.Paragraph type="secondary">A reason is required.</Typography.Paragraph>
                <Input.TextArea
                    aria-label="Rejection reason"
                    rows={3}
                    value={rejectReason}
                    onChange={(event) => setRejectReason(event.target.value)}
                />
            </Modal>

            <Modal
                title="Void reconciliation"
                open={voidOpen}
                okText="Void"
                okButtonProps={{ danger: true }}
                onCancel={() => setVoidOpen(false)}
                onOk={() =>
                    router.post(`/accounting/bank-reconciliation/${reconciliation.id}/void`, {
                        reason: voidReason,
                    })
                }
            >
                <Typography.Paragraph type="secondary">This session will be voided. Provide a reason.</Typography.Paragraph>
                <Input.TextArea
                    aria-label="Void reason"
                    rows={3}
                    value={voidReason}
                    onChange={(event) => setVoidReason(event.target.value)}
                />
            </Modal>

            <Modal
                title="Reopen reconciliation"
                open={reopenOpen}
                onCancel={() => setReopenOpen(false)}
                onOk={() =>
                    router.post(`/accounting/bank-reconciliation/${reconciliation.id}/reopen`, {
                        reason: reopenReason,
                    })
                }
            >
                <Typography.Paragraph type="secondary">Provide a reason for reopening this completed session.</Typography.Paragraph>
                <Input.TextArea
                    aria-label="Reopen reason"
                    rows={3}
                    value={reopenReason}
                    onChange={(event) => setReopenReason(event.target.value)}
                />
            </Modal>

            {reconciliation.rejection_reason && (
                <Typography.Paragraph type="secondary" style={{ marginTop: token.marginMD, marginBottom: 0 }}>
                    Last rejection: {reconciliation.rejection_reason}
                </Typography.Paragraph>
            )}
        </Card>
    );
}
