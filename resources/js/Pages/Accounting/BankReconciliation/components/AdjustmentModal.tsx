import { useForm } from '@inertiajs/react';
import { Form, Input, Modal, Select, Typography, theme } from 'antd';
import { useEffect } from 'react';
import { formatFullIdr } from '../formatMoney';
import type { PostableAccount, StatementLineRow } from '../types';

interface AdjustmentModalProps {
    open: boolean;
    reconciliationId: number;
    line: StatementLineRow | null;
    postableAccounts: PostableAccount[];
    onClose: () => void;
}

export default function AdjustmentModal({
    open,
    reconciliationId,
    line,
    postableAccounts,
    onClose,
}: AdjustmentModalProps) {
    const { token } = theme.useToken();
    const form = useForm({
        statement_line_id: line?.id ?? 0,
        counter_account_id: line?.suggested_counter_account_id ?? null as number | null,
        description: line?.description ?? '',
    });

    useEffect(() => {
        if (!line) {
            return;
        }

        form.setData({
            statement_line_id: line.id,
            counter_account_id: line.suggested_counter_account_id,
            description: line.description ?? '',
        });
    }, [line]);

    const submit = () => {
        if (!line) {
            return;
        }

        Modal.confirm({
            title: 'Post bank adjustment?',
            content:
                'This will create a journal entry in the general ledger. The adjustment cannot be removed without reversing the journal.',
            okText: 'Post adjustment',
            onOk: () => {
                form.post(`/accounting/bank-reconciliation/${reconciliationId}/adjustments`, {
                    onSuccess: () => onClose(),
                });
            },
        });
    };

    if (!line) {
        return null;
    }

    const directionLabel = line.net_amount < 0 ? 'Debit (money out)' : 'Credit (money in)';

    return (
        <Modal
            title="Create bank adjustment"
            open={open}
            onCancel={onClose}
            onOk={submit}
            confirmLoading={form.processing}
            destroyOnClose
        >
            <div style={{ marginBottom: token.marginMD }}>
                <Typography.Text type="secondary">Statement line</Typography.Text>
                <div>{line.description || '—'}</div>
                <div style={{ marginTop: token.marginXS }}>
                    Amount: <strong>{formatFullIdr(Math.abs(line.net_amount))}</strong> ({directionLabel})
                </div>
            </div>
            {line.suggested_counter_account_code && (
                <Typography.Paragraph type="secondary" style={{ fontSize: token.fontSizeSM }}>
                    Suggested counter account: {line.suggested_counter_account_code}
                    {line.suggested_counter_account_name ? ` — ${line.suggested_counter_account_name}` : ''}
                </Typography.Paragraph>
            )}
            <Form layout="vertical">
                <Form.Item label="Counter account" required>
                    <Select
                        showSearch
                        optionFilterProp="label"
                        aria-label="Counter account"
                        value={form.data.counter_account_id}
                        options={postableAccounts.map((account) => ({
                            value: account.id,
                            label: account.label,
                        }))}
                        onChange={(value) => form.setData('counter_account_id', value)}
                    />
                </Form.Item>
                <Form.Item label="Memo">
                    <Input.TextArea
                        aria-label="Adjustment memo"
                        rows={3}
                        value={form.data.description}
                        onChange={(event) => form.setData('description', event.target.value)}
                    />
                </Form.Item>
            </Form>
        </Modal>
    );
}
