import assert from 'node:assert/strict';
import test from 'node:test';
import {
    beginSosAction,
    completeSosAction,
    getPendingSosKey,
    SOS_PENDING_IDEMPOTENCY_KEY,
    type SosIdempotencyStorage,
} from '../../resources/js/lib/sos-idempotency.ts';

class MemoryStorage implements SosIdempotencyStorage {
    private values = new Map<string, string>();

    getItem(key: string): string | null {
        return this.values.get(key) ?? null;
    }

    setItem(key: string, value: string): void {
        this.values.set(key, value);
    }

    removeItem(key: string): void {
        this.values.delete(key);
    }
}

test('a pending SOS action reuses its key after reload, reopening, and in another tab', () => {
    const storage = new MemoryStorage();
    const originalKey = beginSosAction(storage, 7, () => 'sos-action-1');

    // A reloaded page, a reopened tab, and another tab share localStorage.
    const keyAfterReload = getPendingSosKey(storage, 7);
    const keyAfterReopen = getPendingSosKey(storage, 7);

    assert.equal(keyAfterReload, originalKey);
    assert.equal(keyAfterReopen, originalKey);
    assert.equal(
        beginSosAction(storage, 7, () => 'unexpected-new-tab-key'),
        originalKey,
    );
    assert.equal(
        storage.getItem(`${SOS_PENDING_IDEMPOTENCY_KEY}.7`),
        originalKey,
    );
    assert.equal(getPendingSosKey(storage, 8), null);
});

test('a new SOS action gets a new key after the previous action succeeds', () => {
    const storage = new MemoryStorage();
    let nextId = 0;
    const createId = () => `sos-action-${++nextId}`;

    const firstKey = beginSosAction(storage, 7, createId);
    completeSosAction(storage, 7);
    const nextKey = beginSosAction(storage, 7, createId);

    assert.equal(firstKey, 'sos-action-1');
    assert.equal(nextKey, 'sos-action-2');
    assert.notEqual(nextKey, firstKey);
});

test('a pending key from the previous tab-scoped storage is migrated without changing it', () => {
    const persistentStorage = new MemoryStorage();
    const previousTabStorage = new MemoryStorage();
    const previousKey = 'sos-action-pending';
    const storageKey = `${SOS_PENDING_IDEMPOTENCY_KEY}.7`;
    previousTabStorage.setItem(storageKey, previousKey);

    const restoredKey = getPendingSosKey(
        persistentStorage,
        7,
        previousTabStorage,
    );

    assert.equal(restoredKey, previousKey);
    assert.equal(persistentStorage.getItem(storageKey), previousKey);
    assert.equal(previousTabStorage.getItem(storageKey), null);
});
