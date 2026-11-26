<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { Bell, LoaderCircle, LocateFixed, MapPinned, MapPinPlus, Navigation, ShieldCheck } from '@lucide/vue';
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

type MapReport = {
    id: number;
    title: string;
    incident_type: string;
    severity: 'baja' | 'media' | 'alta' | 'critica';
    latitude: number;
    longitude: number;
    url: string;
};

type CrowdHotspot = {
    latitude: number;
    longitude: number;
    level: 'baja' | 'media' | 'alta';
    level_label: string;
    weight: number;
    sources: string[];
};

type OpenSpace = {
    id: string;
    name: string;
    type: 'assembly_point' | 'park' | 'square';
    type_label: string;
    latitude: number;
    longitude: number;
};

const props = defineProps<{
    districts: District[];
    safeZones: SafeZone[];
    activeAlerts: ActiveAlert[];
    reports: MapReport[];
    crowds: CrowdHotspot[];
    trafficEnabled: boolean;
    updatedAt: string;
}>();

/** Colores del tráfico en tiempo real según la velocidad actual de cada calle. */
const trafficLegend = [
    { color: '#22c55e', label: 'Fluido' },
    { color: '#f59e0b', label: 'Moderado' },
    { color: '#ef4444', label: 'Congestionado' },
    { color: '#7f1d1d', label: 'Detenido o cerrado' },
];

const crowdStyles: Record<CrowdHotspot['level'], { color: string; label: string; radius: number }> = {
    baja: { color: '#facc15', label: 'Baja', radius: 220 },
    media: { color: '#f97316', label: 'Media', radius: 320 },
    alta: { color: '#dc2626', label: 'Alta', radius: 420 },
};

type CrowdStreets = {
    latitude: number;
    longitude: number;
    streets: Array<Array<[number, number]>> | null;
};

const crowdStreets = ref<CrowdStreets[]>([]);
const openSpaces = ref<OpenSpace[]>([]);
const openSpacesStatus = ref<'loading' | 'ready' | 'error'>('loading');
const lastUpdate = computed(() => new Date(props.updatedAt).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }));

// Las publicaciones, zonas seguras y aglomeraciones se actualizan solas cada minuto.
usePoll(60000, { only: ['reports', 'safeZones', 'crowds', 'activeAlerts', 'updatedAt'] });

/** Colores de las publicaciones según su gravedad; las zonas seguras van en verde. */
const reportStyles: Record<MapReport['severity'] | 'segura', { color: string; label: string }> = {
    segura: { color: '#10b981', label: 'Zona segura' },
    baja: { color: '#0ea5e9', label: 'Gravedad baja' },
    media: { color: '#f59e0b', label: 'Gravedad media' },
    alta: { color: '#f97316', label: 'Gravedad alta' },
    critica: { color: '#dc2626', label: 'Gravedad crítica' },
};

const reportStyle = (report: MapReport) =>
    report.incident_type === 'zona_segura' ? reportStyles.segura : (reportStyles[report.severity] ?? reportStyles.media);

const publishUrl = (latitude: number, longitude: number) =>
    `/reports?lat=${latitude.toFixed(6)}&lng=${longitude.toFixed(6)}#nueva-publicacion`;

const geolocation = useGeolocation();
const { coords, lookup, isLocating, preferredDistrictId } = geolocation;
const safeZoneLayer = shallowRef<L.LayerGroup | null>(null);
const reportLayer = shallowRef<L.LayerGroup | null>(null);
const reportMarkers = new Map<number, L.Marker>();
const crowdLayer = shallowRef<L.LayerGroup | null>(null);
const openSpaceLayer = shallowRef<L.LayerGroup | null>(null);
const trafficLayer = shallowRef<L.TileLayer | null>(null);
let trafficTimer: ReturnType<typeof setInterval> | null = null;

/**
 * Tráfico en tiempo real: el servidor reenvía los mosaicos del proveedor y
 * se recargan cada dos minutos para reflejar el estado actual de las calles.
 */
