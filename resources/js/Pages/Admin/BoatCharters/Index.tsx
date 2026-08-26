import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, DatePicker, Form, Input, InputNumber, Modal, Select } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface BoatCharterRow {
    id: number;
    trip_date: string;
    destination: string;
    charter_type: string;
    charter_type_label: string;
    price: number;
    quantity: number;
    guide_type: string;
    guide_type_label: string;
    guide_fee?: number | null;
    bbm_liters?: number | null;
    bbm_cost?: number | null;
    status: string;
    status_label: string;
    boat_unit_id?: number | null;
    boat_name?: string | null;
    dive_package_id?: number | null;
    dive_package_name?: string | null;
    reservation_id?: number | null;
    guest_name?: string | null;
    folio_id?: number | null;
    folio_no?: string | null;
    notes?: string | null;
}

interface IdOption {
    id: number;
    name?: string;
    code?: string;
}

interface ReservationOption {
    id: number;
    reservation_code: string;
    guest_name?: string | null;
}

interface FolioOption {
    id: number;
    folio_no: string;
    guest_name?: string | null;
}

interface OptionItem {
    value: string;
    label: string;
}

interface BoatChartersIndexProps {
    boatCharters: Paginated<BoatCharterRow>;
    filters: { search?: string };
    boatUnits: IdOption[];
    divePackages: IdOption[];
    reservations: ReservationOption[];
    folios: FolioOption[];
    charterTypes: OptionItem[];
    guideTypes: OptionItem[];
    statusOptions: OptionItem[];
}

