import { Head, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Card, DatePicker, Form, InputNumber, Modal } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface ExchangeRate {
    id: number;
    rate_to_base: string;
    effective_date: string;
}

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string;
    exchange_rate_to_base: string;
    effective_date: string;
    is_active: boolean;
    exchange_rates: ExchangeRate[];
}

interface CurrenciesIndexProps {
    currencies: Currency[];
}

export default function CurrenciesIndex({ currencies }: CurrenciesIndexProps) {
    const [selectedCurrency, setSelectedCurrency] = useState<Currency | null>(null);
    const [modalOpen, setModalOpen] = useState(false);

    const form = useForm({
        rate_to_base: 0,
        effective_date: dayjs().format('YYYY-MM-DD'),
    });

    const openRateModal = (currency: Currency) => {
        setSelectedCurrency(currency);
        form.setData({
            rate_to_base: Number(currency.exchange_rate_to_base),
            effective_date: dayjs().format('YYYY-MM-DD'),
        });
        setModalOpen(true);
    };

    const submitRate = () => {
        if (!selectedCurrency) {
            return;
        }

        form.post(`/admin/currencies/${selectedCurrency.id}/exchange-rates`, {
            onSuccess: () => setModalOpen(false),
        });
    };

    const columns: ProColumns<Currency>[] = [
        { title: 'Code', dataIndex: 'code' },
        { title: 'Name', dataIndex: 'name' },
        { title: 'Symbol', dataIndex: 'symbol' },
        {
            title: 'Current Rate (to IDR)',
            dataIndex: 'exchange_rate_to_base',
            render: (_, record) => Number(record.exchange_rate_to_base).toLocaleString('id-ID'),
        },
        {
            title: 'Effective Date',
            dataIndex: 'effective_date',
            render: (_, record) => dayjs(record.effective_date).locale('en').format('DD MMM YYYY'),
        },
        {
            title: 'Actions',
            valueType: 'option',
            render: (_, record) => [
                <Button key="rate" type="link" onClick={() => openRateModal(record)}>
                    Add Rate
                </Button>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Currencies">
            <Head title="Currencies" />
            <ProTable<Currency>
                rowKey="id"
                columns={columns}
                dataSource={currencies}
                search={false}
                options={false}
                pagination={false}
            />

            {currencies.map((currency) => (
                <Card
                    key={currency.id}
                    title={`${currency.code} — Rate History`}
                    style={{ marginTop: 16 }}
                    size="small"
                >
                    <ProTable<ExchangeRate>
                        rowKey="id"
                        search={false}
                        pagination={false}
                        toolBarRender={false}
                        columns={[
                            {
                                title: 'Rate',
                                dataIndex: 'rate_to_base',
                                render: (_, record) =>
                                    Number(record.rate_to_base).toLocaleString('id-ID'),
                            },
                            {
                                title: 'Effective Date',
                                dataIndex: 'effective_date',
                                render: (_, record) =>
                                    dayjs(record.effective_date).locale('en').format('DD MMM YYYY'),
                            },
                        ]}
                        dataSource={currency.exchange_rates}
                    />
                </Card>
            ))}

            <Modal
                title={selectedCurrency ? `New Rate — ${selectedCurrency.code}` : 'New Rate'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submitRate}
                confirmLoading={form.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Rate to Base (IDR per 1 unit)" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.rate_to_base}
                            onChange={(v) => form.setData('rate_to_base', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Effective Date" required>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={dayjs(form.data.effective_date)}
                            onChange={(date) =>
                                form.setData(
                                    'effective_date',
                                    date ? date.format('YYYY-MM-DD') : dayjs().format('YYYY-MM-DD'),
                                )
                            }
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
