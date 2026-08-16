import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Card, DatePicker, Form, Input, Select, Space, Table } from 'antd';
import dayjs from 'dayjs';
import { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface ApprovedRequisitionItem {
    id?: number;
    inventory_item_id?: number;
    quantity_requested?: number;
    inventory_item: { id: number; name: string; unit?: string } | null;
}

interface ApprovedRequisition {
    id: number;
    requisition_no: string;
    department: string;
    items: ApprovedRequisitionItem[];
}

interface OrdersCreateProps {
    suppliers: Array<{ id: number; name: string }>;
    approvedRequisitions: ApprovedRequisition[];
}

export default function OrdersCreate({ suppliers, approvedRequisitions }: OrdersCreateProps) {
    const form = useForm({
        purchase_requisition_id: null as number | null,
        supplier_id: null as number | null,
        expected_at: null as string | null,
        notes: '',
    });

    const selectedRequisition = useMemo(
        () => approvedRequisitions.find((r) => r.id === form.data.purchase_requisition_id),
        [approvedRequisitions, form.data.purchase_requisition_id],
    );

    const requisitionOptions = approvedRequisitions.map((r) => ({
        value: r.id,
        label: `${r.requisition_no} · ${r.department}`,
    }));

    const supplierOptions = suppliers.map((s) => ({ value: s.id, label: s.name }));

    const submit = () => {
        form.post('/purchasing/orders');
    };

    return (
        <AuthenticatedLayout title="New Purchase Order">
            <Head title="New Purchase Order" />
            <Link href="/purchasing/orders" style={{ marginBottom: 16, display: 'inline-block' }}>
                ← Back to list
            </Link>
            <Card>
                <Form layout="vertical">
                    <Space wrap style={{ width: '100%' }}>
                        <Form.Item label="Purchase Requisition" required style={{ minWidth: 280 }}>
                            <Select
                                showSearch
                                optionFilterProp="label"
                                placeholder="Select approved requisition"
                                value={form.data.purchase_requisition_id}
                                options={requisitionOptions}
                                onChange={(v) => form.setData('purchase_requisition_id', v)}
                            />
                            {form.errors.purchase_requisition_id && (
                                <div style={{ color: 'red' }}>{form.errors.purchase_requisition_id}</div>
                            )}
                        </Form.Item>
                        <Form.Item label="Supplier" required style={{ minWidth: 220 }}>
                            <Select
                                showSearch
                                optionFilterProp="label"
                                placeholder="Select supplier"
                                value={form.data.supplier_id}
                                options={supplierOptions}
                                onChange={(v) => form.setData('supplier_id', v)}
                            />
                            {form.errors.supplier_id && (
                                <div style={{ color: 'red' }}>{form.errors.supplier_id}</div>
                            )}
                        </Form.Item>
                        <Form.Item label="Expected Delivery Date" style={{ minWidth: 200 }}>
                            <DatePicker
                                style={{ width: '100%' }}
                                value={form.data.expected_at ? dayjs(form.data.expected_at) : null}
                                onChange={(d) =>
                                    form.setData('expected_at', d ? d.format('YYYY-MM-DD') : null)
                                }
                            />
                        </Form.Item>
                    </Space>
                    <Form.Item label="Notes">
                        <Input.TextArea
                            rows={3}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                    </Form.Item>
                </Form>

                {selectedRequisition && (
                    <Card type="inner" title={`Requisition Items · ${selectedRequisition.requisition_no}`}>
                        <Table
                            rowKey={(r) => r.id ?? r.inventory_item?.id ?? Math.random()}
                            pagination={false}
                            dataSource={selectedRequisition.items}
                            columns={[
                                {
                                    title: 'Item',
                                    render: (_, r) => r.inventory_item?.name ?? '–',
                                },
                                {
                                    title: 'Unit',
                                    render: (_, r) => r.inventory_item?.unit ?? '–',
                                },
                                {
                                    title: 'Qty Requested',
                                    render: (_, r) => r.quantity_requested ?? '–',
                                },
                            ]}
                        />
                    </Card>
                )}

                <Space style={{ marginTop: 16 }}>
                    <Button type="primary" loading={form.processing} onClick={submit}>
                        Create Purchase Order
                    </Button>
                    <Link href="/purchasing/orders">
                        <Button>Cancel</Button>
                    </Link>
                </Space>
            </Card>
        </AuthenticatedLayout>
    );
}
