<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    reports: Array<{
        id: number;
        title: string;
        description: string;
        incident_type: string;
        severity: string;
        status: string;
        status_label: string;
        district_name: string | null;
        user_name: string | null;
        created_at: string | null;
    }>;
}>();
</script>

<template>
    <Head title="Reportes del sistema" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-600 dark:text-orange-400">Administración</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Gestión de reportes</h1>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-700 dark:text-slate-300">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                            <th class="px-3 py-2">Reporte</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Distrito</th>
                            <th class="px-3 py-2">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="report in props.reports" :key="report.id" class="border-b border-slate-200 last:border-0 dark:border-slate-800">
                            <td class="px-3 py-3">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ report.title }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ report.user_name ?? 'Usuario' }}</p>
                                </div>
                            </td>
                            <td class="px-3 py-3">{{ report.incident_type }}</td>
                            <td class="px-3 py-3">{{ report.status_label }}</td>
                            <td class="px-3 py-3">{{ report.district_name ?? 'Sin distrito' }}</td>
                            <td class="px-3 py-3">{{ report.created_at ?? 'Sin fecha' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
