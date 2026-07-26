import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, InputNumber, Select, Table } from 'antd';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface BudgetLine {
    id?: number;
    chart_of_account_id: number;
    account_code: string | null;
    account_name: string | null;
    month: number;
    budgeted_amount: number;
}

interface BudgetEditProps {
    budget: {
        id: number;
        department_label: string;
        fiscal_year: number;
        status: string;
        status_label: string;
        lines: BudgetLine[];
    };
    accounts: Array<{ id: number; account_code: string; name: string }>;
}

export default function BudgetEdit({ budget, accounts }: BudgetEditProps) {
    const [lines, setLines] = useState<BudgetLine[]>(
        budget.lines.length > 0
            ? budget.lines
            : [{ chart_of_account_id: accounts[0]?.id ?? 0, account_code: null, account_name: null, month: 1, budgeted_amount: 0 }],
    );

    const { post, processing } = useForm({});

    const save = () => {
        router.put(`/accounting/budgets/${budget.id}/lines`, { lines });
    };

    const canApprove = budget.status === 'draft';

    return (
        <AuthenticatedLayout title={`Budget: ${budget.department_label} ${budget.fiscal_year}`}>
            <Head title="Edit Budget" />
            <Link href="/accounting/budgets" style={{ marginBottom: 16, display: 'inline-block' }}>
                ← Back
            </Link>
            <div style={{ marginBottom: 16, display: 'flex', gap: 8 }}>
                <Button type="primary" loading={processing} onClick={save}>
                    Save Lines
                </Button>
                {canApprove && (
                    <Button onClick={() => router.post(`/accounting/budgets/${budget.id}/approve`)}>
                        Approve Budget
                    </Button>
                )}
            </div>
            <Table
                rowKey={(_, i) => String(i)}
                dataSource={lines}
                pagination={false}
                columns={[
                    {
                        title: 'Account',
                        render: (_, r, i) => (
                            <Select
                                style={{ width: 280 }}
                                showSearch
                                optionFilterProp="label"
                                value={r.chart_of_account_id}
                                options={accounts.map((a) => ({
                                    value: a.id,
                                    label: `${a.account_code} - ${a.name}`,
                                }))}
                                onChange={(v) => {
                                    const next = [...lines];
                                    next[i].chart_of_account_id = v;
                                    setLines(next);
                                }}
                            />
                        ),
                    },
                    {
                        title: 'Month',
                        width: 100,
                        render: (_, r, i) => (
                            <InputNumber
                                min={1}
                                max={12}
                                value={r.month}
                                onChange={(v) => {
                                    const next = [...lines];
                                    next[i].month = v ?? 1;
                                    setLines(next);
                                }}
                            />
                        ),
                    },
                    {
                        title: 'Amount',
                        width: 160,
                        render: (_, r, i) => (
                            <InputNumber
                                min={0}
                                value={r.budgeted_amount}
                                onChange={(v) => {
                                    const next = [...lines];
                                    next[i].budgeted_amount = v ?? 0;
                                    setLines(next);
                                }}
                            />
                        ),
                    },
                ]}
                footer={() => (
                    <Button
                        type="dashed"
                        onClick={() =>
                            setLines([
                                ...lines,
                                {
                                    chart_of_account_id: accounts[0]?.id ?? 0,
                                    account_code: null,
                                    account_name: null,
                                    month: 1,
                                    budgeted_amount: 0,
                                },
                            ])
                        }
                    >
                        Add Line
                    </Button>
                )}
            />
        </AuthenticatedLayout>
    );
}
