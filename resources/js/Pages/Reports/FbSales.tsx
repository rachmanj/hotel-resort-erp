import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Card, Col, DatePicker, Row, Statistic } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface CategoryRow {
    category_id: number;
    category_name: string;
    quantity: number;
    amount: number;
}

interface ItemRow {
    item_id: number;
    item_name: string;
    category_name: string;
    quantity: number;
    amount: number;
}

interface ShiftRow {
    shift: string;
    label: string;
    quantity: number;
    amount: number;
}

interface FbSalesProps {
    report: {
        by_category: CategoryRow[];
        by_item: ItemRow[];
        by_shift: ShiftRow[];
        totals: { quantity: number; amount: number };
    };
    filters: { from: string; to: string };
}

export default function FbSales({ report, filters }: FbSalesProps) {
    const categoryColumns: ProColumns<CategoryRow>[] = [
        { title: 'Category', dataIndex: 'category_name' },
        { title: 'Quantity', dataIndex: 'quantity', align: 'right' },
        { title: 'Amount', dataIndex: 'amount', render: (v) => `Rp ${Number(v).toLocaleString('id-ID')}`, align: 'right' },
    ];

    const itemColumns: ProColumns<ItemRow>[] = [
        { title: 'Category', dataIndex: 'category_name' },
        { title: 'Item', dataIndex: 'item_name' },
        { title: 'Quantity', dataIndex: 'quantity', align: 'right' },
        { title: 'Amount', dataIndex: 'amount', render: (v) => `Rp ${Number(v).toLocaleString('id-ID')}`, align: 'right' },
    ];

    const shiftColumns: ProColumns<ShiftRow>[] = [
        { title: 'Shift', dataIndex: 'label' },
        { title: 'Quantity', dataIndex: 'quantity', align: 'right' },
        { title: 'Amount', dataIndex: 'amount', render: (v) => `Rp ${Number(v).toLocaleString('id-ID')}`, align: 'right' },
    ];

    const exportUrl = `/reports/fb-sales?from=${filters.from}&to=${filters.to}&export=csv`;

    return (
        <AuthenticatedLayout title="F&B Sales Report">
            <Head title="F&B Sales" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 12, flexWrap: 'wrap', justifyContent: 'space-between' }}>
                <DatePicker.RangePicker
                    value={[dayjs(filters.from), dayjs(filters.to)]}
                    onChange={(dates) => router.get('/reports/fb-sales', {
                        from: dates?.[0]?.format('YYYY-MM-DD'),
                        to: dates?.[1]?.format('YYYY-MM-DD'),
                    }, { preserveState: true })}
                />
                <Button href={exportUrl}>Export CSV</Button>
            </div>
            <Row gutter={16} style={{ marginBottom: 24 }}>
                <Col span={6}>
                    <Card><Statistic title="Total Quantity" value={report.totals.quantity} /></Card>
                </Col>
                <Col span={6}>
                    <Card><Statistic title="Total Sales" value={report.totals.amount} prefix="Rp" /></Card>
                </Col>
            </Row>
            <Row gutter={16}>
                <Col span={12}>
                    <Card title="By Category" style={{ marginBottom: 24 }}>
                        <ProTable<CategoryRow>
                            rowKey="category_id"
                            search={false}
                            options={false}
                            dataSource={report.by_category}
                            columns={categoryColumns}
                            pagination={false}
                            scroll={{ x: 'max-content' }}
                        />
                    </Card>
                </Col>
                <Col span={12}>
                    <Card title="By Shift" style={{ marginBottom: 24 }}>
                        <ProTable<ShiftRow>
                            rowKey="shift"
                            search={false}
                            options={false}
                            dataSource={report.by_shift}
                            columns={shiftColumns}
                            pagination={false}
                            scroll={{ x: 'max-content' }}
                        />
                    </Card>
                </Col>
            </Row>
            <Card title="By Item">
                <ProTable<ItemRow>
                    rowKey="item_id"
                    search={false}
                    options={false}
                    dataSource={report.by_item}
                    columns={itemColumns}
                    scroll={{ x: 'max-content' }}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
