<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Bell, LoaderCircle, LocateFixed, MapPinned, Navigation, ShieldCheck } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { directionsUrl, distanceKm, useGeolocation } from '@/composables/useGeolocation';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mapa de Riesgo', href: '/map' }],
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

type SafeZone = {
    id: number;
    name: string;
    address: string;
    district_id: number;
    district_name: string | null;
    latitude: number;
    longitude: number;
    status: string;
    status_label: string;
    status_color: string;
    is_verified: boolean;
};

type ActiveAlert = {
    id: number;
    title: string;
    message: string;
    severity: 'info' | 'caution' | 'danger' | 'critical';
    district_id: number | null;
    district_name: string | null;
};

const props = defineProps<{
    districts: District[];
    safeZones: SafeZone[];
    activeAlerts: ActiveAlert[];
}>();

const geolocation = useGeolocation();
const { coords, lookup, isLocating, preferredDistrictId } = geolocation;
const safeZoneLayer = shallowRef<L.LayerGroup | null>(null);

const escapeHtml = (value: string | null | undefined) =>
    String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char] ?? char);

const currentDistrictId = computed(() => lookup.value?.district?.id ?? preferredDistrictId.value ?? null);
const currentDistrictName = computed(() => props.districts.find((district) => district.id === currentDistrictId.value)?.name ?? null);

const nearbyZones = computed(() => {
    const position = coords.value;

    if (!position) {
        return [];
    }

    return (props.safeZones ?? [])
        .map((zone) => ({ ...zone, distance: distanceKm(position, zone) }))
        .sort((a, b) => a.distance - b.distance)
        .slice(0, 5);
});

const relevantAlerts = computed(() => {
    const alerts = props.activeAlerts ?? [];

    if (!currentDistrictId.value) {
        return alerts;
    }

    return alerts.filter((alert) => alert.district_id === null || alert.district_id === currentDistrictId.value);
});

