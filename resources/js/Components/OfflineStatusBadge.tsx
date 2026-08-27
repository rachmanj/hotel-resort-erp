import { CloudSyncOutlined, DisconnectOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { List, Popover, Tag } from 'antd';
import dayjs from 'dayjs';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    getPendingWriteCount,
    getPendingWrites,
    PENDING_WRITES_REFRESH_EVENT,
} from '@/lib/pendingWrites';

const MUTATING_METHODS = new Set(['post', 'put', 'patch', 'delete']);

function formatPendingLabel(method: string, url: string): string {
    try {
        const pathname = new URL(url, window.location.origin).pathname;

        return `${method.toUpperCase()} ${pathname}`;
    } catch {
        return `${method.toUpperCase()} ${url}`;
    }
}

export default function OfflineStatusBadge() {
    const [isOnline, setIsOnline] = useState(
        typeof navigator !== 'undefined' ? navigator.onLine : true,
    );
    const [pendingCount, setPendingCount] = useState(0);
    const [pendingEntries, setPendingEntries] = useState<
        Array<{ id: number; method: string; url: string; timestamp: number | null }>
    >([]);
    const [popoverOpen, setPopoverOpen] = useState(false);
    const refreshInFlight = useRef(false);

    const refreshPendingCount = useCallback(async () => {
        if (refreshInFlight.current) {
            return;
        }

        refreshInFlight.current = true;

        try {
            const count = await getPendingWriteCount();
            setPendingCount(count);
        } finally {
            refreshInFlight.current = false;
        }
    }, []);

    const loadPendingEntries = useCallback(async () => {
        const entries = await getPendingWrites();
        setPendingEntries(entries);
    }, []);

    useEffect(() => {
        refreshPendingCount();
    }, [refreshPendingCount]);

    useEffect(() => {
        const handleOnline = () => {
            setIsOnline(true);
            refreshPendingCount();
        };

        const handleOffline = () => {
            setIsOnline(false);
            refreshPendingCount();
        };

        const handleFocus = () => refreshPendingCount();
        const handleVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                refreshPendingCount();
            }
        };

        const handleRefreshEvent = () => refreshPendingCount();

        const handleInertiaFinish = (event: Event) => {
            const visit = (event as CustomEvent<{ visit?: { method?: string } }>).detail?.visit;

            if (visit?.method && MUTATING_METHODS.has(visit.method.toLowerCase())) {
                window.setTimeout(refreshPendingCount, 500);
            }
        };

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        window.addEventListener('focus', handleFocus);
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener(PENDING_WRITES_REFRESH_EVENT, handleRefreshEvent);

        const removeFinish = router.on('finish', handleInertiaFinish);
        const removeNetworkError = router.on('networkError', () => {
            window.setTimeout(refreshPendingCount, 500);
        });

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
            window.removeEventListener('focus', handleFocus);
            document.removeEventListener('visibilitychange', handleVisibilityChange);
            window.removeEventListener(PENDING_WRITES_REFRESH_EVENT, handleRefreshEvent);
            removeFinish();
            removeNetworkError();
        };
    }, [refreshPendingCount]);

    const showBadge = !isOnline || pendingCount > 0;

    useEffect(() => {
        if (!showBadge) {
            return;
        }

        const intervalId = window.setInterval(refreshPendingCount, 10_000);

        return () => window.clearInterval(intervalId);
    }, [showBadge, refreshPendingCount]);

    useEffect(() => {
        if (popoverOpen) {
            loadPendingEntries();
        }
    }, [popoverOpen, pendingCount, loadPendingEntries]);

    if (!showBadge) {
        return null;
    }

    const label = !isOnline
        ? `Offline — ${pendingCount} pending`
        : `Syncing ${pendingCount}…`;

    const tag = (
        <Tag
            icon={isOnline ? <CloudSyncOutlined /> : <DisconnectOutlined />}
            color={isOnline ? 'processing' : 'warning'}
            style={{ cursor: pendingCount > 0 ? 'pointer' : 'default', margin: 0 }}
        >
            {label}
        </Tag>
    );

    if (pendingCount === 0) {
        return tag;
    }

    return (
        <Popover
            open={popoverOpen}
            onOpenChange={setPopoverOpen}
            trigger="click"
            title="Pending writes"
            content={
                <List
                    size="small"
                    style={{ width: 280, maxHeight: 240, overflow: 'auto' }}
                    dataSource={pendingEntries}
                    locale={{ emptyText: 'No pending writes' }}
                    renderItem={(item) => (
                        <List.Item style={{ paddingInline: 0 }}>
                            <List.Item.Meta
                                title={formatPendingLabel(item.method, item.url)}
                                description={
                                    item.timestamp
                                        ? dayjs(item.timestamp).format('DD MMM YYYY, HH:mm')
                                        : 'Queued'
                                }
                            />
                        </List.Item>
                    )}
                />
            }
        >
            {tag}
        </Popover>
    );
}
