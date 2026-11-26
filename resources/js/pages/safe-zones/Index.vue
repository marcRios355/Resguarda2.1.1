<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MapPin, Navigation, ShieldCheck, ShieldPlus, ThumbsDown, ThumbsUp, Users, Wifi, Droplets, HeartPulse, Building2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import LocationPanel from '@/components/LocationPanel.vue';
import SafeZoneForm from '@/components/SafeZoneForm.vue';
import { directionsUrl, distanceKm, useGeolocation } from '@/composables/useGeolocation';

const props = defineProps<{
    zones: Array<{
        id: number;
        name: string;
        address: string;
        district_name: string | null;
        latitude: number;
        longitude: number;
        is_verified: boolean;
        capacity: number;
        current_occupancy: number;
        occupancy_percentage: number;
        status: string;
        status_label: string;
        status_color: string;
        services: string[];
        image_path: string | null;
        notes: string | null;
        published_by: string | null;
        reliable_votes: number;
        unreliable_votes: number;
        my_vote: 'reliable' | 'unreliable' | null;
        updated_at: string | null;
        distance_km: number | null;
    }>;
    districts: Array<{ id: number; name: string; latitude: number; longitude: number }>;
    selectedZoneId?: number | null;
}>();

const showForm = ref(false);
const voting = ref(false);

const vote = (zoneId: number, value: 'reliable' | 'unreliable') => {
    router.post(`/safe-zones/${zoneId}/vote`, { vote: value }, {
        preserveScroll: true,
        onStart: () => (voting.value = true),
        onFinish: () => (voting.value = false),
    });
};

const serviceLabels: Record<string, string> = {
    agua: 'Agua',
    luz: 'Luz',
    banos: 'Baños',
    baños: 'Baños',
    primeros_auxilios: 'Primeros auxilios',
    alimentos: 'Alimentos',
    internet: 'Internet',
    seguridad: 'Seguridad',
};

const { coords } = useGeolocation();

/*
 * Las distancias se calculan en el dispositivo: la ubicación del usuario no se
 * envía al servidor para ordenar esta lista.
 */
const zones = computed(() => {
    const position = coords.value;
    const items = props.zones.map((zone) => ({
        ...zone,
        distance_km: position ? distanceKm(position, zone) : null,
    }));

    return position
        ? items.sort((a, b) => (a.distance_km ?? 0) - (b.distance_km ?? 0))
        : items;
});

const selectedZone = computed(() => {
    if (!props.selectedZoneId) {
        return zones.value[0] ?? null;
    }

    return zones.value.find((zone) => zone.id === props.selectedZoneId) ?? zones.value[0] ?? null;
});

const serviceIcons: Record<string, any> = {
    agua: Droplets,
    luz: Wifi,
    primeros_auxilios: HeartPulse,
    baños: Building2,
    banos: Building2,
};

const statusStyles: Record<string, string> = {
    available: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    limited: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    almost_full: 'bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300',
    saturated: 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300',
    unavailable: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};
</script>