const renderTraffic = () => {
    const map = mapInstance.value;

    if (!map || !props.trafficEnabled) {
        return;
    }

    if (!trafficLayer.value) {
        trafficLayer.value = L.tileLayer(`/map/traffic/{z}/{x}/{y}.png?t=${Date.now()}`, {
            minZoom: 3, maxZoom: 18, opacity: 0.85, zIndex: 5, attribution: 'Tráfico &copy; TomTom',
        });
    }

    if (filters.value.traffic && !map.hasLayer(trafficLayer.value)) {
        trafficLayer.value.addTo(map);
    } else if (!filters.value.traffic && map.hasLayer(trafficLayer.value)) {
        trafficLayer.value.remove();
    }
};

/**
 * Aglomeraciones: las calles y pasajes reales alrededor de cada zona se pintan
 * con el color de su nivel. Si aún no hay calles descargadas para una zona, se
 * marca con un halo suave mientras llegan.
 */
const renderCrowds = () => {
    const map = mapInstance.value;

    if (!map) {
        return;
    }

    crowdLayer.value?.remove();
    const layer = L.layerGroup();

    if (filters.value.crowds) {
        const withStreets = crowdStreets.value ?? [];

        (props.crowds ?? []).forEach((crowd) => {
            const style = crowdStyles[crowd.level] ?? crowdStyles.baja;
            const streets = withStreets.find((item) => item.latitude === crowd.latitude && item.longitude === crowd.longitude)?.streets;
            const tooltip = `${crowd.level_label} · ${escapeHtml(crowd.sources.join(', '))}`;

            if (streets && streets.length) {
                streets.forEach((line) => {
                    L.polyline(line, { color: '#ffffff', weight: 9, opacity: 0.75, lineCap: 'round', lineJoin: 'round', interactive: false }).addTo(layer);
                    L.polyline(line, { color: style.color, weight: 5, opacity: 0.95, lineCap: 'round', lineJoin: 'round', bubblingMouseEvents: false })
                        .bindTooltip(tooltip, { sticky: true })
                        .addTo(layer);
                });

                return;
            }

            L.circle([crowd.latitude, crowd.longitude], {
                radius: style.radius, color: style.color, weight: 1, dashArray: '4 4', fillColor: style.color, fillOpacity: 0.12, bubblingMouseEvents: false,
            })
                .bindTooltip(`${tooltip} · cargando calles…`, { direction: 'top' })
                .addTo(layer);
        });
    }

    layer.addTo(map);
    crowdLayer.value = layer;
};

let streetAttempts = 0;
let streetTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * El servidor descarga las calles de una zona por consulta; mientras falten
 * zonas se vuelve a pedir, con un máximo de intentos.
 */
