import { Head } from '@inertiajs/react';
import { Alert, Card } from 'antd';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface ConsolidatedProps {
    message: string;
}

export default function Consolidated({ message }: ConsolidatedProps) {
    return (
        <AuthenticatedLayout title="Consolidated Report">
            <Head title="Consolidated Report" />
            <Card>
                <Alert type="info" message={message} showIcon />
            </Card>
        </AuthenticatedLayout>
    );
}
