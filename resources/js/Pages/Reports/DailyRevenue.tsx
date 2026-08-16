import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Card, Col, DatePicker, Row, Statistic } from 'antd';
import dayjs from 'dayjs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface DailyRow {
    date: string;
    room: number;
    fb: number;
    spa: number;
    misc: number;
    total: number;
}

interface DailyRevenueProps {
    report: {
        by_department: Array<{ department: string; label: string; amount: number }>;
        by_payment_method: Array<{ method: string; label: string; amount: number }>;
        by_date: DailyRow[];
        totals: { revenue: number; payments: number };
    };
    filters: { from: string; to: string };
}

export default function DailyRevenue({ report, filters }: DailyRevenueProps) {
    const columns: ProColumns<DailyRow>[] = [
        { title: 'Date', dataIndex: 'date' },
        { title: 'Room', dataIndex: 'room', render: (v) => Number(v).toLocaleString('id-ID'), align: 'right' },
        { title: 'F&B', dataIndex: 'fb', render: (v) => Number(v).toLocaleString('id-ID'), align: 'right' },
        { title: 'Spa', dataIndex: 'spa', render: (v) => Number(v).toLocaleString('id-ID'), align: 'right' },
        { title: 'Misc', dataIndex: 'misc', render: (v) => Number(v).toLocaleString('id-ID'), align: 'right' },
        { title: 'Total', dataIndex: 'total', render: (v) => Number(v).toLocaleString('id-ID'), align: 'right' },
    ];

    const exportUrl = `/reports/daily-revenue?from=${filters.from}&to=${filters.to}&export=csv`;

    return (
        <AuthenticatedLayout title="Daily Revenue Report">
            <Head title="Daily Revenue" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 12, flexWrap: 'wrap', justifyContent: 'space-between' }}>
                <DatePicker.RangePicker
                    value={[dayjs(filters.from), dayjs(filters.to)]}
                    onChange={(dates) => router.get('/reports/daily-revenue', {
                        from: dates?.[0]?.format('YYYY-MM-DD'),
                        to: dates?.[1]?.format('YYYY-MM-DD'),
                    }, { preserveState: true })}
                />
                <Button href={exportUrl}>Export CSV</Button>
            </div>
            <Row gutter={16} style={{ marginBottom: 24 }}>
                <Col span={6}>
                    <Card><Statistic title="Total Revenue" value={report.totals.revenue} prefix="Rp" /></Card>
                </Col>
                <Col span={6}>
                    <Card><Statistic title="Total Payments" value={report.totals.payments} prefix="Rp" /></Card>
                </Col>
            </Row>
            <Row gutter={16} style={{ marginBottom: 24 }}>
                <Col span={12}>
                    <Card title="By Department" size="small">
                        {report.by_department.map((row) => (
                            <div key={row.department} style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
                                <span>{row.label}</span>
                                <strong>Rp {row.amount.toLocaleString('id-ID')}</strong>
                            </div>
                        ))}
                    </Card>
                </Col>
                <Col span={12}>
                    <Card title="By Payment Method" size="small">
                        {report.by_payment_method.length === 0 ? (
                            <span>No payments in period</span>
                        ) : (
                            report.by_payment_method.map((row) => (
                                <div key={row.method} style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
                                    <span>{row.label}</span>
                                    <strong>Rp {row.amount.toLocaleString('id-ID')}</strong>
                                </div>
                            ))
                        )}
                    </Card>
                </Col>
            </Row>
            <ProTable<DailyRow>
                rowKey="date"
                search={false}
                options={false}
                dataSource={report.by_date}
                columns={columns}
                pagination={false}
                scroll={{ x: 'max-content' }}
            />
        </AuthenticatedLayout>
    );
}
