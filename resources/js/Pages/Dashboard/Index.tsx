import { Head } from '@inertiajs/react';
import { Card, Col, Row, Statistic } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function DashboardIndex() {
    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />
            <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Statistic title="Occupancy" value={0} suffix="%" />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Statistic title="Revenue Today" value={0} prefix="Rp" />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Statistic title="Check-ins Today" value={0} />
                    </Card>
                </Col>
                <Col xs={24} sm={12} lg={6}>
                    <Card>
                        <Statistic title="Pending Tasks" value={0} />
                    </Card>
                </Col>
            </Row>
        </AuthenticatedLayout>
    );
}