const loadCrowdStreets = async () => {
    if (streetTimer) {
        clearTimeout(streetTimer);
        streetTimer = null;
    }

    try {
        const response = await fetch('/map/crowd-streets', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

        if (response.ok) {
            crowdStreets.value = ((await response.json()) as { data: CrowdStreets[] }).data ?? [];
            renderCrowds();
        }
    } catch {
        // Sin conexión con el servidor de calles se mantienen los halos.
    }

    if (crowdStreets.value.some((item) => item.streets === null) && streetAttempts < 30) {
        streetAttempts++;
        streetTimer = setTimeout(() => void loadCrowdStreets(), 5000);
    }
};

/** Espacios abiertos reales de OpenStreetMap: parques, plazas y puntos de reunión. */
const renderOpenSpaces = () => {
    const map = mapInstance.value;

    if (!map) {
        return;
    }

    openSpaceLayer.value?.remove();
    const layer = L.layerGroup();

    if (filters.value.openSpaces) {
        openSpaces.value.forEach((space) => {
            const official = space.type === 'assembly_point';

            L.circleMarker([space.latitude, space.longitude], {
                radius: official ? 7 : 5, color: '#ffffff', weight: 1.5, fillColor: official ? '#047857' : '#34d399', fillOpacity: 0.95, bubblingMouseEvents: false,
            })
                .bindTooltip(`${escapeHtml(space.name)} · ${space.type_label}`, { direction: 'top' })
                .bindPopup(`
                    <div style="min-width:170px;">
                        <strong>${escapeHtml(space.name)}</strong><br>
                        <small>${space.type_label} · OpenStreetMap</small><br>
                        <small>Espacio abierto; verifica su estado antes de dirigirte.</small><br>
                        <a href="${directionsUrl(space.latitude, space.longitude)}" target="_blank" rel="noopener noreferrer">Cómo llegar</a>
                    </div>
                `)
                .addTo(layer);
        });
    }

    layer.addTo(map);
    openSpaceLayer.value = layer;
};

const loadOpenSpaces = async () => {
    try {
        const response = await fetch('/map/open-spaces', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

        if (!response.ok) {
            throw new Error('open spaces');
        }

        openSpaces.value = ((await response.json()) as { data: OpenSpace[] }).data ?? [];
        openSpacesStatus.value = 'ready';
        renderOpenSpaces();
    } catch {
        openSpacesStatus.value = 'error';
    }
};

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

    const zones = filters.value.safeZones ? (props.safeZones ?? []) : [];

    zones.forEach((zone) => {
        L.marker([zone.latitude, zone.longitude], {
            title: zone.name,
            icon: L.divIcon({
                className: 'custom-marker',
                html: `<span style="background:${escapeHtml(zone.status_color)}; border:2px solid white; width:16px; height:16px; display:block; border-radius:4px; cursor:pointer; box-shadow:0 0 0 3px rgba(16,185,129,.35)"></span>`,
                iconSize: [16, 16],
                iconAnchor: [8, 8],
            }),
        })
            .bindTooltip(`${escapeHtml(zone.name)} · Zona segura · ${escapeHtml(zone.status_label)}`, { direction: 'top', offset: [0, -8] })
            .on('click', () => router.visit(`/safe-zones/${zone.id}`))
            .addTo(layer);
    });

    layer.addTo(map);
    safeZoneLayer.value = layer;
};

/**
 * Cada publicación es un punto del color de su gravedad; al tocarlo se abre
 * la publicación correspondiente.
 */
const renderReports = () => {
    const map = mapInstance.value;

    if (!map) {
        return;
    }

    reportLayer.value?.remove();
    reportMarkers.clear();
    const layer = L.layerGroup();

    if (filters.value.reports) {
        (props.reports ?? []).forEach((report) => {
            const style = reportStyle(report);

            const marker = L.marker([report.latitude, report.longitude], {
                title: report.title,
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `<span style="background:${style.color}; border:2px solid white; width:16px; height:16px; display:block; border-radius:9999px; cursor:pointer; box-shadow:0 1px 4px rgba(0,0,0,.35)"></span>`,
                    iconSize: [16, 16],
                    iconAnchor: [8, 8],
                }),
            })
                .bindTooltip(`${escapeHtml(report.title)} · ${style.label}`, { direction: 'top', offset: [0, -8] })
                .on('click', () => router.visit(report.url))
                .addTo(layer);
            reportMarkers.set(report.id, marker);
        });
    }

    layer.addTo(map);
    reportLayer.value = layer;
};

/** Al tocar un lugar vacío del mapa se ofrece publicar allí. */
const offerPublishHere = (event: L.LeafletMouseEvent) => {
    const map = mapInstance.value;

    if (!map) {
        return;
    }

    const content = document.createElement('div');
    content.style.minWidth = '160px';
    content.innerHTML = '<small>¿Ocurre algo aquí o es una zona segura?</small><br>';

    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Publicar aquí';
    button.style.cssText = 'margin-top:6px;padding:6px 10px;border-radius:8px;background:#ea580c;color:white;font-weight:600;font-size:12px;';
    button.addEventListener('click', () => router.visit(publishUrl(event.latlng.lat, event.latlng.lng)));
    content.appendChild(button);

    L.popup().setLatLng(event.latlng).setContent(content).openOn(map);
};

