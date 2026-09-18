import { Head, Link } from '@inertiajs/react';
import { theme, Typography } from 'antd';
import type { GlobalToken } from 'antd/es/theme/interface';
import type { CSSProperties } from 'react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface RoomChip {
    id: number;
    number: string;
    housekeeping_status: string;
    housekeeping_status_label: string;
    housekeeping_status_color: string;
}

interface RoomStatusSummary {
    total: number;
    dirty: number;
    cleaning: number;
    clean: number;
    ready: number;
    out_of_order: number;
}

interface MovementEntry {
    id: number;
    time: string | null;
    guest_name: string;
    room_label: string;
    nights: number;
    guest_count: number;
    source_label: string;
    agent_name: string | null;
    status_label: string;
    status_kind: string;
    folio_open: boolean;
}

interface OccupancyPoint {
    date: string;
    label: string;
    occupancy: number;
}

interface RevenueMixRow {
    category: string;
    amount: number;
    share: number;
}

interface DashboardIndexProps {
    occupancy: number;
    checkinsToday: number;
    occupiedRooms: number;
    sellableRooms: number;
    revenueToday: number;
    roomStatusSummary: RoomStatusSummary;
    rooms: RoomChip[];
    arrivalsToday: MovementEntry[];
    departuresToday: MovementEntry[];
    occupancySeries: OccupancyPoint[];
    occupancyDelta: number;
    inHouseGuests: number;
    revenueMix: RevenueMixRow[];
}

type MovementFilter = 'all' | 'arrivals' | 'departures';

type MovementRow = MovementEntry & {
    direction: 'arrival' | 'departure';
};

const PANEL_FONT_SIZE = 13;

function formatCompactIdr(amount: number): string {
    const abs = Math.abs(amount);
    const formatNum = (value: number): string =>
        value.toLocaleString('id-ID', {
            maximumFractionDigits: 1,
            minimumFractionDigits: Number.isInteger(value) ? 0 : 1,
        });

    if (abs >= 1_000_000) {
        return `${formatNum(amount / 1_000_000)} jt`;
    }

    if (abs >= 1_000) {
        return `${formatNum(amount / 1_000)} rb`;
    }

    return amount.toLocaleString('id-ID');
}

function panelStyle(token: GlobalToken): CSSProperties {
    return {
        background: token.colorBgContainer,
        border: `1px solid ${token.colorBorderSecondary}`,
        borderRadius: 6,
        boxShadow: 'none',
        fontSize: PANEL_FONT_SIZE,
        overflow: 'hidden',
    };
}

function captionStyle(token: GlobalToken): CSSProperties {
    return {
        fontSize: 10,
        fontWeight: 700,
        textTransform: 'uppercase',
        letterSpacing: '0.06em',
        color: token.colorTextSecondary,
        lineHeight: 1.2,
    };
}

function kpiValueStyle(token: GlobalToken): CSSProperties {
    return {
        fontSize: 20,
        fontWeight: 700,
        fontVariantNumeric: 'tabular-nums',
        color: token.colorText,
        lineHeight: 1.2,
    };
}

function housekeepingTileBackgroundColor(status: string, token: GlobalToken): string {
    switch (status) {
        case 'dirty':
            return token.colorErrorBg;
        case 'cleaning':
            return token.colorWarningBg;
        case 'clean':
            return token.colorSuccessBg;
        case 'inspected':
            return token.colorPrimaryBg;
        case 'ready':
            return token.colorSuccessBgHover;
        case 'out_of_order':
            return token.colorFillSecondary;
        default:
            return token.colorFillSecondary;
    }
}

function housekeepingTileBorderColor(status: string, token: GlobalToken): string {
    switch (status) {
        case 'dirty':
            return token.colorError;
        case 'cleaning':
            return token.colorWarning;
        case 'clean':
            return token.colorSuccess;
        case 'inspected':
            return token.colorPrimary;
        case 'ready':
            return token.colorSuccess;
        case 'out_of_order':
            return token.colorTextSecondary;
        default:
            return token.colorTextSecondary;
    }
}

function housekeepingTileAriaLabel(roomNumber: string, statusLabel: string): string {
    return `Room ${roomNumber}, ${statusLabel.toLowerCase()}`;
}

function statusKindColor(kind: string, token: GlobalToken): string {
    if (kind === 'done' || kind === 'ready') {
        return token.colorSuccess;
    }

    if (kind === 'waiting') {
        return token.colorWarning;
    }

    return token.colorError;
}

