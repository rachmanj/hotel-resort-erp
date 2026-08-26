import { Head, router } from '@inertiajs/react';
import { Button, Descriptions, Table, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface JournalLine {
    id: number;
    account_code: string | null;
    account_name: string | null;
    department_name: string | null;
    description: string | null;
    debit: number;
    credit: number;
}

interface JournalEntryShowProps {
    entry: {
        id: number;
        journal_no: string;
        entry_date: string;
        description: string;
        status: string;
        status_label: string;
        created_by: string | null;
        approved_by: string | null;
        posted_at: string | null;
        lines: JournalLine[];
    };
}

export default function JournalEntryShow({ entry }: JournalEntryShowProps) {
    const totalDebit = entry.lines.reduce((s, l) => s + l.debit, 0);
    const totalCredit = entry.lines.reduce((s, l) => s + l.credit, 0);

    return (
        <AuthenticatedLayout title={`Journal ${entry.journal_no}`}>
            <Head title={entry.journal_no} />
            <Descriptions bordered column={2} style={{ marginBottom: 24 }}>
                <Descriptions.Item label="Journal No">{entry.journal_no}</Descriptions.Item>
                <Descriptions.Item label="Date">{entry.entry_date}</Descriptions.Item>
                <Descriptions.Item label="Status"><Tag>{entry.status_label}</Tag></Descriptions.Item>
                <Descriptions.Item label="Created By">{entry.created_by}</Descriptions.Item>
                <Descriptions.Item label="Description" span={2}>{entry.description}</Descriptions.Item>
            </Descriptions>

            <Table
                dataSource={entry.lines}
                rowKey="id"
                pagination={false}
                columns={[
                    { title: 'Code', dataIndex: 'account_code' },
                    { title: 'Account', dataIndex: 'account_name' },
                    { title: 'Department', dataIndex: 'department_name' },
                    { title: 'Description', dataIndex: 'description' },
                    { title: 'Debit', dataIndex: 'debit', render: (v: number) => v.toLocaleString('id-ID') },
                    { title: 'Credit', dataIndex: 'credit', render: (v: number) => v.toLocaleString('id-ID') },
                ]}
                footer={() => (
                    <div style={{ textAlign: 'right' }}>
                        Total Debit: {totalDebit.toLocaleString('id-ID')} | Total Credit: {totalCredit.toLocaleString('id-ID')}
                    </div>
                )}
            />

            <div style={{ marginTop: 16, display: 'flex', gap: 8 }}>
                {entry.status === 'draft' && (
                    <Button type="primary" onClick={() => router.post(`/accounting/journal-entries/${entry.id}/submit`)}>
                        Submit
                    </Button>
                )}
                {entry.status === 'submitted' && (
                    <Button type="primary" onClick={() => router.post(`/accounting/journal-entries/${entry.id}/approve`)}>
                        Approve & Post
                    </Button>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
