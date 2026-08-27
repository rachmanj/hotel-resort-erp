const DB_NAME = 'workbox-background-sync';
const STORE_NAME = 'requests';
const QUEUE_NAME_INDEX = 'queueName';
export const OFFLINE_WRITES_QUEUE = 'offline-writes';

export const PENDING_WRITES_REFRESH_EVENT = 'pending-writes-refresh';

function openDb() {
    return new Promise((resolve, reject) => {
        if (typeof indexedDB === 'undefined') {
            reject(new Error('IndexedDB unavailable'));

            return;
        }

        const request = indexedDB.open(DB_NAME);

        request.onerror = () => reject(request.error ?? new Error('Failed to open IndexedDB'));
        request.onsuccess = () => resolve(request.result);
    });
}

function closeDb(db) {
    try {
        db.close();
    } catch {
        // ignore
    }
}

export async function getPendingWriteCount() {
    if (typeof indexedDB === 'undefined') {
        return 0;
    }

    let db;

    try {
        db = await openDb();

        if (!db.objectStoreNames.contains(STORE_NAME)) {
            return 0;
        }

        const tx = db.transaction(STORE_NAME, 'readonly');
        const index = tx.objectStore(STORE_NAME).index(QUEUE_NAME_INDEX);

        return await new Promise((resolve) => {
            const countRequest = index.count(IDBKeyRange.only(OFFLINE_WRITES_QUEUE));

            countRequest.onsuccess = () => resolve(countRequest.result ?? 0);
            countRequest.onerror = () => resolve(0);
            tx.onerror = () => resolve(0);
        });
    } catch {
        return 0;
    } finally {
        if (db) {
            closeDb(db);
        }
    }
}

/**
 * @returns {Promise<Array<{ id: number, method: string, url: string, timestamp: number | null }>>}
 */
export async function getPendingWrites() {
    if (typeof indexedDB === 'undefined') {
        return [];
    }

    let db;

    try {
        db = await openDb();

        if (!db.objectStoreNames.contains(STORE_NAME)) {
            return [];
        }

        const tx = db.transaction(STORE_NAME, 'readonly');
        const index = tx.objectStore(STORE_NAME).index(QUEUE_NAME_INDEX);

        const entries = await new Promise((resolve) => {
            const getAllRequest = index.getAll(IDBKeyRange.only(OFFLINE_WRITES_QUEUE));

            getAllRequest.onsuccess = () => resolve(getAllRequest.result ?? []);
            getAllRequest.onerror = () => resolve([]);
            tx.onerror = () => resolve([]);
        });

        return entries.map((entry) => ({
            id: entry.id,
            method: entry.requestData?.method ?? 'POST',
            url: entry.requestData?.url ?? '',
            timestamp: entry.timestamp ?? null,
        }));
    } catch {
        return [];
    } finally {
        if (db) {
            closeDb(db);
        }
    }
}

export function requestPendingCountRefresh() {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(PENDING_WRITES_REFRESH_EVENT));
}
