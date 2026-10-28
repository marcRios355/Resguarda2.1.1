export const SOS_PENDING_IDEMPOTENCY_KEY = 'resguarda.sos.pending.v1';

export function getSosPendingStorageKey(userId: number): string {
    return `${SOS_PENDING_IDEMPOTENCY_KEY}.${userId}`;
}

export interface SosIdempotencyStorage {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
    removeItem(key: string): void;
}

export function getPendingSosKey(
    storage: SosIdempotencyStorage,
    userId: number,
    previousStorage?: SosIdempotencyStorage,
): string | null {
    const key = getSosPendingStorageKey(userId);
    const pendingKey = storage.getItem(key);

    if (pendingKey) {
        previousStorage?.removeItem(key);

        return pendingKey;
    }

    const previousPendingKey = previousStorage?.getItem(key);
    if (previousPendingKey) {
        storage.setItem(key, previousPendingKey);
        previousStorage?.removeItem(key);

        return previousPendingKey;
    }

    return null;
}

export function beginSosAction(
    storage: SosIdempotencyStorage,
    userId: number,
    createId: () => string,
): string {
    const pendingKey = getPendingSosKey(storage, userId);

    if (pendingKey) {
        return pendingKey;
    }

    const idempotencyKey = createId();
    storage.setItem(getSosPendingStorageKey(userId), idempotencyKey);

    return idempotencyKey;
}

export function completeSosAction(
    storage: SosIdempotencyStorage,
    userId: number,
    previousStorage?: SosIdempotencyStorage,
): void {
    const key = getSosPendingStorageKey(userId);
    previousStorage?.removeItem(key);
    storage.removeItem(key);
}
