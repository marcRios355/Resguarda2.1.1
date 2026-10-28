<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    alerts: Array<{
        id: number;
        title: string;
        message: string;
        type: string;
        severity: string;
        district_name: string | null;
        created_by: string | null;
        is_active: boolean;
        expires_at: string | null;
    }>;
}>();
</script>

<template>
    <Head title="Alertas del sistema" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">Administración</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Alertas activas</h1>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-700 dark:text-slate-300">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                            <th class="px-3 py-2">Alerta</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Severidad</th>
                            <th class="px-3 py-2">Distrito</th>
                            <th class="px-3 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="alert in props.alerts" :key="alert.id" class="border-b border-slate-200 last:border-0 dark:border-slate-800">
                            <td class="px-3 py-3">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ alert.title }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ alert.created_by ?? 'Sistema' }}</p>
                                </div>
                            </td>
                            <td class="px-3 py-3">{{ alert.type }}</td>
                            <td class="px-3 py-3">{{ alert.severity }}</td>
                            <td class="px-3 py-3">{{ alert.district_name ?? 'Sin distrito' }}</td>
                            <td class="px-3 py-3">{{ alert.is_active ? 'Activa' : 'Inactiva' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
