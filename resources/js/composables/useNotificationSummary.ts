import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

export type NotificationItem = {
    id: string;
    kind: string;
    title: string;
    body: string;
    read_at: string | null;
    created_at: string | null;
};

type Summary = {
    unread_notifications: number;
    unread_private_messages: number;
    latest?: NotificationItem[];
};

const BROWSER_PREFERENCE_KEY = 'resguarda.browser-notifications';
const SEEN_KEY = 'resguarda.notifications-seen-at';
const POLL_INTERVAL_MS = 20000;

const unreadNotifications = ref(0);
const unreadPrivateMessages = ref(0);
const browserNotificationsEnabled = ref(readBrowserPreference());
let timer: number | null = null;
let subscribers = 0;
let fetching = false;

function readBrowserPreference(): boolean {
    try {
        return window.localStorage.getItem(BROWSER_PREFERENCE_KEY) === '1';
    } catch {
        return false;
    }
}

function readSeenAt(): string {
    try {
        return window.localStorage.getItem(SEEN_KEY) ?? '';
    } catch {
        return '';
    }
}

function writeSeenAt(value: string): void {
    try {
        window.localStorage.setItem(SEEN_KEY, value);
    } catch {
        // Sin almacenamiento local solo se pierde la deduplicación entre pestañas.
    }
}

export const browserNotificationsSupported = () =>
    typeof window !== 'undefined' && 'Notification' in window;

/**
 * Muestra avisos del sistema solo para notificaciones nuevas, solo si el
 * usuario los activó y solo cuando la pestaña no está a la vista.
 */
function announce(items: NotificationItem[]): void {
    const seenAt = readSeenAt();
    const newest = items.reduce(
        (latest, item) =>
            item.created_at && item.created_at > latest
                ? item.created_at
                : latest,
        seenAt,
    );

    if (!seenAt) {
        writeSeenAt(newest || new Date().toISOString());

        return;
    }

    const fresh = items.filter(
        (item) => item.created_at && item.created_at > seenAt,
    );
    writeSeenAt(newest);

    if (
        fresh.length === 0 ||
        !browserNotificationsEnabled.value ||
        !browserNotificationsSupported() ||
        Notification.permission !== 'granted' ||
        document.visibilityState === 'visible'
    ) {
        return;
    }

    fresh.slice(0, 3).forEach((item) => {
        const notification = new Notification(item.title, {
            body: item.body,
            tag: `resguarda-${item.id}`,
            icon: '/favicon.svg',
        });
        notification.onclick = () => {
            window.focus();
            router.post(`/notifications/${item.id}/read`);
            notification.close();
        };
    });
}

async function refresh(): Promise<void> {
    if (
        fetching ||
        (document.visibilityState === 'hidden' &&
            !browserNotificationsEnabled.value)
    ) {
        return;
    }

    fetching = true;

    try {
        const response = await fetch('/notifications/summary', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const summary = (await response.json()) as Summary;
        unreadNotifications.value = summary.unread_notifications;
        unreadPrivateMessages.value = summary.unread_private_messages;
        announce(summary.latest ?? []);
    } catch {
        // Un fallo de red temporal no debe interrumpir la aplicación; se reintenta en el siguiente ciclo.
    } finally {
        fetching = false;
    }
}

export function useNotificationSummary() {
    const start = () => {
        subscribers++;

        if (timer === null) {
            void refresh();
            timer = window.setInterval(() => void refresh(), POLL_INTERVAL_MS);
        }
    };

    const stop = () => {
        subscribers = Math.max(0, subscribers - 1);

        if (subscribers === 0 && timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    const sync = (counts: Summary | null | undefined) => {
        if (counts) {
            unreadNotifications.value = counts.unread_notifications;
            unreadPrivateMessages.value = counts.unread_private_messages;
        }
    };

    const enableBrowserNotifications = async (): Promise<
        NotificationPermission | 'unsupported'
    > => {
        if (!browserNotificationsSupported()) {
            return 'unsupported';
        }

        const permission =
            Notification.permission === 'default'
                ? await Notification.requestPermission()
                : Notification.permission;

        browserNotificationsEnabled.value = permission === 'granted';

        try {
            window.localStorage.setItem(
                BROWSER_PREFERENCE_KEY,
                browserNotificationsEnabled.value ? '1' : '0',
            );
        } catch {
            // Ignorado: la preferencia queda en memoria.
        }

        return permission;
    };

    const disableBrowserNotifications = () => {
        browserNotificationsEnabled.value = false;

        try {
            window.localStorage.setItem(BROWSER_PREFERENCE_KEY, '0');
        } catch {
            // Ignorado.
        }
    };

    return {
        unreadNotifications: computed(() => unreadNotifications.value),
        unreadPrivateMessages: computed(() => unreadPrivateMessages.value),
        browserNotificationsEnabled: computed(
            () => browserNotificationsEnabled.value,
        ),
        start,
        stop,
        sync,
        refresh,
        enableBrowserNotifications,
        disableBrowserNotifications,
    };
}
