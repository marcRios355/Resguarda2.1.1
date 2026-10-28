<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, Bell, MapPinned, ShieldCheck, Siren, Users } from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const districtStatus = [
    { name: 'El Porvenir', level: 'Segura', color: 'bg-emerald-500', tone: 'text-emerald-700 dark:text-emerald-300', value: '10%' },
    { name: 'Huanchaco', level: 'Precaución', color: 'bg-amber-500', tone: 'text-amber-700 dark:text-amber-300', value: '32%' },
    { name: 'Florencia de Mora', level: 'Riesgo', color: 'bg-orange-500', tone: 'text-orange-700 dark:text-orange-300', value: '48%' },
    { name: 'Trujillo Centro', level: 'Crítico', color: 'bg-violet-500', tone: 'text-violet-700 dark:text-violet-300', value: '64%' },
];

const safeZones = [
    { name: 'Polideportivo El Milagro', address: 'Av. Brasil 240', occupied: 62, capacity: 180, status: 'Disponible' },
    { name: 'Colegio San José', address: 'Jr. Pizarro 104', occupied: 81, capacity: 140, status: 'Capacidad limitada' },
    { name: 'Centro de Emergencia Sur', address: 'Calle Los Álamos 40', occupied: 96, capacity: 110, status: 'Casi llena' },
];

const alerts = [
    { title: 'Alerta por lluvias intensas', type: 'Hidrometeorológico', time: 'Hace 12 min', severity: 'Alta' },
    { title: 'Calle bloqueada por huaico', type: 'Infraestructura', time: 'Hace 28 min', severity: 'Media' },
    { title: 'Zona segura con aforo bajo', type: 'Seguridad', time: 'Hace 41 min', severity: 'Baja' },
];

