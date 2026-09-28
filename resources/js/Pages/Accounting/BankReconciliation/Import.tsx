import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Alert,
    Button,
    Card,
    Descriptions,
    Modal,
    Select,
    Space,
    Table,
    Tag,
    Typography,
    Upload,
    theme,
} from 'antd';
import type { UploadFile } from 'antd/es/upload/interface';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCompactIdr, formatFullIdr } from './formatMoney';

interface ProfileOption {
    code: string;
    label: string;
}

interface ReconciliationSummary {
    id: number;
    bank_name: string | null;
    account_no: string | null;
    period_end_date: string;
    statement_lines_count: number;
    is_editable: boolean;
}

interface PreviewLine {
    posting_date: string;
    description: string;
    debit: number;
    credit: number;
    balance: number | null;
}

interface PreviewPayload {
    validation_passed?: boolean;
    validation_message?: string;
    statement?: {
        profile_code: string;
        account_number: string;
        period_start: string;
        period_end: string;
        opening_balance: number;
        closing_balance: number;
        total_debit: number;
        total_credit: number;
        debit_count: number;
        credit_count: number;
        lines: PreviewLine[];
        unparsed_rows: { count: number; reasons: string[] };
    };
}

interface ImportProps {
    reconciliation: ReconciliationSummary;
    profileOptions: ProfileOption[];
}