export default function BoatChartersIndex({
    boatCharters,
    filters,
    boatUnits,
    divePackages,
    reservations,
    folios,
    charterTypes,
    guideTypes,
    statusOptions,
}: BoatChartersIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<BoatCharterRow | null>(null);

    const form = useForm({
        boat_unit_id: null as number | null,
        dive_package_id: null as number | null,
        reservation_id: null as number | null,
        folio_id: null as number | null,
        trip_date: '',
        destination: '',
        charter_type: charterTypes[0]?.value ?? 'diving',
        price: 0,
        quantity: 1,
        bbm_liters: null as number | null,
        bbm_cost: null as number | null,
        guide_type: guideTypes[0]?.value ?? 'employee',
        guide_fee: null as number | null,
        status: statusOptions[0]?.value ?? 'pending',
        notes: '',
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            boat_unit_id: null,
            dive_package_id: null,
            reservation_id: null,
            folio_id: null,
            trip_date: dayjs().format('YYYY-MM-DD'),
            destination: '',
            charter_type: charterTypes[0]?.value ?? 'diving',
            price: 0,
            quantity: 1,
            bbm_liters: null,
            bbm_cost: null,
            guide_type: guideTypes[0]?.value ?? 'employee',
            guide_fee: null,
            status: statusOptions[0]?.value ?? 'pending',
            notes: '',
        });
        setModalOpen(true);
    };

    const openEdit = (record: BoatCharterRow) => {
        setEditing(record);
        form.setData({
            boat_unit_id: record.boat_unit_id ?? null,
            dive_package_id: record.dive_package_id ?? null,
            reservation_id: record.reservation_id ?? null,
            folio_id: record.folio_id ?? null,
            trip_date: record.trip_date,
            destination: record.destination,
            charter_type: record.charter_type,
            price: record.price,
            quantity: record.quantity,
            bbm_liters: record.bbm_liters ?? null,
            bbm_cost: record.bbm_cost ?? null,
            guide_type: record.guide_type,
            guide_fee: record.guide_fee ?? null,
            status: record.status,
            notes: record.notes ?? '',
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/boat-charters/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/boat-charters', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const deleteCharter = (record: BoatCharterRow) => {
        Modal.confirm({
            title: 'Delete boat charter?',
            content: `Delete charter to "${record.destination}"? This cannot be undone.`,
            onOk: () => router.delete(`/admin/boat-charters/${record.id}`),
        });
    };

    const billCharter = (record: BoatCharterRow) => {
        Modal.confirm({
            title: 'Bill to folio?',
            content: `Post charge for "${record.destination}" (${record.quantity} pax) to guest folio?`,
            onOk: () => router.post(`/admin/boat-charters/${record.id}/bill`),
        });
    };

    const columns: ProColumns<BoatCharterRow>[] = [
        { title: 'Trip Date', dataIndex: 'trip_date', search: false },
        { title: 'Destination', dataIndex: 'destination', fieldProps: { placeholder: 'Destination' } },
        {
            title: 'Type',
            dataIndex: 'charter_type_label',
            search: false,
        },
        {
            title: 'Price',
            dataIndex: 'price',
            search: false,
            render: (_, record) => record.price.toLocaleString('en-US'),
        },
        { title: 'Pax', dataIndex: 'quantity', search: false },
        {
            title: 'Guide',
            dataIndex: 'guide_type_label',
            search: false,
        },
        {
            title: 'Guide Fee',
            dataIndex: 'guide_fee',
            search: false,
            render: (_, record) =>
                record.guide_fee != null ? record.guide_fee.toLocaleString('en-US') : '–',
        },
        {
            title: 'BBM Cost',
            dataIndex: 'bbm_cost',
            search: false,
            render: (_, record) =>
                record.bbm_cost != null ? record.bbm_cost.toLocaleString('en-US') : '–',
        },
        { title: 'Status', dataIndex: 'status_label', search: false },
        { title: 'Boat', dataIndex: 'boat_name', search: false, render: (value) => value ?? '–' },
        {
            title: 'Dive Package',
            dataIndex: 'dive_package_name',
            search: false,
            render: (value) => value ?? '–',
        },
        {
            title: 'Guest',
            dataIndex: 'guest_name',
            fieldProps: { placeholder: 'Guest name' },
            render: (value) => value ?? '–',
        },
        {
            title: 'Folio',
            dataIndex: 'folio_no',
            search: false,
            render: (value) => value ?? '–',
        },
        {
            title: 'Search',
            dataIndex: 'search',
            hideInTable: true,
            fieldProps: { placeholder: 'Destination or guest name' },
        },
        {
            title: 'Actions',
            valueType: 'option',
            fixed: 'right',
            render: (_, record) => [
                <Button key="edit" type="link" onClick={() => openEdit(record)}>
                    Edit
                </Button>,
                record.status !== 'billed' && (
                    <Button key="bill" type="link" onClick={() => billCharter(record)}>
                        Bill
                    </Button>
                ),
                <Button key="delete" type="link" danger onClick={() => deleteCharter(record)}>
                    Delete
                </Button>,
            ].filter(Boolean),
        },
    ];

    return (
        <AuthenticatedLayout title="Boat Charters">
            <Head title="Boat Charters" />
            <ProTable<BoatCharterRow>
                rowKey="id"
                columns={columns}
                dataSource={boatCharters.data}
                options={false}
                search={{
                    searchText: 'Search',
                    resetText: 'Reset',
                    labelWidth: 'auto',
                    defaultCollapsed: false,
                }}
                form={{
                    initialValues: { search: filters.search },
                }}
                onSubmit={(params) =>
                    router.get(
                        '/admin/boat-charters',
                        { search: params.search || undefined },
                        { preserveState: true },
                    )
                }
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Boat Charter
                    </Button>,
                ]}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: boatCharters.current_page,
                    pageSize: boatCharters.per_page,
                    total: boatCharters.total,
                    onChange: (page) =>
                        router.get(
                            '/admin/boat-charters',
                            { ...filters, page },
                            { preserveState: true },
                        ),
                }}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={editing ? 'Edit Boat Charter' : 'New Boat Charter'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
                width={720}
            >
                <Form layout="vertical">
                    <Form.Item label="Boat Unit" required>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            placeholder="Select boat unit"
                            value={form.data.boat_unit_id ?? undefined}
                            options={boatUnits.map((unit) => ({
                                value: unit.id,
                                label: `${unit.code} · ${unit.name}`,
                            }))}
                            onChange={(value) => form.setData('boat_unit_id', value)}
                        />
                    </Form.Item>
                    <Form.Item label="Dive Package">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="Select dive package"
                            value={form.data.dive_package_id ?? undefined}
                            options={divePackages.map((pkg) => ({
                                value: pkg.id,
                                label: `${pkg.code} · ${pkg.name}`,
                            }))}
                            onChange={(value) => form.setData('dive_package_id', value ?? null)}
                        />
                    </Form.Item>
                    <Form.Item label="Reservation">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="Select reservation"
                            value={form.data.reservation_id ?? undefined}
                            options={reservations.map((reservation) => ({
                                value: reservation.id,
                                label: `${reservation.reservation_code} · ${reservation.guest_name ?? 'Guest'}`,
                            }))}
                            onChange={(value) => form.setData('reservation_id', value ?? null)}
                        />
                    </Form.Item>
                    <Form.Item label="Folio">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="Select folio"
                            value={form.data.folio_id ?? undefined}
                            options={folios.map((folio) => ({
                                value: folio.id,
                                label: `${folio.folio_no} · ${folio.guest_name ?? 'Guest'}`,
                            }))}
                            onChange={(value) => form.setData('folio_id', value ?? null)}
                        />
                    </Form.Item>
                    <Form.Item label="Trip Date" required>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={form.data.trip_date ? dayjs(form.data.trip_date) : null}
                            onChange={(date) =>
                                form.setData('trip_date', date ? date.format('YYYY-MM-DD') : '')
                            }
                        />
                    </Form.Item>
                    <Form.Item label="Destination" required>
                        <Input
                            placeholder="Destination"
                            value={form.data.destination}
                            onChange={(e) => form.setData('destination', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Charter Type" required>
                        <Select
                            value={form.data.charter_type}
                            options={charterTypes}
                            onChange={(value) => form.setData('charter_type', value)}
                        />
                    </Form.Item>
                    <Form.Item label="Price" required>
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.price}
                            onChange={(v) => form.setData('price', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Quantity (Pax)" required>
                        <InputNumber
                            min={1}
                            style={{ width: '100%' }}
                            value={form.data.quantity}
                            onChange={(v) => form.setData('quantity', v ?? 1)}
                        />
                    </Form.Item>
                    <Form.Item label="BBM Liters">
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.bbm_liters ?? undefined}
                            onChange={(v) => form.setData('bbm_liters', v ?? null)}
                        />
                    </Form.Item>
                    <Form.Item label="BBM Cost">
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.bbm_cost ?? undefined}
                            onChange={(v) => form.setData('bbm_cost', v ?? null)}
                        />
                    </Form.Item>
                    <Form.Item label="Guide Type" required>
                        <Select
                            value={form.data.guide_type}
                            options={guideTypes}
                            onChange={(value) => form.setData('guide_type', value)}
                        />
                    </Form.Item>
                    <Form.Item label="Guide Fee">
                        <InputNumber
                            min={0}
                            style={{ width: '100%' }}
                            value={form.data.guide_fee ?? undefined}
                            onChange={(v) => form.setData('guide_fee', v ?? null)}
                        />
                    </Form.Item>
                    <Form.Item label="Status" required>
                        <Select
                            value={form.data.status}
                            options={statusOptions}
                            onChange={(value) => form.setData('status', value)}
                        />
                    </Form.Item>
                    <Form.Item label="Notes">
                        <Input.TextArea
                            rows={3}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
