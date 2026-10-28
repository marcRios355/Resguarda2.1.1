<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AtSign,
    BellOff,
    BellRing,
    CheckCheck,
    MessageCircleReply,
    MessagesSquare,
    UsersRound,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import {
    browserNotificationsSupported,
    useNotificationSummary,
} from '@/composables/useNotificationSummary';
import type { NotificationItem } from '@/composables/useNotificationSummary';

const props = defineProps<{
    notifications: NotificationItem[];
    preferences: {
        notify_all_community_messages: boolean;
        show_message_previews: boolean;
    };
}>();

const {
    browserNotificationsEnabled,
    enableBrowserNotifications,
    disableBrowserNotifications,
    refresh,
} = useNotificationSummary();

const permissionState = ref<NotificationPermission | 'unsupported'>('default');
const unreadCount = computed(
    () => props.notifications.filter((item) => !item.read_at).length,
);

const preferencesForm = useForm({ ...props.preferences });

const savePreferences = () => {
    preferencesForm.patch('/notifications/preferences', {
        preserveScroll: true,
    });
};

const open = (notification: NotificationItem) => {
    router.post(
        `/notifications/${notification.id}/read`,
        {},
        {
            onSuccess: () => void refresh(),
        },
    );
};

const markAllRead = () => {
    router.post(
        '/notifications/read-all',
        {},
        {
            preserveScroll: true,
            onSuccess: () => void refresh(),
        },
    );
};

const toggleBrowserNotifications = async () => {
    if (browserNotificationsEnabled.value) {
        disableBrowserNotifications();

        return;
    }

    permissionState.value = await enableBrowserNotifications();
};

const iconFor = (kind: string) =>
    ({
        private_message: MessagesSquare,
        community_reply: MessageCircleReply,
        community_mention: AtSign,
        community_community_message: UsersRound,
    })[kind] ?? BellRing;

const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleString() : '';

onMounted(() => {
    permissionState.value = browserNotificationsSupported()
        ? Notification.permission
        : 'unsupported';
});
</script>

<template>
    <Head title="Notificaciones" />

    <main
        class="mx-auto flex w-full max-w-4xl min-w-0 flex-col gap-4 p-3 sm:p-4 lg:p-6"
    >
        <section
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950/70"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-emerald-600 uppercase dark:text-emerald-400"
                >
                    RESGUARDA
                </p>
                <h1
                    class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"
                >
                    Notificaciones
                </h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ unreadCount }} sin leer · mensajes privados, respuestas y
                    menciones.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                :disabled="unreadCount === 0"
                @click="markAllRead"
            >
                <CheckCheck class="h-4 w-4" />
                Marcar todas como leídas
            </button>
        </section>

        <section class="space-y-2" aria-label="Lista de notificaciones">
            <button
                v-for="notification in props.notifications"
                :key="notification.id"
                type="button"
                class="flex w-full min-w-0 items-start gap-3 rounded-2xl border p-4 text-left shadow-sm transition hover:border-emerald-300 dark:hover:border-emerald-800"
                :class="
                    notification.read_at
                        ? 'border-slate-200 bg-white/60 dark:border-slate-800 dark:bg-slate-950/50'
                        : 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900 dark:bg-emerald-950/30'
                "
                @click="open(notification)"
            >
                <component
                    :is="iconFor(notification.kind)"
                    class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                />
                <span class="min-w-0 flex-1">
                    <span
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span
                            class="font-semibold text-slate-900 dark:text-white"
                            >{{ notification.title }}</span
                        >
                        <span
                            class="text-xs text-slate-500 dark:text-slate-400"
                            >{{ formatDate(notification.created_at) }}</span
                        >
                    </span>
                    <span
                        class="mt-1 block text-sm break-words text-slate-600 dark:text-slate-300"
                        >{{ notification.body }}</span
                    >
                    <span
                        v-if="!notification.read_at"
                        class="mt-2 inline-block rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white uppercase"
                    >
                        Nueva
                    </span>
                </span>
            </button>
            <p
                v-if="props.notifications.length === 0"
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
            >
                <BellOff class="h-6 w-6" />
                No tienes notificaciones todavía.
            </p>
        </section>

        <section
            class="space-y-4 rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950/70"
        >
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                Preferencias
            </h2>

            <form class="space-y-3" @submit.prevent="savePreferences">
                <label class="flex items-start gap-3 text-sm">
                    <input
                        v-model="preferencesForm.notify_all_community_messages"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600"
                    />
                    <span>
                        <span
                            class="font-medium text-slate-800 dark:text-slate-100"
                            >Avisarme de todos los mensajes de la
                            comunidad</span
                        >
                        <span
                            class="block text-xs text-slate-500 dark:text-slate-400"
                            >Desactivado por defecto: solo recibes avisos cuando
                            te responden o te mencionan con @usuario.</span
                        >
                    </span>
                </label>
                <label class="flex items-start gap-3 text-sm">
                    <input
                        v-model="preferencesForm.show_message_previews"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600"
                    />
                    <span>
                        <span
                            class="font-medium text-slate-800 dark:text-slate-100"
                            >Mostrar vista previa del contenido</span
                        >
                        <span
                            class="block text-xs text-slate-500 dark:text-slate-400"
                            >Si lo desactivas, las notificaciones solo indicarán
                            quién te escribió.</span
                        >
                    </span>
                </label>
                <button
                    type="submit"
                    class="min-h-10 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="
                        preferencesForm.processing || !preferencesForm.isDirty
                    "
                >
                    {{
                        preferencesForm.processing
                            ? 'Guardando…'
                            : 'Guardar preferencias'
                    }}
                </button>
            </form>

            <div
                class="rounded-xl border border-sky-200 bg-sky-50/70 p-4 text-sm dark:border-sky-900 dark:bg-sky-950/30"
            >
                <p class="font-medium text-slate-800 dark:text-slate-100">
                    Avisos del navegador
                </p>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                    Te mostramos un aviso del sistema cuando recibes un mensaje
                    privado, una respuesta o una mención mientras RESGUARDA está
                    abierto en otra pestaña. El navegador te pedirá permiso solo
                    al pulsar el botón.
                </p>
                <p
                    v-if="permissionState === 'unsupported'"
                    class="mt-2 text-xs text-amber-700 dark:text-amber-300"
                >
                    Este navegador no admite avisos del sistema. Seguirás viendo
                    las notificaciones aquí.
                </p>
                <p
                    v-else-if="permissionState === 'denied'"
                    class="mt-2 text-xs text-amber-700 dark:text-amber-300"
                >
                    Bloqueaste los avisos para este sitio. Puedes habilitarlos
                    desde la configuración del navegador.
                </p>
                <button
                    v-else
                    type="button"
                    class="mt-3 min-h-10 rounded-xl border border-sky-300 px-4 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-100 dark:border-sky-800 dark:text-sky-300 dark:hover:bg-sky-900/40"
                    @click="toggleBrowserNotifications"
                >
                    {{
                        browserNotificationsEnabled
                            ? 'Desactivar avisos del navegador'
                            : 'Activar avisos del navegador'
                    }}
                </button>
            </div>
        </section>
    </main>
</template>