const severityStyles: Record<ActiveAlert['severity'], { box: string; label: string }> = {
    info: { box: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/80 dark:bg-sky-950/40 dark:text-sky-200', label: 'Informativa' },
    caution: { box: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/80 dark:bg-amber-950/40 dark:text-amber-200', label: 'Precaución' },
    danger: { box: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900/80 dark:bg-orange-950/40 dark:text-orange-200', label: 'Peligro' },
    critical: { box: 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/80 dark:bg-red-950/40 dark:text-red-200', label: 'Crítica' },
};

const renderSafeZones = () => {
    const map = mapInstance.value;

    if (!map) {
        return;
    }

    safeZoneLayer.value?.remove();
    const layer = L.layerGroup();

    (props.safeZones ?? []).forEach((zone) => {
        L.marker([zone.latitude, zone.longitude], {
            icon: L.divIcon({
                className: 'custom-marker',
                html: `<span style="background:${escapeHtml(zone.status_color)}; border:2px solid white; width:14px; height:14px; display:block; border-radius:4px; box-shadow:0 0 0 3px rgba(16,185,129,.35)"></span>`,
                iconSize: [14, 14],
                iconAnchor: [7, 7],
            }),
        })
            .bindPopup(`
                <div style="min-width:180px;">
                    <strong>${escapeHtml(zone.name)}</strong><br>
                    <small>${escapeHtml(zone.address)}</small><br>
                    <small>${escapeHtml(zone.status_label)}${zone.is_verified ? ' · Verificada' : ' · No verificada'}</small><br>
                    <a href="${directionsUrl(zone.latitude, zone.longitude)}" target="_blank" rel="noopener noreferrer">Cómo llegar</a>
                </div>
            `)
            .addTo(layer);
    });

    layer.addTo(map);
    safeZoneLayer.value = layer;
};

const districts = computed(() => props.districts ?? []);
const mapContainer = ref<HTMLElement | null>(null);
const statusMessage = ref('Cargando mapa…');
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
                <strong>${escapeHtml(district.name)}</strong><br>
                <span style="color:${style.color}; font-weight:600;">${style.label}</span><br>
                <small>${escapeHtml(district.summary)}</small><br>
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

const showUserOnMap = (latitude: number, longitude: number, fly = true) => {
    if (fly) {
        mapInstance.value?.flyTo([latitude, longitude], 14, { duration: 1.5 });
    }

    userMarker.value = L.circleMarker([latitude, longitude], {
        radius: 10,
        color: '#0f172a',
        weight: 2,
        fillColor: '#22c55e',
        fillOpacity: 0.9,
    }).bindPopup('Tu ubicación actual');

    renderDistrictMarkers();
};

const describeLookup = () => {
    const result = lookup.value;

    if (!result) {
        statusMessage.value = 'Ubicación actual detectada.';
    } else if (!result.district) {
        statusMessage.value = 'Tu ubicación parece estar fuera de la provincia de Trujillo.';
    } else {
        statusMessage.value = result.approximate
            ? `Ubicación detectada. Distrito aproximado: ${result.district.name}.`
            : `Ubicación detectada en el distrito ${result.district.name}.`;
    }
};

const requestUserLocation = async () => {
    const position = await geolocation.locate({ force: true });

    if (!position) {
        statusMessage.value = `${geolocation.statusMessage.value} Puedes seguir usando el mapa con la vista general.`;

        return;
    }

    showUserOnMap(position.latitude, position.longitude);
    await geolocation.lookupDistrict();
    describeLookup();
};

const focusOnTrujillo = () => {
    mapInstance.value?.flyTo([-8.116, -79.03], 12, { duration: 1.5 });
    statusMessage.value = 'Vista general de Trujillo cargada.';
};

watch(filters, () => {
    renderDistrictMarkers();
}, { deep: true });

onMounted(async () => {
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
    renderSafeZones();

    // Solo se ubica automáticamente si el permiso ya fue concedido; en otro caso
    // se espera a que el usuario pulse "Mi ubicación".
    const position = await geolocation.locateIfPermitted();

    if (position) {
        showUserOnMap(position.latitude, position.longitude);
        await geolocation.lookupDistrict();
        describeLookup();
    } else {
        statusMessage.value = geolocation.status.value === 'denied'
            ? 'Permiso de ubicación denegado. Puedes usar el mapa y elegir tu distrito manualmente.'
            : 'Pulsa "Mi ubicación" para ver tu posición y las zonas seguras cercanas.';
    }
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
                            :disabled="isLocating"
                            @click="requestUserLocation"
                            class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-60 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300 dark:hover:bg-emerald-900/70"
                        >
                            <LoaderCircle v-if="isLocating" class="h-4 w-4 animate-spin" />
                            <LocateFixed v-else class="h-4 w-4" />
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

                <div role="status" aria-live="polite" class="mb-3 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-700 dark:border-sky-900/80 dark:bg-sky-950/40 dark:text-sky-300">
                    {{ statusMessage }}
                </div>

                <div ref="mapContainer" class="h-[420px] w-full sm:h-[520px] overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-800" />
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
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Zonas seguras cercanas</p>
                    </div>
                    <div v-if="nearbyZones.length" class="mt-4 space-y-2">
                        <div v-for="zone in nearbyZones" :key="zone.id" class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <div class="flex items-start justify-between gap-2">
                                <strong class="text-sm text-slate-800 dark:text-slate-100">{{ zone.name }}</strong>
                                <span class="shrink-0 text-xs font-semibold text-emerald-700 dark:text-emerald-300">{{ zone.distance.toFixed(1) }} km</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ zone.address }} · {{ zone.status_label }}{{ zone.is_verified ? '' : ' · No verificada' }}</p>
                            <a :href="directionsUrl(zone.latitude, zone.longitude)" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-sky-700 hover:underline dark:text-sky-300">
                                <Navigation class="h-3.5 w-3.5" /> Cómo llegar
                            </a>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Distancias en línea recta. Verifica el estado del lugar con las autoridades antes de desplazarte.</p>
                    </div>
                    <p v-else-if="coords" class="mt-4 text-sm text-slate-500 dark:text-slate-400">No hay zonas seguras registradas en RESGUARDA.</p>
                    <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">Activa tu ubicación para ver las zonas seguras más cercanas.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                        Alertas activas{{ currentDistrictName ? ` · ${currentDistrictName}` : '' }}
                    </p>
                    <div v-if="relevantAlerts.length" class="mt-4 space-y-3">
                        <div v-for="alert in relevantAlerts" :key="alert.id" class="rounded-xl border p-3" :class="severityStyles[alert.severity]?.box ?? severityStyles.caution.box">
                            <div class="flex items-center justify-between gap-3">
                                <strong class="text-sm">{{ alert.title }}</strong>
                                <span class="rounded-full bg-white/60 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide dark:bg-black/20">{{ severityStyles[alert.severity]?.label ?? alert.severity }}</span>
                            </div>
                            <p class="mt-1 text-xs opacity-80">{{ alert.district_name ?? 'Toda la provincia' }} · {{ alert.message }}</p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">No hay alertas activas registradas{{ currentDistrictName ? ` para ${currentDistrictName}` : '' }}.</p>
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
