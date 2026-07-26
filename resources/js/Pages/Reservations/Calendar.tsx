import { Head, Link } from '@inertiajs/react';
import { Button, Space } from 'antd';
import dayjs from 'dayjs';
import { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface RoomRow {
    id: number;
    number: string;
    room_type?: { id: number; name: string; code: string };
    floor?: { id: number; name: string };
}

interface CalendarReservation {
    id: number;
    reservation_code: string;
    room_id: number;
    guest_name?: string;
    status: string;
    status_color: string;
    arrival_date: string;
    departure_date: string;
}

interface DateColumn {
    date: string;
    label: string;
}

interface CalendarProps {
    rooms: RoomRow[];
    reservations: CalendarReservation[];
    dateColumns: DateColumn[];
    startDate: string;
    days: number;
}

const CELL_WIDTH = 72;
const ROW_HEIGHT = 44;
const LABEL_WIDTH = 120;

export default function ReservationCalendar({
    rooms,
    reservations,
    dateColumns,
    startDate,
    days,
}: CalendarProps) {
    const start = dayjs(startDate);
    const gridWidth = days * CELL_WIDTH;

    const blocksByRoom = useMemo(() => {
        const map = new Map<number, CalendarReservation[]>();
        for (const res of reservations) {
            const list = map.get(res.room_id) ?? [];
            list.push(res);
            map.set(res.room_id, list);
        }
        return map;
    }, [reservations]);

    const getBlockStyle = (res: CalendarReservation) => {
        const arrival = dayjs(res.arrival_date);
        const departure = dayjs(res.departure_date);
        const startOffset = arrival.diff(start, 'day');
        const nightCount = departure.diff(arrival, 'day');
        const clampedStart = Math.max(0, startOffset);
        const clampedEnd = Math.min(days, startOffset + nightCount);
        const span = clampedEnd - clampedStart;

        if (span <= 0) {
            return null;
        }

        return {
            left: clampedStart * CELL_WIDTH + 2,
            width: span * CELL_WIDTH - 4,
        };
    };

    const blockColor = (color: string) => {
        if (color === 'green') {
            return '#52c41a';
        }
        if (color === 'blue') {
            return '#1677ff';
        }
        return '#722ed1';
    };

    return (
        <AuthenticatedLayout title="Reservation Calendar">
            <Head title="Reservation Calendar" />
            <Space style={{ marginBottom: 16 }}>
                <Link href="/reservations">
                    <Button>List View</Button>
                </Link>
                <Link href="/reservations/create">
                    <Button type="primary">New Reservation</Button>
                </Link>
            </Space>

            <div
                style={{
                    overflow: 'auto',
                    border: '1px solid #f0f0f0',
                    borderRadius: 8,
                    background: '#fff',
                }}
            >
                <div style={{ display: 'flex', background: '#fafafa', borderBottom: '1px solid #f0f0f0' }}>
                    <div
                        style={{
                            width: LABEL_WIDTH,
                            padding: 8,
                            fontWeight: 600,
                            flexShrink: 0,
                        }}
                    >
                        Room
                    </div>
                    <div style={{ display: 'flex', width: gridWidth }}>
                        {dateColumns.map((col) => (
                            <div
                                key={col.date}
                                style={{
                                    width: CELL_WIDTH,
                                    padding: 8,
                                    textAlign: 'center',
                                    fontSize: 11,
                                    borderLeft: '1px solid #f0f0f0',
                                    flexShrink: 0,
                                }}
                            >
                                {col.label}
                            </div>
                        ))}
                    </div>
                </div>

                {rooms.map((room) => {
                    const roomReservations = blocksByRoom.get(room.id) ?? [];

                    return (
                        <div
                            key={room.id}
                            style={{
                                display: 'flex',
                                borderBottom: '1px solid #f0f0f0',
                            }}
                        >
                            <div
                                style={{
                                    width: LABEL_WIDTH,
                                    padding: 8,
                                    flexShrink: 0,
                                    background: '#fafafa',
                                    borderRight: '1px solid #f0f0f0',
                                }}
                            >
                                <div style={{ fontWeight: 500 }}>{room.number}</div>
                                <div style={{ fontSize: 11, color: '#888' }}>
                                    {room.room_type?.name}
                                </div>
                            </div>
                            <div
                                style={{
                                    position: 'relative',
                                    width: gridWidth,
                                    height: ROW_HEIGHT,
                                    flexShrink: 0,
                                }}
                            >
                                <div style={{ display: 'flex', height: '100%' }}>
                                    {dateColumns.map((col) => (
                                        <div
                                            key={col.date}
                                            style={{
                                                width: CELL_WIDTH,
                                                height: '100%',
                                                borderLeft: '1px solid #f0f0f0',
                                                background:
                                                    dayjs(col.date).day() === 0 ||
                                                    dayjs(col.date).day() === 6
                                                        ? '#f9f9f9'
                                                        : '#fff',
                                                flexShrink: 0,
                                            }}
                                        />
                                    ))}
                                </div>
                                {roomReservations.map((res) => {
                                    const style = getBlockStyle(res);
                                    if (!style) {
                                        return null;
                                    }

                                    return (
                                        <Link
                                            key={`${res.id}-${res.room_id}`}
                                            href={`/reservations/${res.id}`}
                                            style={{
                                                position: 'absolute',
                                                top: 6,
                                                height: ROW_HEIGHT - 12,
                                                left: style.left,
                                                width: style.width,
                                                background: blockColor(res.status_color),
                                                color: '#fff',
                                                borderRadius: 4,
                                                padding: '2px 6px',
                                                fontSize: 11,
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                                whiteSpace: 'nowrap',
                                                lineHeight: `${ROW_HEIGHT - 16}px`,
                                            }}
                                        >
                                            {res.guest_name ?? res.reservation_code}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>
        </AuthenticatedLayout>
    );
}
