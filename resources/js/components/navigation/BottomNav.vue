<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useNotificationSummary } from '@/composables/useNotificationSummary';
import {
    Map,
    AlertTriangle,
    MessageSquare,
    Search,
    User,
    Sparkles,
} from '@lucide/vue';

const page = usePage();
const currentUrl = computed(() => page.url);
const { unreadPrivateMessages } = useNotificationSummary();

const navItems = [
    {
        name: 'Mapa',
        href: '/map',
        icon: Map,
        active: computed(() => currentUrl.value.startsWith('/map')),
    },
    {
        name: 'Alertas',
        href: '/alerts',
        icon: AlertTriangle,
        active: computed(() => currentUrl.value.startsWith('/alerts')),
    },
    {
        name: 'Comunicación',
        href: '/communication',
        icon: MessageSquare,
        badge: unreadPrivateMessages,
        active: computed(
            () =>
                currentUrl.value.startsWith('/communication') ||
                currentUrl.value.startsWith('/chat') ||
                currentUrl.value.startsWith('/private-chat'),
        ),
    },
    {
        name: 'Objetos',
        href: '/lost-found',
        icon: Search,
        active: computed(() => currentUrl.value.startsWith('/lost-found')),
    },
    {
        name: 'Asistente',
        href: '/assistant',
        icon: Sparkles,
        active: computed(() => currentUrl.value.startsWith('/assistant')),
    },
    {
        name: 'Perfil',
        href: '/settings/profile',
        icon: User,
        active: computed(
            () =>
                currentUrl.value.startsWith('/settings') ||
                currentUrl.value.startsWith('/profile'),
        ),
    },
];
</script>

<template>
    <!-- Barra de navegación inferior Mobile-First (visible solo en pantallas < md) -->
    <nav
        aria-label="Navegación principal"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-lg transition-transform md:hidden dark:border-slate-800 dark:bg-slate-950/95"
    >
        <div class="mx-auto grid h-16 max-w-lg grid-cols-6 items-center px-1">
            <Link
                v-for="item in navItems"
                :key="item.name"
                :href="item.href"
                class="relative flex flex-col items-center justify-center py-1 transition-colors"
                :class="[
                    item.active.value
                        ? 'font-semibold text-emerald-600 dark:text-emerald-400'
                        : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                ]"
            >
                <div class="relative">
                    <component
                        :is="item.icon"
                        class="h-5 w-5 transition-transform"
                        :class="[item.active.value ? 'scale-110' : '']"
                    />
                    <span
                        v-if="item.badge && item.badge.value > 0"
                        class="py-0.2 absolute -top-1 -right-2 rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white ring-2 ring-white dark:ring-slate-950"
                    >
                        {{ item.badge.value > 99 ? '99+' : item.badge.value }}
                    </span>
                </div>
                <span class="mt-1 text-[11px] tracking-tight">{{
                    item.name
                }}</span>
                <!-- Línea indicadora activa -->
                <span
                    v-if="item.active.value"
                    class="absolute top-0 h-0.5 w-8 rounded-full bg-emerald-500 dark:bg-emerald-400"
                ></span>
            </Link>
        </div>
    </nav>
</template>
