<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, BellRing, ShieldAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import LocationPanel from '@/components/LocationPanel.vue';
import { useGeolocation } from '@/composables/useGeolocation';

const props = defineProps<{
    alerts: Array<{
        id: number;
        title: string;
        message: string;
        type: string;
        severity: string;
        district_id: number | null;
        district_name: string | null;
        created_by: string | null;
        latitude: number | null;
        longitude: number | null;
        radius_meters: number | null;
        expires_at: string | null;
    }>;
    districts: Array<{ id: number; name: string }>;
}>();

const { lookup, preferredDistrictId } = useGeolocation();
const districtFilter = ref<string>('');
const myDistrictId = computed(() => lookup.value?.district?.id ?? preferredDistrictId.value ?? null);

/*
 * Las alertas sin distrito aplican a toda la provincia y se muestran siempre.
 */
const visibleAlerts = computed(() => {
    if (!districtFilter.value) {
        return props.alerts;
    }

    const districtId = districtFilter.value === 'mine' ? myDistrictId.value : Number(districtFilter.value);

    return props.alerts.filter((alert) => alert.district_id === null || alert.district_id === districtId);
});

const severityClasses: Record<string, string> = {
    info: 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
    caution: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
    danger: 'bg-orange-100 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300',
    critical: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
};
</script>

<template>
    <Head title="Alertas" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-600 dark:text-red-400">Alertas activas</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Monitoreo comunitario</h1>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 dark:border-red-900/70 dark:bg-red-950/50 dark:text-red-300">
                    <BellRing class="h-3.5 w-3.5" />
                    {{ props.alerts.length }} alertas
                </div>
            </div>
        </section>

        <LocationPanel :districts="props.districts" purpose="Activa tu ubicación o elige tu distrito para ver las alertas que te afectan." />

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <label for="alert-district-filter" class="text-sm font-medium text-slate-700 dark:text-slate-200">Mostrar alertas de</label>
            <select id="alert-district-filter" v-model="districtFilter" class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <option value="">Toda la provincia</option>
                <option v-if="myDistrictId" value="mine">Mi distrito</option>
                <option v-for="district in props.districts" :key="district.id" :value="String(district.id)">{{ district.name }}</option>
            </select>
        </div>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="space-y-4">
                <p v-if="visibleAlerts.length === 0" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No hay alertas activas registradas para este filtro.</p>
                <div v-for="alert in visibleAlerts" :key="alert.id" class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ alert.title }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ alert.district_name ?? 'Distrito no identificado' }} · {{ alert.created_by ?? 'Sistema' }}</p>
                        </div>
                        <span :class="['rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide', severityClasses[alert.severity] || 'bg-slate-100 text-slate-700']">
                            {{ alert.severity }}
                        </span>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ alert.message }}</p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Tipo</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ alert.type }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Radio</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ alert.radius_meters ?? 300 }} m</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Vence</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ alert.expires_at ? new Date(alert.expires_at).toLocaleString() : 'Sin tiempo' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                    <ShieldAlert class="h-4 w-4" />
                    <p class="text-xs font-semibold uppercase tracking-[0.2em]">Resumen</p>
                </div>

                <div class="mt-4 rounded-xl border border-dashed border-red-200 bg-red-50 p-3 dark:border-red-900/60 dark:bg-red-950/30">
                    <p class="text-sm font-semibold text-red-700 dark:text-red-300">Criterio de activación</p>
                    <p class="mt-2 text-sm text-red-700 dark:text-red-300">
                        Cuando un reporte alcanza 5 o más confirmaciones, se activa una alerta con prioridad y radio de cobertura.
                    </p>
                </div>

                <div class="mt-4 flex items-center gap-2 rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                    <AlertTriangle class="h-5 w-5 text-orange-500" />
                    <span class="text-sm text-slate-700 dark:text-slate-200">Acciones recomendadas: coordinar evacuación, activar rutas de emergencia y avisar a la comunidad.</span>
                </div>
            </aside>
        </section>
    </div>
</template>
