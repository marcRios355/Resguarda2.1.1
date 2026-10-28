<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token ?? '');

const props = defineProps<{
    districts: Array<{
        id: number;
        name: string;
        risk_level: string;
        zones: Array<{
            id: number;
            name: string;
            zone_type: string;
            radius_meters: number;
            latitude: number;
            longitude: number;
        }>;
    }>;
}>();

const zoneLabels: Record<string, string> = {
    residential: 'Residencial',
    flood_zone: 'Zona de inundación',
    blocked_street: 'Calle bloqueada',
    critical_area: 'Área crítica',
    evacuation_route: 'Ruta de evacuación',
    other: 'Otro',
};
</script>

<template>
    <Head title="Zonas por distrito" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600 dark:text-sky-400">Mapeo</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Zonas y sectores críticos</h1>
                </div>
                <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 dark:border-sky-900/70 dark:bg-sky-950/40 dark:text-sky-300">
                    {{ props.districts.reduce((total, district) => total + district.zones.length, 0) }} zonas registradas
                </span>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div v-for="district in props.districts" :key="district.id" class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ district.name }}</h2>
                    <span class="rounded-full bg-sky-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">
                        {{ district.risk_level }}
                    </span>
                </div>

                <form method="post" action="/district-zones" class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/80">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input type="hidden" name="district_id" :value="district.id" />
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                            Nombre
                            <input name="name" required maxlength="150" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" placeholder="Ej. Sector norte" />
                        </label>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                            Tipo
                            <select name="zone_type" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                                <option value="residential">Residencial</option>
                                <option value="flood_zone">Zona de inundación</option>
                                <option value="blocked_street">Calle bloqueada</option>
                                <option value="critical_area">Área crítica</option>
                                <option value="evacuation_route">Ruta de evacuación</option>
                                <option value="other">Otro</option>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                            Latitud
                            <input name="latitude" type="number" step="0.000001" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" />
                        </label>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                            Longitud
                            <input name="longitude" type="number" step="0.000001" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" />
                        </label>
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                            Radio (metros)
                            <input name="radius_meters" type="number" min="10" max="50000" value="500" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" />
                        </label>
                    </div>
                    <button type="submit" class="mt-3 inline-flex items-center rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-500">
                        Agregar zona
                    </button>
                </form>

                <div v-if="district.zones.length" class="space-y-3">
                    <div v-for="zone in district.zones" :key="zone.id" class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-white">{{ zone.name }}</p>
                                <span class="mt-1 inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-[10px] uppercase tracking-wide text-slate-700 dark:bg-slate-700 dark:text-slate-100">
                                    {{ zoneLabels[zone.zone_type] ?? zone.zone_type }}
                                </span>
                            </div>
                            <form method="post" :action="`/district-zones/${zone.id}`">
                                <input type="hidden" name="_token" :value="csrfToken" />
                                <input type="hidden" name="_method" value="DELETE" />
                                <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-red-700 hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                                    Eliminar
                                </button>
                            </form>
                        </div>

                        <form method="post" :action="`/district-zones/${zone.id}`" class="mt-3 grid gap-2 md:grid-cols-2">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <input type="hidden" name="_method" value="PATCH" />
                            <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                                Nombre
                                <input :name="'name'" :value="zone.name" required maxlength="150" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" />
                            </label>
                            <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                                Tipo
                                <select :name="'zone_type'" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                                    <option value="residential" :selected="zone.zone_type === 'residential'">Residencial</option>
                                    <option value="flood_zone" :selected="zone.zone_type === 'flood_zone'">Zona de inundación</option>
                                    <option value="blocked_street" :selected="zone.zone_type === 'blocked_street'">Calle bloqueada</option>
                                    <option value="critical_area" :selected="zone.zone_type === 'critical_area'">Área crítica</option>
                                    <option value="evacuation_route" :selected="zone.zone_type === 'evacuation_route'">Ruta de evacuación</option>
                                    <option value="other" :selected="zone.zone_type === 'other'">Otro</option>
                                </select>
                            </label>
                            <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                                Latitud
                                <input :name="'latitude'" :value="zone.latitude ?? ''" type="number" step="0.000001" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" />
                            </label>
                            <label class="text-xs font-medium text-slate-700 dark:text-slate-200">
                                Longitud
                                <input :name="'longitude'" :value="zone.longitude ?? ''" type="number" step="0.000001" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" />
                            </label>
                            <label class="text-xs font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                                Radio (metros)
                                <input :name="'radius_meters'" :value="zone.radius_meters ?? 500" type="number" min="10" max="50000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" />
                            </label>
                            <div class="md:col-span-2 flex justify-end">
                                <button type="submit" class="inline-flex items-center rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-100 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300">
                                    Guardar cambios
                                </button>
                            </div>
                        </form>

                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            Radio {{ zone.radius_meters }}m · {{ zone.latitude ?? '—' }}, {{ zone.longitude ?? '—' }}
                        </p>
                    </div>
                </div>

                <p v-else class="text-sm text-slate-500 dark:text-slate-400">No hay zonas registradas para este distrito.</p>
            </div>
        </section>
    </div>
</template>
