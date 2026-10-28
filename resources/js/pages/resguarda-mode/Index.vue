<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, BellRing, ShieldCheck, ShieldAlert, Siren } from '@lucide/vue';

const props = defineProps<{
    user: {
        name: string | null;
        username: string | null;
    };
    status: string;
    districts: Array<Record<string, unknown>>;
}>();
</script>

<template>
    <Head title="Modo RESGUARDA" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-600 to-teal-700 p-4 text-white shadow-sm shadow-emerald-600/20 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-100">Modo operativo</p>
                    <h1 class="mt-2 text-2xl font-bold">RESGUARDA</h1>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-emerald-50">
                    <Siren class="h-3.5 w-3.5" />
                    {{ props.status === 'active' ? 'Activo' : 'No activo' }}
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-white/10 p-3">
                    <p class="text-xs uppercase tracking-wide text-emerald-100">Usuario</p>
                    <p class="mt-2 font-semibold">{{ props.user.name ?? 'Usuario' }}</p>
                </div>
                <div class="rounded-xl bg-white/10 p-3">
                    <p class="text-xs uppercase tracking-wide text-emerald-100">Modo</p>
                    <p class="mt-2 font-semibold">Protección</p>
                </div>
                <div class="rounded-xl bg-white/10 p-3">
                    <p class="text-xs uppercase tracking-wide text-emerald-100">Estado</p>
                    <p class="mt-2 font-semibold">Monitoreo</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                    <ShieldCheck class="h-4 w-4" />
                    <p class="text-xs font-semibold uppercase tracking-[0.2em]">Rutas seguras</p>
                </div>
                <ul class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li>• Colegio San José</li>
                    <li>• Polideportivo El Milagro</li>
                    <li>• Centro de Emergencia Sur</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                    <BellRing class="h-4 w-4" />
                    <p class="text-xs font-semibold uppercase tracking-[0.2em]">Alertas</p>
                </div>
                <ul class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li>• Precaución por lluvias intensas</li>
                    <li>• Revisión de calles inundadas</li>
                    <li>• Verificación de zonas seguras</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                    <ShieldAlert class="h-4 w-4" />
                    <p class="text-xs font-semibold uppercase tracking-[0.2em]">Acción inmediata</p>
                </div>
                <ul class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li>• Activar protocolo de evacuación</li>
                    <li>• Mantener contacto con vecinos</li>
                    <li>• Confirmar ubicación segura</li>
                </ul>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
            <div class="flex items-center gap-2 text-sky-600 dark:text-sky-400">
                <Activity class="h-4 w-4" />
                <p class="text-xs font-semibold uppercase tracking-[0.2em]">Situación</p>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">
                Modo operativo activo para monitoreo preventivo y coordinación rápida de respuesta comunitaria.
            </p>
        </section>
    </div>
</template>