<template>
    <Head title="Zonas Seguras" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">Refugios y puntos de resguardo</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Zonas Seguras</h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <ShieldCheck class="h-3.5 w-3.5" />
                        {{ zones.length }} zonas registradas
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition"
                        :class="showForm ? 'border border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200' : 'bg-emerald-600 text-white hover:bg-emerald-700'"
                        @click="showForm = !showForm"
                    >
                        <X v-if="showForm" class="h-4 w-4" />
                        <ShieldPlus v-else class="h-4 w-4" />
                        {{ showForm ? 'Cerrar' : 'Publicar zona segura' }}
                    </button>
                </div>
            </div>
        </section>

        <SafeZoneForm v-if="showForm" :districts="props.districts ?? []" @published="showForm = false" />

        <LocationPanel purpose="Activa tu ubicación para ordenar las zonas seguras por cercanía. Se usa solo en este dispositivo." />

        <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-4">
                <div
                    v-for="zone in zones"
                    :key="zone.id"
                    class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm transition hover:border-emerald-200 hover:shadow-md dark:border-slate-800 dark:bg-slate-950/70"
                    :class="selectedZone && selectedZone.id === zone.id ? 'border-emerald-300 dark:border-emerald-700' : ''"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ zone.name }}</h2>
                            <p v-if="zone.distance_km !== null" class="mt-1 text-sm font-semibold text-emerald-700 dark:text-emerald-300">A {{ zone.distance_km.toFixed(1) }} km en línea recta</p>
                            <p v-if="!zone.is_verified" class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                {{ zone.published_by ? `Publicada por ${zone.published_by} · ` : '' }}Información no verificada por RESGUARDA
                            </p>
                            <div class="mt-1 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                <MapPin class="h-4 w-4" />
                                <span>{{ zone.address }}</span>
                            </div>
                        </div>
                        <span :class="['rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide', statusStyles[zone.status] || 'bg-slate-100 text-slate-600']">
                            {{ zone.status_label }}
                        </span>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Distrito</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ zone.district_name || 'No registrado' }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Capacidad</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ zone.capacity }} personas</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Ocupación</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ zone.current_occupancy }}/{{ zone.capacity }}</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="mb-1 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>Disponibilidad</span>
                            <span>{{ zone.occupancy_percentage }}%</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500"
                                :style="`width: ${zone.occupancy_percentage}%`"
                            />
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <template v-if="zone.services && zone.services.length">
                            <span
                                v-for="service in zone.services"
                                :key="service"
                                class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-medium uppercase tracking-wide text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                            >
                                <component :is="serviceIcons[service] || ShieldCheck" class="h-3 w-3" />
                                {{ serviceLabels[service] ?? service }}
                            </span>
                        </template>
                        <span v-else class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-medium uppercase tracking-wide text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                            Servicios no especificados
                        </span>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            <span>Última actualización:</span>
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ zone.updated_at ? new Date(zone.updated_at).toLocaleString() : 'Sin datos' }}</span>
                        </div>
                        <Link
                            :href="`/safe-zones/${zone.id}`"
                            class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                        >
                            Ver detalle
                        </Link>
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                <div v-if="selectedZone" class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="mb-3 flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                        <ShieldCheck class="h-4 w-4" />
                        <p class="text-xs font-semibold uppercase tracking-[0.2em]">Detalle de zona</p>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/80">
                        <div class="h-36 bg-gradient-to-br from-emerald-200 via-emerald-100 to-sky-100 dark:from-emerald-900/80 dark:via-slate-900 dark:to-sky-950/80" />
                    </div>

                    <h2 class="mt-4 text-2xl font-bold text-slate-900 dark:text-white">{{ selectedZone.name }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ selectedZone.address }}</p>

                    <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex items-center justify-between">
                            <span>Distrito</span>
                            <strong>{{ selectedZone.district_name || 'No definido' }}</strong>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Capacidad</span>
                            <strong>{{ selectedZone.capacity }} personas</strong>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Ocupación</span>
                            <strong>{{ selectedZone.current_occupancy }} / {{ selectedZone.capacity }}</strong>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Estado</span>
                            <strong>{{ selectedZone.status_label }}</strong>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Distancia</span>
                            <strong>{{ selectedZone.distance_km ? `${selectedZone.distance_km.toFixed(1)} km` : 'No calculada' }}</strong>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/80">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Servicios</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-for="service in selectedZone.services" :key="service" class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">{{ serviceLabels[service] ?? service }}</span>
                        </div>
                    </div>

                    <div v-if="selectedZone.notes" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/80">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Información adicional</p>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ selectedZone.notes }}</p>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/80">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">¿Es confiable esta zona?</p>
                        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-slate-600 dark:text-slate-300">
                            <span class="inline-flex items-center gap-1"><ThumbsUp class="h-4 w-4 text-emerald-600" /> {{ selectedZone.reliable_votes }} confiable</span>
                            <span class="inline-flex items-center gap-1"><ThumbsDown class="h-4 w-4 text-red-600" /> {{ selectedZone.unreliable_votes }} no confiable</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold transition disabled:opacity-60"
                                :class="selectedZone.my_vote === 'reliable' ? 'bg-emerald-600 text-white' : 'border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300'"
                                :aria-pressed="selectedZone.my_vote === 'reliable'"
                                :disabled="voting"
                                @click="vote(selectedZone.id, 'reliable')"
                            >
                                <ThumbsUp class="h-4 w-4" /> Es confiable
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold transition disabled:opacity-60"
                                :class="selectedZone.my_vote === 'unreliable' ? 'bg-red-600 text-white' : 'border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300'"
                                :aria-pressed="selectedZone.my_vote === 'unreliable'"
                                :disabled="voting"
                                @click="vote(selectedZone.id, 'unreliable')"
                            >
                                <ThumbsDown class="h-4 w-4" /> No es confiable
                            </button>
                        </div>
                        <p v-if="selectedZone.my_vote" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Puedes cambiar tu voto cuando quieras. Todos ven estos totales.</p>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/80">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Última actualización</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ selectedZone.updated_at ? new Date(selectedZone.updated_at).toLocaleString() : 'Sin datos' }}</p>
                    </div>

                    <div class="mt-4">
                        <a
                            :href="directionsUrl(selectedZone.latitude, selectedZone.longitude)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-sky-700"
                        >
                            <Navigation class="h-4 w-4" />
                            Cómo llegar
                        </a>
                        <p class="mt-2 text-center text-[11px] text-slate-500 dark:text-slate-400">Abre Google Maps con el destino; tu ubicación no se envía desde RESGUARDA.</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="flex items-center gap-2 text-slate-700 dark:text-slate-200">
                        <Users class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Indicadores</p>
                    </div>
                    <ul class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <li>• Disponibles: {{ zones.filter((zone) => zone.status === 'available').length }}</li>
                        <li>• Limitadas: {{ zones.filter((zone) => zone.status === 'limited').length }}</li>
                        <li>• Casi llenas: {{ zones.filter((zone) => zone.status === 'almost_full').length }}</li>
                        <li>• Saturadas: {{ zones.filter((zone) => zone.status === 'saturated').length }}</li>
                    </ul>
                </div>
            </aside>
        </section>
    </div>
</template>
