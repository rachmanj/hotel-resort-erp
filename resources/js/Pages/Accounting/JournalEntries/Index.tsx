import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Select, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface JournalEntryRow {
    id: number;
    journal_no: string;
    entry_date: string;
    description: string;
    status: string;
    status_label: string;
    created_by: string | null;
    approved_by: string | null;
    posted_at: string | null;
}

interface JournalEntriesIndexProps {
    entries: { data: JournalEntryRow[] };
    filters: Record<string, string>;
    statusOptions: Array<{ value: string; label: string }>;
}

const statusColors: Record<string, string> = {
    draft: 'default',
    submitted: 'processing',
    approved: 'success',
    posted: 'green',
    rejected: 'error',
};

export default function JournalEntriesIndex({ entries, filters, statusOptions }: JournalEntriesIndexProps) {
    const columns: ProColumns<JournalEntryRow>[] = [
        { title: 'Journal No', dataIndex: 'journal_no', width: 150 },
        { title: 'Date', dataIndex: 'entry_date', width: 120 },
        { title: 'Description', dataIndex: 'description' },
        {
            title: 'Status',
            width: 120,
            render: (_, r) => <Tag color={statusColors[r.status] ?? 'default'}>{r.status_label}</Tag>,
        },
        { title: 'Created By', dataIndex: 'created_by', width: 140 },
        {
            title: 'Action',
            width: 80,
            render: (_, r) => <Link href={`/accounting/journal-entries/${r.id}`}>View</Link>,
        },
    ];

    return (
        <AuthenticatedLayout title="Journal Entries">
            <Head title="Journal Entries" />
            <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'space-between' }}>
                <Select
                    allowClear
                    placeholder="Status"
                    style={{ width: 160 }}
                    value={filters.status}
                    options={statusOptions}
                    onChange={(v) => router.get('/accounting/journal-entries', { status: v }, { preserveState: true })}
                />
                <Link href="/accounting/journal-entries/create">
                    <Button type="primary">New Journal Entry</Button>
                </Link>
            </div>
            <ProTable rowKey="id" search={false} options={false} dataSource={entries.data} columns={columns} />
        </AuthenticatedLayout>
    );
}
