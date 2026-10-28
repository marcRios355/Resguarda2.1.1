<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    evidences: Array<{
        id: number;
        title: string;
        description: string | null;
        incident_type: string;
        severity: string;
        status: string;
        status_label: string;
        district_name: string | null;
        user_name: string | null;
        confidence: number;
        created_at: string | null;
    }>;
    helpRequests: Array<{
        id: number;
        help_type: string;
        description: string | null;
        urgency_level: string;
        status: string;
        status_label: string;
        district_name: string | null;
        user_name: string | null;
        created_at: string | null;
    }>;
}>();
</script>

<template>
    <Head title="Moderación y revisión" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-600 dark:text-violet-400">Moderación</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Revisión de evidencias y ayudas</h1>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Evidencias pendientes</h2>
                <span class="rounded-full bg-violet-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-violet-700 dark:bg-violet-950/60 dark:text-violet-300">
                    {{ props.evidences.length }} registros
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-700 dark:text-slate-300">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                            <th class="px-3 py-2">Evidencia</th>
                            <th class="px-3 py-2">Distrito</th>
                            <th class="px-3 py-2">Confianza</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="evidence in props.evidences" :key="evidence.id" class="border-b border-slate-200 last:border-0 dark:border-slate-800">
                            <td class="px-3 py-3">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ evidence.title }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ evidence.user_name ?? 'Usuario' }} · {{ evidence.incident_type }}</p>
                                </div>
                            </td>
                            <td class="px-3 py-3">{{ evidence.district_name ?? 'Sin distrito' }}</td>
                            <td class="px-3 py-3">{{ evidence.confidence }}%</td>
                            <td class="px-3 py-3">{{ evidence.status_label }}</td>
                            <td class="px-3 py-3">
                                <form :action="`/admin/evidence/${evidence.id}/status`" method="post" class="flex flex-wrap gap-2">
                                    <input type="hidden" name="_token" :value="$page.props.csrfToken ?? ''" />
                                    <input type="hidden" name="_method" value="PATCH" />
                                    <select name="status" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                        <option value="pending">Pendiente</option>
                                        <option value="under_review">En revisión</option>
                                        <option value="confirmed">Confirmada</option>
                                        <option value="resolved">Resuelta</option>
                                        <option value="rejected">Rechazada</option>
                                    </select>
                                    <button type="submit" class="rounded-lg bg-violet-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-violet-500">
                                        Guardar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Solicitudes de ayuda</h2>
                <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                    {{ props.helpRequests.length }} registros
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-700 dark:text-slate-300">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
                            <th class="px-3 py-2">Solicitud</th>
                            <th class="px-3 py-2">Urgencia</th>
                            <th class="px-3 py-2">Distrito</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="request in props.helpRequests" :key="request.id" class="border-b border-slate-200 last:border-0 dark:border-slate-800">
                            <td class="px-3 py-3">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ request.help_type }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ request.user_name ?? 'Usuario' }}</p>
                                </div>
                            </td>
                            <td class="px-3 py-3">{{ request.urgency_level }}</td>
                            <td class="px-3 py-3">{{ request.district_name ?? 'Sin distrito' }}</td>
                            <td class="px-3 py-3">{{ request.status_label }}</td>
                            <td class="px-3 py-3">
                                <form :action="`/admin/help/${request.id}/status`" method="post" class="flex flex-wrap gap-2">
                                    <input type="hidden" name="_token" :value="$page.props.csrfToken ?? ''" />
                                    <input type="hidden" name="_method" value="PATCH" />
                                    <select name="status" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                        <option value="pending">Pendiente</option>
                                        <option value="in_progress">En atención</option>
                                        <option value="resolved">Atendido</option>
                                    </select>
                                    <button type="submit" class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                                        Guardar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
