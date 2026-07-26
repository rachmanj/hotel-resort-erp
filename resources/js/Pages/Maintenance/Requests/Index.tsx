import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Select, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useAuth } from '@/hooks/useAuth';

interface RequestRow {
    id: number;
    description: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    room: { number: string } | null;
    reporter: { name: string } | null;
    assignee: { name: string } | null;
    created_at: string;
}

interface MaintenanceRequestsIndexProps {
    requests: { data: RequestRow[] };
    filters: Record<string, string>;
    statusOptions: Array<{ value: string; label: string }>;
    priorityOptions: Array<{ value: string; label: string }>;
}

const priorityColors: Record<string, string> = {
    low: 'default', medium: 'blue', high: 'orange', urgent: 'red',
};

export default function MaintenanceRequestsIndex({ requests, filters, statusOptions, priorityOptions }: MaintenanceRequestsIndexProps) {
    const { can } = useAuth();
    const columns: ProColumns<RequestRow>[] = [
        { title: 'Description', dataIndex: 'description', ellipsis: true },
        { title: 'Room', render: (_, r) => r.room?.number ?? '—' },
        { title: 'Priority', render: (_, r) => <Tag color={priorityColors[r.priority]}>{r.priority_label}</Tag> },
        { title: 'Status', render: (_, r) => <Tag>{r.status_label}</Tag> },
        { title: 'Reporter', render: (_, r) => r.reporter?.name },
        { title: 'Assigned', render: (_, r) => r.assignee?.name ?? '—' },
        { title: 'Created', dataIndex: 'created_at' },
        can('maintenance.manage') && {
            title: 'Actions',
            render: (_, r) => r.status !== 'resolved' && r.status !== 'closed' ? (
                <Button size="small" onClick={() => router.post(`/maintenance/requests/${r.id}/resolve`)}>Resolve</Button>
            ) : null,
        },
    ].filter(Boolean) as ProColumns<RequestRow>[];

    return (
        <AuthenticatedLayout title="Maintenance Requests">
            <Head title="Maintenance" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8 }}>
                <Select allowClear placeholder="Status" style={{ width: 140 }} value={filters.status} options={statusOptions}
                    onChange={(v) => router.get('/maintenance/requests', { ...filters, status: v }, { preserveState: true })} />
                <Select allowClear placeholder="Priority" style={{ width: 140 }} value={filters.priority} options={priorityOptions}
                    onChange={(v) => router.get('/maintenance/requests', { ...filters, priority: v }, { preserveState: true })} />
            </div>
            <ProTable rowKey="id" search={false} options={false} dataSource={requests.data} columns={columns} />
        </AuthenticatedLayout>
    );
}
