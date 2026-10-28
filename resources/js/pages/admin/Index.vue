<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Activity, BellRing, ShieldAlert, Users } from '@lucide/vue';
import { index as sosDeliveriesIndex } from '@/routes/admin/sos-deliveries';

const props = defineProps<{
    stats: {
        users: number;
        reports: number;
        activeAlerts: number;
    };
    recentReports: Array<{
        id: number;
        title: string;
        status: string;
        severity: string;
        district_name: string | null;
        user_name: string | null;
        created_at: string | null;
    }>;
}>();
</script>

<template>
    <Head title="Panel administrativo" />

    <div
        class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6"
    >
        <section
            class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950/70"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-400"
                    >
                        Panel administrativo
                    </p>
                    <h1
                        class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        Centro de control
                    </h1>
                </div>
                <div
                    class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 dark:border-indigo-900/70 dark:bg-indigo-950/50 dark:text-indigo-300"
                >
                    <Activity class="h-3.5 w-3.5" />
                    Supervisión activa
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <div
                class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
            >
                <div
                    class="flex items-center gap-2 text-sky-600 dark:text-sky-400"
                >
                    <Users class="h-4 w-4" />
                    <p class="text-xs font-semibold tracking-[0.2em] uppercase">
                        Usuarios
                    </p>
                </div>
                <p
                    class="mt-4 text-3xl font-bold text-slate-900 dark:text-white"
                >
                    {{ props.stats.users }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
            >
                <div
                    class="flex items-center gap-2 text-orange-600 dark:text-orange-400"
                >
                    <ShieldAlert class="h-4 w-4" />
                    <p class="text-xs font-semibold tracking-[0.2em] uppercase">
                        Reportes
                    </p>
                </div>
                <p
                    class="mt-4 text-3xl font-bold text-slate-900 dark:text-white"
                >
                    {{ props.stats.reports }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
            >
                <div
                    class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400"
                >
                    <BellRing class="h-4 w-4" />
                    <p class="text-xs font-semibold tracking-[0.2em] uppercase">
                        Alertas activas
                    </p>
                </div>
                <p
                    class="mt-4 text-3xl font-bold text-slate-900 dark:text-white"
                >
                    {{ props.stats.activeAlerts }}
                </p>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <a
                href="/admin/moderation"
                class="rounded-2xl border border-violet-200 bg-violet-50 p-4 shadow-sm transition hover:border-violet-300 hover:bg-violet-100 dark:border-violet-900/70 dark:bg-violet-950/30 dark:hover:border-violet-700 dark:hover:bg-violet-950/50"
            >
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-violet-600 uppercase dark:text-violet-300"
                >
                    Moderación
                </p>
                <h2
                    class="mt-2 text-lg font-bold text-slate-900 dark:text-white"
                >
                    Revisar evidencias y ayudas
                </h2>
            </a>
            <Link
                :href="sosDeliveriesIndex.url()"
                class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm transition hover:border-amber-300 hover:bg-amber-100 dark:border-amber-900/70 dark:bg-amber-950/30 dark:hover:border-amber-700 dark:hover:bg-amber-950/50"
            >
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-amber-700 uppercase dark:text-amber-300"
                >
                    Notificaciones SOS
                </p>
                <h2
                    class="mt-2 text-lg font-bold text-slate-900 dark:text-white"
                >
                    Revisar entregas inciertas
                </h2>
            </Link>
            <a
                href="/district-zones"
                class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm transition hover:border-sky-300 hover:bg-sky-100 dark:border-sky-900/70 dark:bg-sky-950/30 dark:hover:border-sky-700 dark:hover:bg-sky-950/50"
            >
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-sky-600 uppercase dark:text-sky-300"
                >
                    Zonas
                </p>
                <h2
                    class="mt-2 text-lg font-bold text-slate-900 dark:text-white"
                >
                    Ver sectores por distrito
                </h2>
            </a>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
        >
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                Reportes recientes
            </h2>

            <div class="mt-4 space-y-3">
                <div
                    v-for="report in props.recentReports"
                    :key="report.id"
                    class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/80"
                >
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">
                            {{ report.title }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ report.user_name ?? 'Usuario' }} ·
                            {{ report.district_name ?? 'Sin distrito' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p
                            class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                        >
                            {{ report.status }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ report.created_at }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