const quickActions = [
    { label: 'Mapa', icon: MapPinned, accent: 'bg-blue-100 text-blue-700' },
    { label: 'Reportes', icon: AlertTriangle, accent: 'bg-orange-100 text-orange-700' },
    { label: 'Zonas seguras', icon: ShieldCheck, accent: 'bg-emerald-100 text-emerald-700' },
    { label: 'Alertas', icon: Bell, accent: 'bg-red-100 text-red-700' },
];
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 overflow-x-clip rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="grid gap-4 xl:grid-cols-[1.5fr_0.9fr]">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm backdrop-blur-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">Situación general</p>
                        <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Resumen operacional de la ciudad</h1>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:border-amber-900/70 dark:bg-amber-950/50 dark:text-amber-300">
                        <Siren class="h-3.5 w-3.5" />
                        Monitoreo activo
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Distritos</p>
                        <div class="mt-2 flex items-end justify-between">
                            <span class="text-2xl font-bold text-slate-900 dark:text-white">12</span>
                            <span class="text-xs text-emerald-600 dark:text-emerald-400">3 activos</span>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Zonas seguras</p>
                        <div class="mt-2 flex items-end justify-between">
                            <span class="text-2xl font-bold text-slate-900 dark:text-white">18</span>
                            <span class="text-xs text-blue-600 dark:text-blue-400">6 disponibles</span>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Reportes hoy</p>
                        <div class="mt-2 flex items-end justify-between">
                            <span class="text-2xl font-bold text-slate-900 dark:text-white">82</span>
                            <span class="text-xs text-orange-600 dark:text-orange-400">+23%</span>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Comunidad</p>
                        <div class="mt-2 flex items-end justify-between">
                            <span class="text-2xl font-bold text-slate-900 dark:text-white">4.8k</span>
                            <span class="text-xs text-violet-600 dark:text-violet-400">online</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-emerald-600 to-teal-700 p-4 text-white shadow-sm shadow-emerald-600/20 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-100">Modo RESGUARDA</p>
                <h2 class="mt-2 text-2xl font-bold">Información crítica en un vistazo</h2>
                <div class="mt-5 space-y-3">
                    <div class="flex items-center justify-between rounded-xl bg-white/10 px-3 py-2">
                        <span class="text-sm text-emerald-50">Zona segura más cercana</span>
                        <span class="font-semibold">Colegio San José</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-white/10 px-3 py-2">
                        <span class="text-sm text-emerald-50">Distancia</span>
                        <span class="font-semibold">1.2 km</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-white/10 px-3 py-2">
                        <span class="text-sm text-emerald-50">Nivel de riesgo</span>
                        <span class="font-semibold">Precaución</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.3fr_0.7fr]">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Mapa</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Riesgo por distrito</h3>
                    </div>
                    <button class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Ver mapa completo</button>
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-100 to-slate-200 p-4 dark:border-slate-700 dark:from-slate-900 dark:to-slate-800">
                    <div class="relative h-64 overflow-hidden rounded-xl border border-slate-200 bg-[radial-gradient(circle_at_top,_rgba(16,185,129,.18),_transparent_40%),linear-gradient(135deg,#e2e8f0_0%,#f8fafc_55%,#dbeafe_100%)] dark:border-slate-700 dark:bg-[radial-gradient(circle_at_top,_rgba(16,185,129,.18),_transparent_40%),linear-gradient(135deg,#0f172a_0%,#111827_55%,#0f172a_100%)]">
                        <div class="absolute left-8 top-10 h-10 w-10 rounded-full bg-emerald-500/80 shadow-lg shadow-emerald-500/50"></div>
                        <div class="absolute left-28 top-20 h-12 w-12 rounded-full bg-amber-400/80 shadow-lg shadow-amber-400/50"></div>
                        <div class="absolute left-36 top-32 h-16 w-16 rounded-full bg-orange-500/80 shadow-lg shadow-orange-500/50"></div>
                        <div class="absolute right-16 top-12 h-14 w-14 rounded-full bg-violet-500/80 shadow-lg shadow-violet-500/50"></div>
                        <div class="absolute right-20 bottom-12 h-12 w-12 rounded-full bg-red-500/80 shadow-lg shadow-red-500/50"></div>
                        <div class="absolute left-1/2 top-1/2 h-24 w-24 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-dashed border-slate-400/70 dark:border-slate-500/70"></div>
                        <div class="absolute bottom-4 left-4 rounded-lg bg-white/80 px-2 py-1 text-xs font-semibold text-slate-700 shadow-sm backdrop-blur-sm dark:bg-slate-900/80 dark:text-slate-200">Ubicación actual: Trujillo Centro</div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Rápidas</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Acciones</h3>
                    </div>
                    <Users class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button
                        v-for="action in quickActions"
                        :key="action.label"
                        class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-4 text-center transition hover:border-emerald-200 hover:bg-emerald-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-800 dark:hover:bg-slate-800"
                    >
                        <div :class="['flex h-10 w-10 items-center justify-center rounded-lg', action.accent]">
                            <component :is="action.icon" class="h-5 w-5" />
                        </div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ action.label }}</span>
                    </button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.1fr_1fr]">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Distritos</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Estado por zona</h3>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Actualizado</span>
                </div>
                <div class="mt-4 space-y-3">
                    <div
                        v-for="district in districtStatus"
                        :key="district.name"
                        class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900/80"
                    >
                        <div class="flex items-center gap-3">
                            <span :class="['h-3 w-3 rounded-full', district.color]"></span>
                            <div>
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ district.name }}</p>
                                <p :class="['text-xs font-medium', district.tone]">{{ district.level }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ district.value }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Alertas</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Recientes</h3>
                    </div>
                    <Bell class="h-5 w-5 text-red-500" />
                </div>
                <div class="mt-4 space-y-3">
                    <div
                        v-for="item in alerts"
                        :key="item.title"
                        class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/80"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ item.title }}</span>
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-700 dark:bg-red-950/60 dark:text-red-300">{{ item.severity }}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>{{ item.type }}</span>
                            <span>{{ item.time }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Zonas seguras</p>
                    <h3 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Disponibilidad y aforo</h3>
                </div>
                <ShieldCheck class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-3">
                <div
                    v-for="zone in safeZones"
                    :key="zone.name"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/80"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h4 class="font-semibold text-slate-800 dark:text-slate-100">{{ zone.name }}</h4>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ zone.address }}</p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{{ zone.status }}</span>
                    </div>
                    <div class="mt-4">
                        <div class="mb-1 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>Ocupación</span>
                            <span>{{ zone.occupied }}/{{ zone.capacity }}</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500"
                                :style="`width: ${(zone.occupied / zone.capacity) * 100}%`"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
