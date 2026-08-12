import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { DatePicker, Select, Tag } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface ActivityLogRow {
    id: number;
    created_at: string;
    user: { id: number; name: string } | null;
    event: string;
    subject_type: string;
    description: string;
    properties: Record<string, unknown> | null;
}

interface ActivityLogsIndexProps {
    logs: Paginated<ActivityLogRow>;
    filters: {
        date_from?: string;
        date_to?: string;
        user_id?: string;
        event?: string;
        subject_type?: string;
    };
    users: Array<{ id: number; name: string }>;
    events: string[];
    subjectTypes: Array<{ value: string; label: string }>;
}

const eventColors: Record<string, string> = {
    created: 'green',
    updated: 'blue',
    deleted: 'red',
    cancelled: 'orange',
    checked_in: 'cyan',
    checked_out: 'purple',
};

export default function ActivityLogsIndex({
    logs,
    filters,
    users,
    events,
    subjectTypes,
}: ActivityLogsIndexProps) {
    const applyFilters = (next: Partial<ActivityLogsIndexProps['filters']>) => {
        router.get(
            '/admin/activity-logs',
            { ...filters, ...next, page: undefined },
            { preserveState: true },
        );
    };

    const columns: ProColumns<ActivityLogRow>[] = [
        {
            title: 'Date/Time',
            dataIndex: 'created_at',
            width: 170,
            render: (_, row) => dayjs(row.created_at).format('YYYY-MM-DD HH:mm'),
        },
        {
            title: 'User',
            dataIndex: ['user', 'name'],
            width: 140,
            render: (_, row) => row.user?.name ?? '—',
        },
        {
            title: 'Action',
            dataIndex: 'event',
            width: 110,
            render: (_, row) => (
                <Tag color={eventColors[row.event] ?? 'default'}>
                    {row.event.replace(/_/g, ' ')}
                </Tag>
            ),
        },
        {
            title: 'Subject Type',
            dataIndex: 'subject_type',
            width: 140,
        },
        {
            title: 'Description',
            dataIndex: 'description',
            ellipsis: true,
        },
        {
            title: 'Properties',
            dataIndex: 'properties',
            width: 200,
            ellipsis: true,
            render: (_, row) =>
                row.properties ? JSON.stringify(row.properties) : '—',
        },
    ];

    const userOptions = users.map((u) => ({ value: u.id, label: u.name }));
    const eventOptions = events.map((e) => ({
        value: e,
        label: e.replace(/_/g, ' '),
    }));

    return (
        <AuthenticatedLayout title="Activity Logs">
            <Head title="Activity Logs" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                <DatePicker.RangePicker
                    value={
                        filters.date_from && filters.date_to
                            ? [dayjs(filters.date_from), dayjs(filters.date_to)]
                            : undefined
                    }
                    onChange={(dates) =>
                        applyFilters({
                            date_from: dates?.[0]?.format('YYYY-MM-DD'),
                            date_to: dates?.[1]?.format('YYYY-MM-DD'),
                        })
                    }
                />
                <Select
                    allowClear
                    showSearch
                    placeholder="Filter by user"
                    style={{ width: 200 }}
                    value={filters.user_id ? Number(filters.user_id) : undefined}
                    options={userOptions}
                    optionFilterProp="label"
                    onChange={(value) =>
                        applyFilters({ user_id: value ? String(value) : undefined })
                    }
                />
                <Select
                    allowClear
                    placeholder="Filter by action"
                    style={{ width: 160 }}
                    value={filters.event}
                    options={eventOptions}
                    onChange={(value) => applyFilters({ event: value ?? undefined })}
                />
                <Select
                    allowClear
                    showSearch
                    placeholder="Filter by subject type"
                    style={{ width: 200 }}
                    value={filters.subject_type}
                    options={subjectTypes}
                    optionFilterProp="label"
                    onChange={(value) =>
                        applyFilters({ subject_type: value ?? undefined })
                    }
                />
            </div>
            <ProTable<ActivityLogRow>
                rowKey="id"
                columns={columns}
                dataSource={logs.data}
                search={false}
                options={false}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: logs.current_page,
                    pageSize: logs.per_page,
                    total: logs.total,
                    onChange: (page) =>
                        router.get(
                            '/admin/activity-logs',
                            { ...filters, page },
                            { preserveState: true },
                        ),
                }}
            />
        </AuthenticatedLayout>
    );
}
