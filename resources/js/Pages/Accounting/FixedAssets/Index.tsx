import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, DatePicker, Modal, Select } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface FixedAssetRow {
    id: number;
    asset_code: string | null;
    name: string;
    acquisition_date: string | null;
    acquisition_cost: number | null;
    accumulated_depreciation: number;
    net_book_value: number | null;
    depreciation_method: string | null;
    useful_life_years: number | null;
    last_depreciation_date: string | null;
    is_depreciable: boolean;
}

interface FixedAssetsIndexProps {
    assets: FixedAssetRow[];
    depreciationMethods: Array<{ value: string; label: string }>;
    expenseAccounts: Array<{ id: number; account_code: string; name: string }>;
    accumAccounts: Array<{ id: number; account_code: string; name: string }>;
}

const formatIdr = (n: number | null) => (n !== null ? `Rp ${n.toLocaleString('id-ID')}` : '—');

export default function FixedAssetsIndex({
    assets,
    depreciationMethods,
    expenseAccounts,
    accumAccounts,
}: FixedAssetsIndexProps) {
    const [editId, setEditId] = useState<number | null>(null);
    const [runOpen, setRunOpen] = useState(false);
    const { data, setData, put, processing, reset } = useForm({
        asset_code: '',
        acquisition_date: '',
        acquisition_cost: 0,
        residual_value: 0,
        useful_life_years: 5,
        depreciation_method: 'straight_line',
        chart_of_account_id: null as number | null,
        accumulated_depreciation_account_id: null as number | null,
    });
    const runForm = useForm({ as_of: dayjs().endOf('month').format('YYYY-MM-DD') });

    const openEdit = (asset: FixedAssetRow) => {
        setEditId(asset.id);
        setData({
            asset_code: asset.asset_code ?? '',
            acquisition_date: asset.acquisition_date ?? '',
            acquisition_cost: asset.acquisition_cost ?? 0,
            residual_value: 0,
            useful_life_years: asset.useful_life_years ?? 5,
            depreciation_method: 'straight_line',
            chart_of_account_id: null,
            accumulated_depreciation_account_id: null,
        });
    };

    const save = () => {
        if (!editId) return;
        put(`/accounting/fixed-assets/${editId}`, {
            onSuccess: () => {
                setEditId(null);
                reset();
            },
        });
    };

    const columns: ProColumns<FixedAssetRow>[] = [
        { title: 'Code', dataIndex: 'asset_code', width: 100 },
        { title: 'Name', dataIndex: 'name' },
        { title: 'Acquired', dataIndex: 'acquisition_date', width: 110 },
        { title: 'Cost', render: (_, r) => formatIdr(r.acquisition_cost), width: 130 },
        { title: 'Accum. Depr.', render: (_, r) => formatIdr(r.accumulated_depreciation), width: 130 },
        { title: 'NBV', render: (_, r) => formatIdr(r.net_book_value), width: 130 },
        { title: 'Method', dataIndex: 'depreciation_method', width: 120 },
        {
            title: 'Action',
            width: 80,
            render: (_, r) => <Button size="small" onClick={() => openEdit(r)}>Edit</Button>,
        },
    ];

    return (
        <AuthenticatedLayout title="Fixed Assets">
            <Head title="Fixed Assets" />
            <div style={{ marginBottom: 16 }}>
                <Button type="primary" onClick={() => setRunOpen(true)}>
                    Run Monthly Depreciation
                </Button>
            </div>
            <ProTable rowKey="id" search={false} options={false} dataSource={assets} columns={columns} pagination={{
                showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
            }} />
            <Modal title="Edit Fixed Asset Accounting" open={editId !== null} onCancel={() => setEditId(null)} onOk={save} confirmLoading={processing}>
                <Select
                    style={{ width: '100%', marginBottom: 8 }}
                    placeholder="Depreciation method"
                    value={data.depreciation_method}
                    options={depreciationMethods}
                    onChange={(v) => setData('depreciation_method', v)}
                />
                <Select
                    style={{ width: '100%', marginBottom: 8 }}
                    placeholder="Depreciation expense account"
                    value={data.chart_of_account_id}
                    options={expenseAccounts.map((a) => ({ value: a.id, label: `${a.account_code} - ${a.name}` }))}
                    onChange={(v) => setData('chart_of_account_id', v)}
                />
                <Select
                    style={{ width: '100%' }}
                    placeholder="Accumulated depreciation account"
                    value={data.accumulated_depreciation_account_id}
                    options={accumAccounts.map((a) => ({ value: a.id, label: `${a.account_code} - ${a.name}` }))}
                    onChange={(v) => setData('accumulated_depreciation_account_id', v)}
                />
            </Modal>
            <Modal
                title="Run Depreciation"
                open={runOpen}
                onCancel={() => setRunOpen(false)}
                onOk={() =>
                    runForm.post('/accounting/fixed-assets/depreciation/run', {
                        onSuccess: () => setRunOpen(false),
                    })
                }
                confirmLoading={runForm.processing}
            >
                <DatePicker
                    picker="month"
                    style={{ width: '100%' }}
                    value={dayjs(runForm.data.as_of)}
                    onChange={(d) => runForm.setData('as_of', d?.endOf('month').format('YYYY-MM-DD') ?? '')}
                />
            </Modal>
        </AuthenticatedLayout>
    );
}
