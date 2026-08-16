import { Head, router, useForm } from '@inertiajs/react';
import type { ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, DatePicker, Form, Input, Modal } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';

interface SeasonRow {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

interface SeasonsIndexProps {
    seasons: Paginated<SeasonRow>;
    filters: { search?: string };
}

export default function SeasonsIndex({ seasons, filters }: SeasonsIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<SeasonRow | null>(null);

    const form = useForm({
        name: '',
        start_date: '',
        end_date: '',
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({ name: '', start_date: '', end_date: '' });
        setModalOpen(true);
    };

    const openEdit = (record: SeasonRow) => {
        setEditing(record);
        form.setData({
            name: record.name,
            start_date: record.start_date,
            end_date: record.end_date,
        });
        setModalOpen(true);
    };

    const submit = () => {
        if (editing) {
            form.put(`/admin/seasons/${editing.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/admin/seasons', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const columns: ProColumns<SeasonRow>[] = [
        { title: 'Name', dataIndex: 'name' },
        { title: 'Start', dataIndex: 'start_date' },
        { title: 'End', dataIndex: 'end_date' },
        {
            title: 'Actions',
            valueType: 'option',
            render: (_, record) => [
                <Button key="edit" type="link" onClick={() => openEdit(record)}>
                    Edit
                </Button>,
            ],
        },
    ];

    return (
        <AuthenticatedLayout title="Seasons">
            <Head title="Seasons" />
            <ProTable<SeasonRow>
                rowKey="id"
                columns={columns}
                dataSource={seasons.data}
                search={false}
                options={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={openCreate}>
                        New Season
                    </Button>,
                ]}
                pagination={{
                    showTotal: (total, range) => `${range[0]}-${range[1]} of ${total}`,
                    current: seasons.current_page,
                    pageSize: seasons.per_page,
                    total: seasons.total,
                    onChange: (page) =>
                        router.get('/admin/seasons', { ...filters, page }, { preserveState: true }),
                }}
                scroll={{ x: 'max-content' }}
            />

            <Modal
                title={editing ? 'Edit Season' : 'New Season'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={submit}
                confirmLoading={form.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Name" required>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="Start date" required>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={form.data.start_date ? dayjs(form.data.start_date) : null}
                            onChange={(d) =>
                                form.setData('start_date', d?.format('YYYY-MM-DD') ?? '')
                            }
                        />
                    </Form.Item>
                    <Form.Item label="End date" required>
                        <DatePicker
                            style={{ width: '100%' }}
                            value={form.data.end_date ? dayjs(form.data.end_date) : null}
                            onChange={(d) =>
                                form.setData('end_date', d?.format('YYYY-MM-DD') ?? '')
                            }
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </AuthenticatedLayout>
    );
}