const publishAtMyLocation = async () => {
    const position = coords.value ?? (await geolocation.locate({ force: true }));

    if (!position) {
        statusMessage.value = `${geolocation.statusMessage.value} Toca un punto del mapa para publicar allí.`;

        return;
    }

    router.visit(publishUrl(position.latitude, position.longitude));
};

const mapContainer = ref<HTMLElement | null>(null);
const statusMessage = ref('Cargando mapa…');
const mapInstance = shallowRef<L.Map | null>(null);
const tileLayer = shallowRef<L.TileLayer | null>(null);
const userMarker = shallowRef<L.CircleMarker | null>(null);
const filters = ref({
    reports: true,
    safeZones: true,
    traffic: true,
    crowds: true,
    openSpaces: true,
});

const filterSummary = computed(() => ({
    reports: (props.reports ?? []).length,
    safeZones: (props.safeZones ?? []).length,
    evidence: (props.districts ?? []).reduce((total, district) => total + district.evidence, 0),
    help: (props.districts ?? []).reduce((total, district) => total + district.help, 0),
}));

/** Solo se dibujan publicaciones; este marcador muestra dónde está la persona. */
const renderUserMarker = () => {
    const map = mapInstance.value;

    if (!map || !userMarker.value) {
        return;
    }

    if (!map.hasLayer(userMarker.value)) {
        userMarker.value.addTo(map);
    }
};

const showUserOnMap = (latitude: number, longitude: number, fly = true) => {
    if (fly) {
        mapInstance.value?.flyTo([latitude, longitude], 14, { duration: 1.5 });
    }

    userMarker.value?.remove();
    userMarker.value = L.circleMarker([latitude, longitude], {
        radius: 10,
        color: '#0f172a',
        weight: 2,
        fillColor: '#22c55e',
        fillOpacity: 0.9,
    }).bindTooltip('Tu ubicación actual');

    renderUserMarker();
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
    renderSafeZones();
    renderReports();
    renderCrowds();
    renderOpenSpaces();
    renderTraffic();
}, { deep: true });

