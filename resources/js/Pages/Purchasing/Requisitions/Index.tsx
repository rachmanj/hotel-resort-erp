import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Select, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface RequisitionRow {
    id: number;
    requisition_no: string;
    department: string;
    status: string;
    status_label: string;
    requested_by: { id: number; name: string } | null;
    created_at: string;
}

interface RequisitionsIndexProps {
    requisitions: { data: RequisitionRow[] };
    filters: Record<string, string>;
    statusOptions: Array<{ value: string; label: string }>;
}

const statusColors: Record<string, string> = {
    draft: 'default', pending_approval: 'orange', approved: 'green', rejected: 'red', converted: 'blue',
};

export default function RequisitionsIndex({ requisitions, filters, statusOptions }: RequisitionsIndexProps) {
    const columns: ProColumns<RequisitionRow>[] = [
        { title: 'PR #', dataIndex: 'requisition_no', render: (_, r) => <Link href={`/purchasing/requisitions/${r.id}`}>{r.requisition_no}</Link> },
        { title: 'Department', dataIndex: 'department' },
        { title: 'Status', render: (_, r) => <Tag color={statusColors[r.status]}>{r.status_label}</Tag> },
        { title: 'Requested By', render: (_, r) => r.requested_by?.name ?? '–' },
        { title: 'Created', dataIndex: 'created_at' },
    ];

    return (
        <AuthenticatedLayout title="Purchase Requisitions">
            <Head title="Requisitions" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8, alignItems: 'center' }}>
                <Link href="/purchasing/requisitions/create">
                    <Button type="primary">New Requisition</Button>
                </Link>
                <Select allowClear placeholder="Status" style={{ width: 160 }} value={filters.status} options={statusOptions}
                    onChange={(v) => router.get('/purchasing/requisitions', { status: v }, { preserveState: true })} />
            </div>
            <ProTable rowKey="id" search={false} options={false} pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }} dataSource={requisitions.data} columns={columns} scroll={{ x: 'max-content' }} />
        </AuthenticatedLayout>
    );
}
