import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, InputNumber, Modal, Select, Tag } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface BudgetRow {
    id: number;
    department: string;
    department_label: string;
    fiscal_year: number;
    status: string;
    status_label: string;
    created_by: string | null;
    total_budgeted: number;
}

interface BudgetIndexProps {
    budgets: BudgetRow[];
    year: number;
    departments: Array<{ value: string; label: string }>;
}

const formatIdr = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

export default function BudgetIndex({ budgets, year, departments }: BudgetIndexProps) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        department: '',
        fiscal_year: year,
    });

    const columns: ProColumns<BudgetRow>[] = [
        { title: 'Department', dataIndex: 'department_label' },
        { title: 'Year', dataIndex: 'fiscal_year', width: 80 },
        { title: 'Total Budgeted', render: (_, r) => formatIdr(r.total_budgeted), width: 160 },
        { title: 'Status', render: (_, r) => <Tag>{r.status_label}</Tag>, width: 100 },
        { title: 'Created By', dataIndex: 'created_by', width: 140 },
        {
            title: 'Action',
            width: 80,
            render: (_, r) => <Link href={`/accounting/budgets/${r.id}/edit`}>Edit</Link>,
        },
    ];

    return (
        <AuthenticatedLayout title="Budgets">
            <Head title="Budgets" />
            <div style={{ marginBottom: 16, display: 'flex', gap: 8 }}>
                <Link href="/accounting/budgets/actual">
                    <Button>Budget vs Actual</Button>
                </Link>
                <Button type="primary" onClick={() => setOpen(true)}>
                    New Budget
                </Button>
                <InputNumber
                    value={year}
                    onChange={(v) => router.get('/accounting/budgets', { year: v }, { preserveState: true })}
                />
            </div>
            <ProTable rowKey="id" search={false} options={false} dataSource={budgets} columns={columns} />
            <Modal
                title="New Budget"
                open={open}
                onCancel={() => setOpen(false)}
                onOk={() =>
                    post('/accounting/budgets', {
                        onSuccess: () => {
                            setOpen(false);
                            reset();
                        },
                    })
                }
                confirmLoading={processing}
            >
                <Select
                    style={{ width: '100%', marginBottom: 12 }}
                    placeholder="Department"
                    value={data.department || undefined}
                    options={departments}
                    onChange={(v) => setData('department', v)}
                />
                <InputNumber
                    style={{ width: '100%' }}
                    value={data.fiscal_year}
                    onChange={(v) => setData('fiscal_year', v ?? year)}
                />
            </Modal>
        </AuthenticatedLayout>
    );
}
