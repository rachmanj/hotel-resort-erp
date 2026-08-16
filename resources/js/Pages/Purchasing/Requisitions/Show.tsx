import { Head, Link, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Card, Descriptions, Space, Tag } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface RequisitionItem {
    id: number;
    inventory_item: { id: number; name: string; unit: string } | null;
    quantity_requested: number;
}

interface RequisitionShowProps {
    requisition: {
        id: number;
        requisition_no: string;
        department: string;
        status: string;
        status_label: string;
        notes: string | null;
        requested_by: { id: number; name: string } | null;
        approved_by: { id: number; name: string } | null;
        items: RequisitionItem[];
        created_at: string;
    };
    canApprove: boolean;
}

const statusColors: Record<string, string> = {
    draft: 'default',
    pending_approval: 'orange',
    approved: 'green',
    rejected: 'red',
    converted: 'blue',
};

export default function RequisitionsShow({ requisition, canApprove }: RequisitionShowProps) {
    const columns: ProColumns<RequisitionItem>[] = [
        {
            title: 'Item Name',
            render: (_, r) => r.inventory_item?.name ?? '–',
        },
        {
            title: 'Unit',
            render: (_, r) => r.inventory_item?.unit ?? '–',
        },
        {
            title: 'Quantity Requested',
            dataIndex: 'quantity_requested',
        },
    ];

    return (
        <AuthenticatedLayout title={requisition.requisition_no}>
            <Head title={requisition.requisition_no} />
            <Space style={{ marginBottom: 16 }} wrap>
                <Link href="/purchasing/requisitions">
                    <Button>Back to list</Button>
                </Link>
                {requisition.status === 'draft' && (
                    <Button
                        type="primary"
                        onClick={() => router.post(`/purchasing/requisitions/${requisition.id}/submit`)}
                    >
                        Submit for Approval
                    </Button>
                )}
                {requisition.status === 'pending_approval' && canApprove && (
                    <Button
                        type="primary"
                        onClick={() => router.post(`/purchasing/requisitions/${requisition.id}/approve`)}
                    >
                        Approve
                    </Button>
                )}
            </Space>

            <Card>
                <Descriptions bordered column={{ xs: 1, sm: 2 }}>
                    <Descriptions.Item label="PR Number">{requisition.requisition_no}</Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag color={statusColors[requisition.status]}>{requisition.status_label}</Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="Department">{requisition.department}</Descriptions.Item>
                    <Descriptions.Item label="Created">{requisition.created_at}</Descriptions.Item>
                    <Descriptions.Item label="Requested By">
                        {requisition.requested_by?.name ?? '–'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Approved By">
                        {requisition.approved_by?.name ?? '–'}
                    </Descriptions.Item>
                    {requisition.notes && (
                        <Descriptions.Item label="Notes" span={2}>
                            {requisition.notes}
                        </Descriptions.Item>
                    )}
                </Descriptions>
            </Card>

            <Card title="Items" style={{ marginTop: 16 }}>
                <ProTable
                    rowKey="id"
                    search={false}
                    options={false}
                    pagination={false}
                    dataSource={requisition.items}
                    columns={columns}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
