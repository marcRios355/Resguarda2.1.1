<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { useNotificationSummary } from '@/composables/useNotificationSummary';

const page = usePage();
const { unreadNotifications, start, stop, sync } = useNotificationSummary();

type Counts = {
    unread_notifications: number;
    unread_private_messages: number;
} | null;

watch(
    () => page.props.notificationCounts as Counts,
    (counts) => sync(counts),
    { immediate: true },
);

onMounted(start);
onBeforeUnmount(stop);
</script>

<template>
    <Link
        href="/notifications"
        class="relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
        :aria-label="
            unreadNotifications > 0
                ? `Notificaciones: ${unreadNotifications} sin leer`
                : 'Notificaciones'
        "
    >
        <Bell class="h-4 w-4" />
        <span
            v-if="unreadNotifications > 0"
            class="absolute -top-1.5 -right-1.5 min-w-5 rounded-full bg-red-600 px-1 text-center text-[10px] leading-5 font-bold text-white ring-2 ring-white dark:ring-slate-900"
        >
            {{ unreadNotifications > 99 ? '99+' : unreadNotifications }}
        </span>
    </Link>
</template>