export default function Import({ reconciliation, profileOptions }: ImportProps) {
    const { token } = theme.useToken();
    const flash = (usePage().props.flash as { success?: string; error?: string }) ?? {};

    const [fileList, setFileList] = useState<UploadFile[]>([]);
    const [profileCode, setProfileCode] = useState<string | undefined>();
    const [preview, setPreview] = useState<PreviewPayload | null>(null);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [importing, setImporting] = useState(false);

    const validationPassed = preview?.validation_passed === true && preview?.statement !== undefined;

    const previewRows = useMemo(() => (preview?.statement?.lines ?? []).slice(0, 20), [preview]);

    const runPreview = async () => {
        const file = fileList[0]?.originFileObj;
        if (!file) {
            setPreviewError('Choose a statement file first.');

            return;
        }

        setPreviewing(true);
        setPreviewError(null);
        setPreview(null);

        const body = new FormData();
        body.append('file', file);
        if (profileCode) {
            body.append('profile_code', profileCode);
        }

        try {
            const response = await fetch(
                `/accounting/bank-reconciliation/${reconciliation.id}/import-preview`,
                {
                    method: 'POST',
                    body,
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN':
                            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    },
                    credentials: 'same-origin',
                },
            );

            const data = (await response.json()) as PreviewPayload;

            if (!response.ok) {
                setPreviewError(data.validation_message ?? 'Preview failed.');
                setPreview({ validation_passed: false, validation_message: data.validation_message });

                return;
            }

            setPreview({ ...data, validation_passed: true });
        } catch {
            setPreviewError('Could not reach the server. Try again.');
        } finally {
            setPreviewing(false);
        }
    };

    const confirmImport = () => {
        const file = fileList[0]?.originFileObj;
        if (!file || !validationPassed) {
            return;
        }

        Modal.confirm({
            title: 'Replace statement lines?',
            content:
                'Importing will replace all statement lines in this session (carried-forward lines are kept). Continue?',
            okText: 'Import',
            onOk: () => {
                setImporting(true);
                router.post(
                    `/accounting/bank-reconciliation/${reconciliation.id}/import-file`,
                    {
                        file,
                        profile_code: profileCode,
                        replace: reconciliation.statement_lines_count > 0,
                    },
                    {
                        forceFormData: true,
                        onFinish: () => setImporting(false),
                    },
                );
            },
        });
    };

    const columns = [
        { title: 'Date', dataIndex: 'posting_date', width: 110 },
        {
            title: 'Description',
            dataIndex: 'description',
            ellipsis: true,
        },
        {
            title: 'Debit',
            dataIndex: 'debit',
            width: 120,
            align: 'right' as const,
            render: (value: number) => (value > 0 ? formatCompactIdr(value) : '—'),
        },
        {
            title: 'Credit',
            dataIndex: 'credit',
            width: 120,
            align: 'right' as const,
            render: (value: number) => (value > 0 ? formatCompactIdr(value) : '—'),
        },
        {
            title: 'Balance',
            dataIndex: 'balance',
            width: 130,
            align: 'right' as const,
            render: (value: number | null) => (value !== null ? formatCompactIdr(value) : '—'),
        },
    ];

    return (
        <AuthenticatedLayout title="Import bank statement">
            <Head title="Import bank statement" />
            <Space direction="vertical" size="middle" style={{ width: '100%' }}>
                {flash.error && <Alert type="error" showIcon message={flash.error} />}
                {flash.success && <Alert type="success" showIcon message={flash.success} />}

                <Card size="small">
                    <Space wrap>
                        <Typography.Text>
                            {reconciliation.bank_name} · {reconciliation.account_no} · period ending{' '}
                            {reconciliation.period_end_date}
                        </Typography.Text>
                        <Link href={`/accounting/bank-reconciliation/${reconciliation.id}/reconcile`}>
                            Back to reconcile
                        </Link>
                    </Space>
                </Card>

                {!reconciliation.is_editable && (
                    <Alert
                        type="warning"
                        showIcon
                        message="This reconciliation is locked. Statement import is not available."
                    />
                )}

                <Card title="Upload statement file">
                    <Typography.Paragraph type="secondary">
                        Bank statement PDF exports are supported for import. CSV and Excel export formats will be
                        supported in a later release.
                    </Typography.Paragraph>
                    <Space direction="vertical" style={{ width: '100%' }} size="middle">
                        <Upload
                            accept=".pdf"
                            maxCount={1}
                            beforeUpload={() => false}
                            fileList={fileList}
                            onChange={({ fileList: next }) => {
                                setFileList(next);
                                setPreview(null);
                                setPreviewError(null);
                            }}
                            disabled={!reconciliation.is_editable}
                        >
                            <Button disabled={!reconciliation.is_editable}>Select PDF file</Button>
                        </Upload>

                        <Select
                            allowClear
                            placeholder="Auto-detect bank profile"
                            style={{ maxWidth: 360 }}
                            options={profileOptions.map((option) => ({
                                value: option.code,
                                label: option.label,
                            }))}
                            value={profileCode}
                            onChange={(value) => setProfileCode(value)}
                            disabled={!reconciliation.is_editable}
                        />

                        <Space>
                            <Button
                                type="primary"
                                onClick={runPreview}
                                loading={previewing}
                                disabled={!reconciliation.is_editable || fileList.length === 0}
                            >
                                Preview
                            </Button>
                            <Button
                                type="primary"
                                disabled={!reconciliation.is_editable || !validationPassed}
                                loading={importing}
                                onClick={confirmImport}
                            >
                                Import
                            </Button>
                        </Space>
                    </Space>
                </Card>

                {previewError && !preview?.statement && (
                    <Alert type="error" showIcon message={previewError} />
                )}

                {preview?.statement && (
                    <Card title="Preview">
                        <Space direction="vertical" style={{ width: '100%' }} size="middle">
                            <Descriptions size="small" column={2} bordered>
                                <Descriptions.Item label="Detected account">
                                    {preview.statement.account_number || '—'}
                                </Descriptions.Item>
                                <Descriptions.Item label="Period">
                                    {preview.statement.period_start} — {preview.statement.period_end}
                                </Descriptions.Item>
                                <Descriptions.Item label="Opening">
                                    {formatFullIdr(preview.statement.opening_balance)}
                                </Descriptions.Item>
                                <Descriptions.Item label="Closing">
                                    {formatFullIdr(preview.statement.closing_balance)}
                                </Descriptions.Item>
                                <Descriptions.Item label="Total debit">
                                    {formatFullIdr(preview.statement.total_debit)} (
                                    {preview.statement.debit_count} lines)
                                </Descriptions.Item>
                                <Descriptions.Item label="Total credit">
                                    {formatFullIdr(preview.statement.total_credit)} (
                                    {preview.statement.credit_count} lines)
                                </Descriptions.Item>
                                <Descriptions.Item label="Profile">
                                    {preview.statement.profile_code}
                                </Descriptions.Item>
                                <Descriptions.Item label="Validation">
                                    {validationPassed ? (
                                        <Tag color="success">Passed</Tag>
                                    ) : (
                                        <Tag color="error">Failed</Tag>
                                    )}
                                    {!validationPassed && (
                                        <Typography.Text type="danger" style={{ marginLeft: token.marginXS }}>
                                            {preview.validation_message}
                                        </Typography.Text>
                                    )}
                                </Descriptions.Item>
                            </Descriptions>

                            {preview.statement.lines.length === 0 ? (
                                <Typography.Text type="secondary">No rows parsed.</Typography.Text>
                            ) : (
                                <Table
                                    size="small"
                                    rowKey={(row) => `${row.posting_date}-${row.description}`}
                                    columns={columns}
                                    dataSource={previewRows}
                                    pagination={false}
                                    scroll={{ x: true }}
                                />
                            )}
                        </Space>
                    </Card>
                )}
            </Space>
        </AuthenticatedLayout>
    );
}