function formatOccupancyDelta(delta: number): string {
    if (delta > 0) {
        return `+${delta} pts vs yesterday`;
    }

    if (delta < 0) {
        return `${delta} pts vs yesterday`;
    }

    return 'Same as yesterday';
}

function occupancyDeltaColor(delta: number, token: GlobalToken): string {
    if (delta > 0) {
        return token.colorSuccess;
    }

    if (delta < 0) {
        return token.colorError;
    }

    return token.colorTextSecondary;
}

const MOVEMENT_FILTERS: Array<{ key: MovementFilter; label: string }> = [
    { key: 'all', label: 'All' },
    { key: 'arrivals', label: 'Arrivals' },
    { key: 'departures', label: 'Departures' },
];

const HOUSEKEEPING_LEGEND: Array<{ status: string; label: string }> = [
    { status: 'dirty', label: 'Dirty' },
    { status: 'cleaning', label: 'Cleaning' },
    { status: 'clean', label: 'Clean' },
    { status: 'inspected', label: 'Inspected' },
    { status: 'ready', label: 'Ready' },
    { status: 'out_of_order', label: 'Out of Order' },
];

export default function DashboardIndex({
    occupancy,
    occupiedRooms,
    sellableRooms,
    revenueToday,
    roomStatusSummary,
    rooms,
    arrivalsToday,
    departuresToday,
    occupancySeries,
    occupancyDelta,
    inHouseGuests,
    revenueMix,
}: DashboardIndexProps) {
    const { token } = theme.useToken();
    const [movementFilter, setMovementFilter] = useState<MovementFilter>('all');

    const movementRows = useMemo(() => {
        const rows: MovementRow[] = [
            ...arrivalsToday.map((entry) => ({ ...entry, direction: 'arrival' as const })),
            ...departuresToday.map((entry) => ({ ...entry, direction: 'departure' as const })),
        ];

        return rows.sort((left, right) => {
            if (left.time === null && right.time === null) {
                return 0;
            }

            if (left.time === null) {
                return 1;
            }

            if (right.time === null) {
                return -1;
            }

            return left.time.localeCompare(right.time);
        });
    }, [arrivalsToday, departuresToday]);

    const filteredMovementRows = useMemo(() => {
        if (movementFilter === 'arrivals') {
            return movementRows.filter((row) => row.direction === 'arrival');
        }

        if (movementFilter === 'departures') {
            return movementRows.filter((row) => row.direction === 'departure');
        }

        return movementRows;
    }, [movementFilter, movementRows]);

    const roomsNeedingService = roomStatusSummary.dirty + roomStatusSummary.cleaning;

    const occupancyBounds = useMemo(() => {
        if (occupancySeries.length === 0) {
            return { min: 0, max: 0 };
        }

        const values = occupancySeries.map((point) => point.occupancy);

        return {
            min: Math.min(...values),
            max: Math.max(...values),
        };
    }, [occupancySeries]);

    const chartHeight = 120;
    const chartWidth = 100;
    const barGap = 4;

    return (
        <AuthenticatedLayout title="Dashboard">
            <Head title="Dashboard" />
            <style>{`
                .dashboard-page {
                    display: flex;
                    flex-direction: column;
                    gap: 16px;
                    min-width: 0;
                }

                .dashboard-kpi-strip {
                    display: grid;
                    grid-template-columns: repeat(5, minmax(0, 1fr));
                }

                .dashboard-kpi-cell {
                    padding: 16px;
                    min-width: 0;
                }

                .dashboard-kpi-cell:not(:last-child) {
                    border-right: 1px solid ${token.colorBorderSecondary};
                }

                @media (max-width: 768px) {
                    .dashboard-kpi-strip {
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                    }

                    .dashboard-kpi-cell {
                        border-right: none;
                        border-bottom: 1px solid ${token.colorBorderSecondary};
                    }

                    .dashboard-kpi-cell:nth-child(odd) {
                        border-right: 1px solid ${token.colorBorderSecondary};
                    }

                    .dashboard-kpi-cell:nth-last-child(-n+2) {
                        border-bottom: none;
                    }

                    .dashboard-kpi-cell:last-child:nth-child(odd) {
                        border-right: none;
                    }
                }

                @media (max-width: 480px) {
                    .dashboard-kpi-strip {
                        grid-template-columns: minmax(0, 1fr);
                    }

                    .dashboard-kpi-cell,
                    .dashboard-kpi-cell:nth-child(odd) {
                        border-right: none;
                        border-bottom: 1px solid ${token.colorBorderSecondary};
                    }

                    .dashboard-kpi-cell:last-child {
                        border-bottom: none;
                    }
                }

                .dashboard-movement-row {
                    display: grid;
                    grid-template-columns: 52px 88px minmax(120px, 1.4fr) minmax(100px, 1fr) 56px minmax(80px, 0.8fr) minmax(80px, 0.8fr) minmax(90px, 0.9fr);
                    gap: 8px;
                    align-items: center;
                    padding: 10px 16px;
                    border-top: 1px solid ${token.colorBorderSecondary};
                    min-width: 0;
                }

                .dashboard-movement-row:first-of-type {
                    border-top: none;
                }

                @media (max-width: 900px) {
                    .dashboard-movement-row {
                        grid-template-columns: 1fr;
                        gap: 4px;
                        align-items: start;
                    }
                }

                .dashboard-filter-tab {
                    appearance: none;
                    background: transparent;
                    border: 1px solid ${token.colorBorderSecondary};
                    border-radius: 6px;
                    color: ${token.colorTextSecondary};
                    cursor: pointer;
                    font-size: 12px;
                    font-weight: 600;
                    line-height: 1;
                    padding: 6px 10px;
                }

                .dashboard-filter-tab.is-active {
                    background: ${token.colorPrimaryBg};
                    border-color: ${token.colorPrimaryBorder};
                    color: ${token.colorPrimary};
                }

                .dashboard-room-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(44px, 1fr));
                    gap: 6px;
                }

                .dashboard-revenue-table {
                    width: 100%;
                    border-collapse: collapse;
                }

                .dashboard-revenue-table th,
                .dashboard-revenue-table td {
                    padding: 10px 16px;
                    text-align: left;
                    border-top: 1px solid ${token.colorBorderSecondary};
                }

                .dashboard-revenue-table th {
                    font-size: 10px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.06em;
                    color: ${token.colorTextSecondary};
                }

                .dashboard-revenue-table td:last-child,
                .dashboard-revenue-table th:last-child {
                    text-align: right;
                }
            `}</style>

            <div className="dashboard-page">
                <section style={panelStyle(token)}>
                    <div className="dashboard-kpi-strip">
                        <div className="dashboard-kpi-cell">
                            <div style={captionStyle(token)}>Occupancy</div>
                            <div style={{ ...kpiValueStyle(token), marginTop: 6 }}>{occupancy}%</div>
                            <div
                                style={{
                                    marginTop: 4,
                                    fontSize: 12,
                                    color: occupancyDeltaColor(occupancyDelta, token),
                                    fontVariantNumeric: 'tabular-nums',
                                }}
                            >
                                {formatOccupancyDelta(occupancyDelta)}
                            </div>
                            <div style={{ marginTop: 4, fontSize: 12, color: token.colorTextSecondary }}>
                                {occupiedRooms} of {sellableRooms} rooms occupied
                            </div>
                        </div>
                        <div className="dashboard-kpi-cell">
                            <div style={captionStyle(token)}>Arrivals today</div>
                            <div style={{ ...kpiValueStyle(token), marginTop: 6 }}>{arrivalsToday.length}</div>
                        </div>
                        <div className="dashboard-kpi-cell">
                            <div style={captionStyle(token)}>Departures today</div>
                            <div style={{ ...kpiValueStyle(token), marginTop: 6 }}>{departuresToday.length}</div>
                        </div>
                        <div className="dashboard-kpi-cell">
                            <div style={captionStyle(token)}>In-house guests</div>
                            <div style={{ ...kpiValueStyle(token), marginTop: 6 }}>{inHouseGuests}</div>
                        </div>
                        <div className="dashboard-kpi-cell">
                            <div style={captionStyle(token)}>Revenue today</div>
                            <div style={{ ...kpiValueStyle(token), marginTop: 6 }}>{formatCompactIdr(revenueToday)}</div>
                        </div>
                    </div>
                </section>

                <section style={panelStyle(token)}>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            gap: 12,
                            flexWrap: 'wrap',
                            padding: '12px 16px',
                            borderBottom: `1px solid ${token.colorBorderSecondary}`,
                        }}
                    >
                        <div style={captionStyle(token)}>Movement today</div>
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            {MOVEMENT_FILTERS.map((filter) => (
                                <button
                                    key={filter.key}
                                    type="button"
                                    className={`dashboard-filter-tab${movementFilter === filter.key ? ' is-active' : ''}`}
                                    onClick={() => setMovementFilter(filter.key)}
                                >
                                    {filter.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {filteredMovementRows.length === 0 ? (
                        <div style={{ padding: '24px 16px', color: token.colorTextSecondary }}>
                            {movementFilter === 'arrivals' && 'No arrivals scheduled for today.'}
                            {movementFilter === 'departures' && 'No departures scheduled for today.'}
                            {movementFilter === 'all' && 'No arrivals or departures scheduled for today.'}
                        </div>
                    ) : (
                        filteredMovementRows.map((row) => (
                            <div key={`${row.direction}-${row.id}`} className="dashboard-movement-row">
                                <div style={{ fontVariantNumeric: 'tabular-nums', color: token.colorTextSecondary }}>
                                    {row.time ?? 'TBD'}
                                </div>
                                <div>
                                    <span
                                        style={{
                                            display: 'inline-block',
                                            padding: '2px 8px',
                                            borderRadius: 4,
                                            fontSize: 11,
                                            fontWeight: 700,
                                            textTransform: 'uppercase',
                                            letterSpacing: '0.04em',
                                            background:
                                                row.direction === 'arrival'
                                                    ? token.colorSuccessBg
                                                    : token.colorWarningBg,
                                            color:
                                                row.direction === 'arrival'
                                                    ? token.colorSuccess
                                                    : token.colorWarning,
                                        }}
                                    >
                                        {row.direction === 'arrival' ? 'Check-in' : 'Checkout'}
                                    </span>
                                </div>
                                <div style={{ fontWeight: 600, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                    {row.guest_name}
                                </div>
                                <div style={{ minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                    {row.room_label}
                                </div>
                                <div style={{ fontVariantNumeric: 'tabular-nums' }}>
                                    {row.nights}n
                                </div>
                                <div style={{ minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                    {row.source_label}
                                </div>
                                <div style={{ minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', color: token.colorTextSecondary }}>
                                    {row.agent_name ?? '-'}
                                </div>
                                <div style={{ color: statusKindColor(row.status_kind, token), fontWeight: 600 }}>
                                    {row.status_label}
                                </div>
                            </div>
                        ))
                    )}
                </section>

                <section style={panelStyle(token)}>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            gap: 12,
                            flexWrap: 'wrap',
                            padding: '12px 16px',
                            borderBottom: `1px solid ${token.colorBorderSecondary}`,
                        }}
                    >
                        <div style={captionStyle(token)}>Housekeeping</div>
                        <div style={{ fontSize: 12, color: token.colorTextSecondary }}>
                            {roomsNeedingService} room{roomsNeedingService === 1 ? '' : 's'} need service
                        </div>
                    </div>

                    <div style={{ padding: 16 }}>
                        {rooms.length === 0 ? (
                            <Typography.Text type="secondary">No rooms configured for this property.</Typography.Text>
                        ) : (
                            <>
                                <div className="dashboard-room-grid">
                                    {rooms.map((room) => (
                                        <div
                                            key={room.id}
                                            role="img"
                                            aria-label={housekeepingTileAriaLabel(
                                                room.number,
                                                room.housekeeping_status_label,
                                            )}
                                            title={housekeepingTileAriaLabel(
                                                room.number,
                                                room.housekeeping_status_label,
                                            )}
                                            style={{
                                                aspectRatio: '1',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                borderRadius: 4,
                                                fontSize: 12,
                                                fontWeight: 600,
                                                fontVariantNumeric: 'tabular-nums',
                                                background: housekeepingTileBackgroundColor(
                                                    room.housekeeping_status,
                                                    token,
                                                ),
                                                color: token.colorText,
                                                border: `1px solid ${token.colorBorderSecondary}`,
                                                borderLeft: `2px solid ${housekeepingTileBorderColor(room.housekeeping_status, token)}`,
                                                minWidth: 0,
                                            }}
                                        >
                                            {room.number}
                                        </div>
                                    ))}
                                </div>

                                <div
                                    style={{
                                        display: 'flex',
                                        flexWrap: 'wrap',
                                        gap: 12,
                                        marginTop: 16,
                                        paddingTop: 12,
                                        borderTop: `1px solid ${token.colorBorderSecondary}`,
                                    }}
                                >
                                    {HOUSEKEEPING_LEGEND.map((item) => (
                                        <div key={item.status} style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                            <span
                                                style={{
                                                    width: 10,
                                                    height: 10,
                                                    borderRadius: 2,
                                                    background: housekeepingTileBackgroundColor(item.status, token),
                                                    border: `1px solid ${token.colorBorderSecondary}`,
                                                    borderLeft: `2px solid ${housekeepingTileBorderColor(item.status, token)}`,
                                                    flexShrink: 0,
                                                }}
                                            />
                                            <span style={{ fontSize: 12, color: token.colorTextSecondary }}>
                                                {item.label}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </>
                        )}
                    </div>

                    <div
                        style={{
                            padding: '10px 16px',
                            borderTop: `1px solid ${token.colorBorderSecondary}`,
                            display: 'flex',
                            justifyContent: 'flex-end',
                        }}
                    >
                        <Link href="/housekeeping" style={{ fontSize: 12, color: token.colorPrimary }}>
                            View board
                        </Link>
                    </div>
                </section>

                <section style={panelStyle(token)}>
                    <div
                        style={{
                            padding: '12px 16px',
                            borderBottom: `1px solid ${token.colorBorderSecondary}`,
                        }}
                    >
                        <div style={captionStyle(token)}>Occupancy, last 14 days</div>
                    </div>

                    <div style={{ padding: '16px 16px 12px' }}>
                        <div style={{ width: '100%', overflowX: 'auto' }}>
                            <svg
                                viewBox={`0 0 ${occupancySeries.length * (chartWidth / occupancySeries.length || chartWidth)} ${chartHeight + 28}`}
                                preserveAspectRatio="none"
                                style={{ width: '100%', height: 148, display: 'block' }}
                                role="img"
                                aria-label="Occupancy for the last 14 days"
                            >
                                {occupancySeries.map((point, index) => {
                                    const slotWidth = 100 / occupancySeries.length;
                                    const barWidth = Math.max(2, slotWidth - barGap);
                                    const x = index * slotWidth + barGap / 2;
                                    const barHeight = (point.occupancy / 100) * chartHeight;
                                    const y = chartHeight - barHeight;

                                    return (
                                        <rect
                                            key={point.date}
                                            x={`${x}%`}
                                            y={y}
                                            width={`${barWidth}%`}
                                            height={barHeight}
                                            fill={
                                                point.occupancy === occupancyBounds.max
                                                    ? token.colorPrimary
                                                    : point.occupancy === occupancyBounds.min &&
                                                        occupancyBounds.min !== occupancyBounds.max
                                                      ? token.colorWarning
                                                      : token.colorPrimaryBorder
                                            }
                                            rx={2}
                                        />
                                    );
                                })}
                                <line
                                    x1="0"
                                    y1={chartHeight}
                                    x2="100%"
                                    y2={chartHeight}
                                    stroke={token.colorBorderSecondary}
                                />
                            </svg>
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                gap: 12,
                                marginTop: 8,
                                fontSize: 12,
                                color: token.colorTextSecondary,
                                fontVariantNumeric: 'tabular-nums',
                            }}
                        >
                            <span>Low {occupancyBounds.min}%</span>
                            <span>High {occupancyBounds.max}%</span>
                        </div>
                    </div>
                </section>

                <section style={panelStyle(token)}>
                    <div
                        style={{
                            padding: '12px 16px',
                            borderBottom: `1px solid ${token.colorBorderSecondary}`,
                        }}
                    >
                        <div style={captionStyle(token)}>Revenue mix this month</div>
                    </div>

                    {revenueMix.length === 0 ? (
                        <div style={{ padding: '24px 16px', color: token.colorTextSecondary }}>
                            No revenue posted this month.
                        </div>
                    ) : (
                        <table className="dashboard-revenue-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                {revenueMix.map((row) => (
                                    <tr key={row.category}>
                                        <td>{row.category}</td>
                                        <td style={{ fontVariantNumeric: 'tabular-nums' }}>
                                            {formatCompactIdr(row.amount)}
                                        </td>
                                        <td style={{ fontVariantNumeric: 'tabular-nums' }}>{row.share}%</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