// Al recibir datos nuevos del servidor se vuelven a dibujar las capas.
watch(() => [props.reports, props.safeZones, props.crowds], () => {
    renderSafeZones();
    renderReports();
    renderCrowds();
    void loadCrowdStreets();
});

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

    renderTraffic();
    renderCrowds();
    renderSafeZones();
    renderReports();
    map.on('click', offerPublishHere);
    void loadOpenSpaces().then(() => loadCrowdStreets());

    if (props.trafficEnabled) {
        trafficTimer = setInterval(() => trafficLayer.value?.setUrl(`/map/traffic/{z}/{x}/{y}.png?t=${Date.now()}`), 120000);
    }

    const focusedReport = props.reports?.find((report) => report.id === Number(new URLSearchParams(window.location.search).get('report')));

    if (focusedReport) {
        map.setView([focusedReport.latitude, focusedReport.longitude], 17);
        reportMarkers.get(focusedReport.id)?.openTooltip();
        statusMessage.value = `Mostrando: ${focusedReport.title}.`;

        return;
    }

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
    if (trafficTimer) {
        clearInterval(trafficTimer);
    }

    if (streetTimer) {
        clearTimeout(streetTimer);
    }

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

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            :disabled="isLocating"
                            @click="publishAtMyLocation"
                            class="inline-flex items-center gap-2 rounded-lg bg-orange-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-orange-700 disabled:opacity-60"
                        >
                            <MapPinPlus class="h-4 w-4" />
                            Publicar aquí
                        </button>
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

                <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">Toca un punto de color para abrir su publicación, o cualquier lugar del mapa para publicar allí.</p>

                <div ref="mapContainer" class="h-[420px] w-full sm:h-[520px] overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-800" />
            </div>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Filtros</p>
                    <div class="mt-4 space-y-3">
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
                            <span class="text-sm text-slate-700 dark:text-slate-200">Publicaciones</span>
                            <input v-model="filters.reports" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
                            <span class="text-sm text-slate-700 dark:text-slate-200">Zonas seguras</span>
                            <input v-model="filters.safeZones" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80" :class="trafficEnabled ? '' : 'opacity-60'">
                            <span class="text-sm text-slate-700 dark:text-slate-200">Tráfico en tiempo real</span>
                            <input v-model="filters.traffic" type="checkbox" :disabled="!trafficEnabled" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
                            <span class="text-sm text-slate-700 dark:text-slate-200">Aglomeración</span>
                            <input v-model="filters.crowds" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
                            <span class="text-sm text-slate-700 dark:text-slate-200">Espacios abiertos</span>
                            <input v-model="filters.openSpaces" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        </label>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Leyenda en vivo</p>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300">
                            <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500" /> Act. {{ lastUpdate }}
                        </span>
                    </div>

                    <p class="mt-3 text-xs font-semibold text-slate-700 dark:text-slate-200">Tráfico (color de las calles)</p>
                    <div v-if="trafficEnabled" class="mt-2 grid grid-cols-2 gap-1.5">
                        <div v-for="item in trafficLegend" :key="item.label" class="flex items-center gap-2">
                            <span class="h-1.5 w-6 shrink-0 rounded-full" :style="`background:${item.color}`" />
                            <span class="text-xs text-slate-600 dark:text-slate-300">{{ item.label }}</span>
                        </div>
                    </div>
                    <p v-else class="mt-1 text-xs text-amber-700 dark:text-amber-300">No disponible: falta configurar la clave del proveedor de tráfico.</p>

                    <p class="mt-3 text-xs font-semibold text-slate-700 dark:text-slate-200">Aglomeración (color de calles y pasajes)</p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <div v-for="(style, key) in crowdStyles" :key="key" class="flex items-center gap-1.5">
                            <span class="h-1.5 w-6 rounded-full ring-2 ring-white" :style="`background:${style.color}`" />
                            <span class="text-xs text-slate-600 dark:text-slate-300">{{ style.label }}</span>
                        </div>
                    </div>

                    <p class="mt-3 text-xs font-semibold text-slate-700 dark:text-slate-200">Lugares</p>
                    <div class="mt-2 space-y-1.5">
                        <div class="flex items-center gap-2"><span class="h-3 w-3 shrink-0 rounded-sm bg-emerald-500" /><span class="text-xs text-slate-600 dark:text-slate-300">Zona segura registrada (con aforo)</span></div>
                        <div class="flex items-center gap-2"><span class="h-3 w-3 shrink-0 rounded-full bg-emerald-700 ring-2 ring-white" /><span class="text-xs text-slate-600 dark:text-slate-300">Punto de reunión oficial</span></div>
                        <div class="flex items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-400 ring-2 ring-white" /><span class="text-xs text-slate-600 dark:text-slate-300">Parque o plaza (espacio abierto)</span></div>
                    </div>

                    <p class="mt-3 border-t border-slate-200 pt-2 text-[11px] leading-relaxed text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        Tráfico: TomTom, se actualiza cada 2 min. Aglomeración: estimada con el aforo de las zonas seguras y los reportes, evidencias y pedidos de ayuda de las últimas 6 h. Espacios abiertos: OpenStreetMap{{ openSpacesStatus === 'ready' ? ` (${openSpaces.length})` : openSpacesStatus === 'loading' ? ' (cargando…)' : ' (no disponible)' }}.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Publicaciones</p>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div v-for="(style, key) in reportStyles" :key="key" class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-900/80">
                            <span class="h-3 w-3 shrink-0 rounded-full" :style="`background:${style.color}`" />
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ style.label }}</span>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-900/80">
                        <span class="h-3 w-3 shrink-0 rounded-sm bg-emerald-500" />
                        <span class="text-xs font-medium text-slate-700 dark:text-slate-200">Zona segura registrada (cuadrado)</span>
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
                        <li>• {{ filterSummary.reports }} publicaciones en el mapa</li>
                        <li>• {{ filterSummary.safeZones }} zonas seguras</li>
                        <li>• {{ filterSummary.evidence }} evidencias registradas</li>
                        <li>• {{ filterSummary.help }} solicitudes abiertas</li>
                    </ul>
                </div>
            </aside>
        </section>
    </div>
</template>
