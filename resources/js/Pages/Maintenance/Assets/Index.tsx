import { Head } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface AssetRow {
    id: number;
    name: string;
    asset_type: string;
    asset_type_label: string;
    location: string | null;
    room: { number: string } | null;
    status: string;
    status_label: string;
    warranty_until: string | null;
}

interface AssetsIndexProps {
    assets: { data: AssetRow[] };
}

export default function AssetsIndex({ assets }: AssetsIndexProps) {
    const columns: ProColumns<AssetRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Type', dataIndex: 'asset_type_label' },
        { title: 'Location', dataIndex: 'location', render: (v, r) => v ?? r.room?.number ?? '–' },
        { title: 'Status', render: (_, r) => <Tag>{r.status_label}</Tag> },
        { title: 'Warranty Until', dataIndex: 'warranty_until', render: (v) => v ?? '–' },
    ];

    return (
        <AuthenticatedLayout title="Assets">
            <Head title="Assets" />
            <ProTable rowKey="id" search={false} options={false} pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }} dataSource={assets.data} columns={columns} scroll={{ x: 'max-content' }} />
        </AuthenticatedLayout>
    );
}
