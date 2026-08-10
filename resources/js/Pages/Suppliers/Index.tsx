import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Form, Input, Modal, Select, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface SupplierRow {
    id: number;
    name: string;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
}

interface SuppliersIndexProps {
    suppliers: { data: SupplierRow[] };
}

export default function SuppliersIndex({ suppliers }: SuppliersIndexProps) {
    const [creating, setCreating] = useState(false);
    const form = useForm({ name: '', contact_person: '', phone: '', email: '', address: '', is_active: true });

    const columns: ProColumns<SupplierRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Contact', dataIndex: 'contact_person' },
        { title: 'Phone', dataIndex: 'phone' },
        { title: 'Email', dataIndex: 'email' },
        { title: 'Active', dataIndex: 'is_active', render: (v) => <Tag color={v ? 'green' : 'red'}>{v ? 'Yes' : 'No'}</Tag> },
    ];

    return (
        <AuthenticatedLayout title="Suppliers">
            <Head title="Suppliers" />
            <div style={{ marginBottom: 16 }}><Button type="primary" onClick={() => setCreating(true)}>Add Supplier</Button></div>
            <ProTable rowKey="id" search={false} options={false} pagination={{ showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}` }} dataSource={suppliers.data} columns={columns} />
            <Modal title="Add Supplier" open={creating} onCancel={() => setCreating(false)}
                onOk={() => form.post('/purchasing/suppliers', { onSuccess: () => setCreating(false) })} confirmLoading={form.processing}>
                <Form layout="vertical">
                    <Form.Item label="Name"><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Form.Item>
                    <Form.Item label="Contact Person"><Input value={form.data.contact_person} onChange={(e) => form.setData('contact_person', e.target.value)} /></Form.Item>
                    <Form.Item label="Phone"><Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} /></Form.Item>
                    <Form.Item label="Email"><Input value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
