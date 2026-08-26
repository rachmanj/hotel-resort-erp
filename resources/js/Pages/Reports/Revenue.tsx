import { Head, router } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Card, Col, DatePicker, Row, Statistic, Table, theme } from 'antd';
import dayjs from 'dayjs';
import { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTheme } from '@/hooks/useTheme';

interface CategoryRow {
    code: string;
    name: string;
    sort_order: number;
    amount: number;
}

interface ByDateRow {
    date: string;
    total: number;
    [key: string]: string | number;
}

interface RevenueProps {
    report: {
        categories: CategoryRow[];
        by_date: ByDateRow[];
        totals: {
            revenue: number;
            discount: number;
            ota_fee: number;
            agent_commission: number;
        };
    };
    filters: { month: string };
}

function formatCompactIdr(amount: number): string {
    const abs = Math.abs(amount);

    if (abs >= 1_000_000) {
        const juta = amount / 1_000_000;

        return `Rp ${Number.isInteger(juta) ? juta.toFixed(0) : juta.toFixed(1)} jt`;
    }

    if (abs >= 1_000) {
        const ribu = amount / 1_000;

        return `Rp ${Number.isInteger(ribu) ? ribu.toFixed(0) : ribu.toFixed(1)} rb`;
    }

    return `Rp ${amount.toLocaleString('id-ID')}`;
}

function formatIdr(amount: number): string {
    return `Rp ${amount.toLocaleString('id-ID')}`;
}

export default function Revenue({ report, filters }: RevenueProps) {
    const { isDark } = useTheme();
    const { token } = theme.useToken();

    const cardStyle = {
        background: isDark ? token.colorBgContainer : undefined,
        borderColor: isDark ? token.colorBorderSecondary : undefined,
    };

    const exportUrl = `/reports/revenue?month=${filters.month}&export=csv`;

    const categoryColumns = useMemo(
        () => [
            {
                title: 'Category',
                dataIndex: 'name',
                key: 'name',
            },
            {
                title: 'Amount',
                dataIndex: 'amount',
                key: 'amount',
                align: 'right' as const,
                render: (value: number) => formatIdr(value),
            },
        ],
        [],
    );

    const byDateColumns: ProColumns<ByDateRow>[] = useMemo(() => {
        const columns: ProColumns<ByDateRow>[] = [
            { title: 'Date', dataIndex: 'date', fixed: 'left', width: 120 },
        ];

        report.categories.forEach((category) => {
            columns.push({
                title: category.name,
                dataIndex: category.code,
                align: 'right',
                width: 140,
                render: (value) => formatIdr(Number(value ?? 0)),
            });
        });

        columns.push({
            title: 'Total',
            dataIndex: 'total',
            align: 'right',
            fixed: 'right',
            width: 140,
            render: (value) => formatIdr(Number(value ?? 0)),
        });

        return columns;
    }, [report.categories]);

    return (
        <AuthenticatedLayout title="Revenue Report">
            <Head title="Revenue Report" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 12, flexWrap: 'wrap', justifyContent: 'space-between' }}>
                <DatePicker
                    picker="month"
                    value={dayjs(`${filters.month}-01`)}
                    onChange={(date) => router.get('/reports/revenue', {
                        month: date?.format('YYYY-MM'),
                    }, { preserveState: true })}
                />
                <Button href={exportUrl}>Export CSV</Button>
            </div>

            <Row gutter={16} style={{ marginBottom: 24 }}>
                <Col xs={24} sm={12} lg={6}>
                    <Card style={cardStyle}>
                        <Statistic
                            title="Total Revenue"
                            value={formatCompactIdr(report.totals.revenue)}
                            valueStyle={{ color: token.colorSuccess }}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card style={cardStyle}>
                        <Statistic
                            title="Discount"
                            value={formatCompactIdr(report.totals.discount)}
                            valueStyle={{ color: token.colorWarning }}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card style={cardStyle}>
                        <Statistic
                            title="OTA Fee"
                            value={formatCompactIdr(report.totals.ota_fee)}
                            valueStyle={{ color: token.colorError }}
                        />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card style={cardStyle}>
                        <Statistic
                            title="Agent Commission"
                            value={formatCompactIdr(report.totals.agent_commission)}
                            valueStyle={{ color: token.colorError }}
                        />
                    </Card>
                </Col>
            </Row>

            <Card
                title="Revenue by Category"
                size="small"
                style={{ ...cardStyle, marginBottom: 24 }}
            >
                <Table<CategoryRow>
                    rowKey="code"
                    dataSource={report.categories}
                    columns={categoryColumns}
                    pagination={false}
                    size="small"
                />
            </Card>

            <ProTable<ByDateRow>
                headerTitle="Daily Breakdown"
                rowKey="date"
                search={false}
                options={false}
                dataSource={report.by_date}
                columns={byDateColumns}
                pagination={false}
                scroll={{ x: 'max-content' }}
            />
        </AuthenticatedLayout>
    );
}
