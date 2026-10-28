<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Bell, LocateFixed, MapPinned } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Mapa de Riesgo', href: '/map' },
        ],
    },
});

type DistrictRisk = 'safe' | 'caution' | 'risk' | 'high_risk' | 'critical' | 'clearing';

type District = {
    id: number;
    name: string;
    slug: string;
    risk: DistrictRisk;
    lat: number;
    lng: number;
    summary: string;
    alerts: number;
    evidence: number;
    help: number;
};

const props = defineProps<{
    districts: District[];
}>();

const districts = computed(() => props.districts ?? []);
const mapContainer = ref<HTMLElement | null>(null);
const statusMessage = ref('Solicitando ubicación…');
const mapInstance = shallowRef<L.Map | null>(null);
const tileLayer = shallowRef<L.TileLayer | null>(null);
const districtLayer = shallowRef<L.LayerGroup | null>(null);
const userMarker = shallowRef<L.CircleMarker | null>(null);
const filters = ref({
    risk: true,
    alerts: true,
    evidence: true,
    help: true,
});

const riskStyles: Record<DistrictRisk, { color: string; label: string; badge: string }> = {
    safe: { color: '#10b981', label: 'Seguro', badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' },
    caution: { color: '#f59e0b', label: 'Precaución', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' },
    risk: { color: '#f97316', label: 'En riesgo', badge: 'bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300' },
    high_risk: { color: '#ef4444', label: 'Riesgo alto', badge: 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300' },
    critical: { color: '#8b5cf6', label: 'Crítico', badge: 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300' },
    clearing: { color: '#64748b', label: 'Despeje', badge: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200' },
};

const filteredDistricts = computed(() => districts.value.filter((district) => {
    if (!filters.value.risk && !filters.value.alerts && !filters.value.evidence && !filters.value.help) {
        return true;
    }

    if (filters.value.risk && district.risk !== 'safe') {
        return true;
    }

    if (filters.value.alerts && district.alerts > 0) {
        return true;
    }

    if (filters.value.evidence && district.evidence > 0) {
        return true;
    }

    if (filters.value.help && district.help > 0) {
        return true;
    }

    return false;
}));

const filterSummary = computed(() => ({
    visible: filteredDistricts.value.length,
    alerts: districts.value.filter((district) => district.alerts > 0).length,
    evidence: districts.value.reduce((total, district) => total + district.evidence, 0),
    help: districts.value.reduce((total, district) => total + district.help, 0),
}));

const renderDistrictMarkers = () => {
    const map = mapInstance.value;
    if (!map) return;

    if (districtLayer.value) {
        map.removeLayer(districtLayer.value);
        districtLayer.value.clearLayers();
    }

    const layer = L.layerGroup();
    districtLayer.value = layer;

    filteredDistricts.value.forEach((district) => {
        const style = riskStyles[district.risk as DistrictRisk] ?? riskStyles.safe;
        const marker = L.marker([district.lat, district.lng], {
            icon: L.divIcon({
                className: 'custom-marker',
                html: `<span style="background:${style.color}; border:2px solid white; width:18px; height:18px; display:block; border-radius:9999px; box-shadow:0 0 0 5px rgba(255,255,255,.35)"></span>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9],
            }),
        });

        marker.bindPopup(`
            <div style="min-width:180px;">
                <strong>${district.name}</strong><br>
                <span style="color:${style.color}; font-weight:600;">${style.label}</span><br>
                <small>${district.summary}</small><br>
                <small>Alertas: ${district.alerts} · Evidencias: ${district.evidence} · Ayuda: ${district.help}</small>
            </div>
        `);

        marker.addTo(layer);
    });

    if (userMarker.value && map.hasLayer(userMarker.value)) {
        map.removeLayer(userMarker.value);
    }

    if (userMarker.value) {
        userMarker.value.addTo(layer);
    }

    layer.addTo(map);
};

const requestUserLocation = () => {
    if (!navigator.geolocation) {
        statusMessage.value = 'Geolocalización no disponible en este navegador.';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            statusMessage.value = 'Ubicación actual detectada.';

            if (mapInstance.value) {
                mapInstance.value.flyTo([lat, lng], 13, { duration: 1.5 });
            }

            if (userMarker.value) {
                mapInstance.value?.removeLayer(userMarker.value);
            }

            userMarker.value = L.circleMarker([lat, lng], {
                radius: 10,
                color: '#0f172a',
                weight: 2,
                fillColor: '#22c55e',
                fillOpacity: 0.9,
            });

            renderDistrictMarkers();
        },
        () => {
            statusMessage.value = 'Permiso denegado. Puedes seguir usando el mapa con la vista por defecto.';
            renderDistrictMarkers();
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
    );
};

const focusOnTrujillo = () => {
    mapInstance.value?.flyTo([-8.116, -79.03], 12, { duration: 1.5 });
    statusMessage.value = 'Vista general de Trujillo cargada.';
};

watch(filters, () => {
    renderDistrictMarkers();
}, { deep: true });

onMounted(() => {
    if (!mapContainer.value) return;

    const map = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: true,
    }).setView([-8.116, -79.03], 12);
    mapInstance.value = map;

    tileLayer.value = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    renderDistrictMarkers();
    requestUserLocation();
});

onBeforeUnmount(() => {
    if (mapInstance.value) {
        mapInstance.value.remove();
        mapInstance.value = null;
    }
});
</script>

<template>
    <Head title="Mapa de Riesgo" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="grid gap-4 xl:grid-cols-[1.5fr_0.85fr]">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600 dark:text-sky-400">Mapa operativo</p>
                        <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Mapa de riesgo de Trujillo</h1>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="requestUserLocation"
                            class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300 dark:hover:bg-emerald-900/70"
                        >
                            <LocateFixed class="h-4 w-4" />
                            Mi ubicación
                        </button>
                        <button
                            @click="focusOnTrujillo"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        >
                            <MapPinned class="h-4 w-4" />
                            Trujillo
                        </button>
                    </div>
                </div>

                <div class="mb-3 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-700 dark:border-sky-900/80 dark:bg-sky-950/40 dark:text-sky-300">
                    {{ statusMessage }}
                </div>

                <div ref="mapContainer" class="h-[520px] w-full overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-800" />
            </div>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Filtros</p>
                    <div class="mt-4 space-y-3">
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
                            <span class="text-sm text-slate-700 dark:text-slate-200">Riesgo</span>
                            <input v-model="filters.risk" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
                            <span class="text-sm text-slate-700 dark:text-slate-200">Alertas</span>
                            <input v-model="filters.alerts" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
                            <span class="text-sm text-slate-700 dark:text-slate-200">Evidencias</span>
                            <input v-model="filters.evidence" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
                            <span class="text-sm text-slate-700 dark:text-slate-200">Necesito ayuda</span>
                            <input v-model="filters.help" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        </label>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Distritos</p>
                    <div class="mt-4 space-y-2">
                        <Link
                            v-for="district in filteredDistricts"
                            :key="district.slug ?? district.name"
                            :href="`/reports?district=${district.slug}`"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-left text-sm font-semibold text-slate-700 transition hover:border-orange-300 hover:bg-orange-50 dark:border-slate-700 dark:text-slate-200 dark:hover:border-orange-700 dark:hover:bg-orange-950/30"
                        >
                            {{ district.name }}
                        </Link>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Leyenda</p>
                    <div class="mt-4 space-y-3">
                        <div v-for="(style, key) in riskStyles" :key="key" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-900/80">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full" :style="`background:${style.color}`" />
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ style.label }}</span>
                            </div>
                            <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide', style.badge]">{{ key }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Alertas cercanas</p>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-900/80 dark:bg-red-950/40">
                            <div class="flex items-center justify-between gap-3">
                                <strong class="text-sm text-red-700 dark:text-red-300">Inundación</strong>
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-700 dark:bg-red-900/50 dark:text-red-300">Alta</span>
                            </div>
                            <p class="mt-1 text-xs text-red-700/80 dark:text-red-200/80">Calle principal con tránsito restringido cerca de Trujillo Centro.</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/80 dark:bg-amber-950/40">
                            <div class="flex items-center justify-between gap-3">
                                <strong class="text-sm text-amber-700 dark:text-amber-300">Lluvias</strong>
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Media</span>
                            </div>
                            <p class="mt-1 text-xs text-amber-700/80 dark:text-amber-200/80">Huanchaco y zonas de baja elevación con riesgo de acumulación.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="flex items-center gap-2">
                        <Bell class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Resumen</p>
                    </div>
                    <ul class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <li>• {{ filterSummary.visible }} distritos visibles</li>
                        <li>• {{ filterSummary.alerts }} zonas con alertas</li>
                        <li>• {{ filterSummary.evidence }} evidencias registradas</li>
                        <li>• {{ filterSummary.help }} solicitudes abiertas</li>
                    </ul>
                </div>
            </aside>
        </section>
    </div>
</template>
