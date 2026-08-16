import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface PermissionRow {
    id: number;
    name: string;
    guard_name: string;
}

interface PermissionsIndexProps {
    permissions: Paginated<PermissionRow>;
    filters: { search?: string };
}

export default function PermissionsIndex({ permissions, filters }: PermissionsIndexProps) {
    const columns: ProColumns<PermissionRow>[] = [
        { title: 'Name', dataIndex: 'name', fieldProps: { placeholder: 'Permission name' } },
        { title: 'Guard Name', dataIndex: 'guard_name', fieldProps: { placeholder: 'Guard name' } },
        {
            title: 'Search',
            dataIndex: 'search',
            hideInTable: true,
            fieldProps: { placeholder: 'Permission name' },
        },
    ];

    return (
        <AuthenticatedLayout title="Permissions">
            <Head title="Permissions" />
            <ProTable<PermissionRow>
                rowKey="id"
                columns={columns}
                dataSource={permissions.data}
                search={{
                    searchText: 'Search',
                    resetText: 'Reset',
                    labelWidth: 'auto',
                    defaultCollapsed: false,
                }}
                form={{
                    initialValues: { search: filters.search },
                }}
                onSubmit={(params) =>
                    router.get(
                        '/admin/permissions',
                        { search: params.search || undefined },
                        { preserveState: true },
                    )
                }
                options={false}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: permissions.current_page,
                    pageSize: permissions.per_page,
                    total: permissions.total,
                    onChange: (page) =>
                        router.get(
                            '/admin/permissions',
                            { ...filters, page },
                            { preserveState: true },
                        ),
                }}
                scroll={{ x: 'max-content' }}
            />
        </AuthenticatedLayout>
    );
}
