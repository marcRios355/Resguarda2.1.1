import { computed, readonly, ref } from 'vue';

export type GeolocationStatus =
    | 'idle'
    | 'locating'
    | 'granted'
    | 'denied'
    | 'unavailable'
    | 'timeout'
    | 'unsupported';

export type Coordinates = {
    latitude: number;
    longitude: number;
    accuracy: number;
};

export type DistrictLookup = {
    district: { id: number; name: string; slug: string } | null;
    method: 'boundary' | 'nearest_centroid' | 'outside';
    approximate: boolean;
    distance_km: number | null;
    safe_zones: NearbySafeZone[];
};

export type NearbySafeZone = {
    id: number;
    name: string;
    address: string;
    district_name: string | null;
    latitude: number;
    longitude: number;
    status: string;
    status_label: string;
    capacity: number;
    current_occupancy: number;
    is_verified: boolean;
    distance_km: number;
};

const PREFERRED_DISTRICT_KEY = 'resguarda.preferred-district';
const FRESHNESS_MS = 2 * 60 * 1000;

/*
 * Estado compartido por toda la aplicación: la ubicación solo vive en memoria
 * durante la sesión del navegador y nunca se guarda en el almacenamiento local.
 */
const coords = ref<Coordinates | null>(null);
const status = ref<GeolocationStatus>('idle');
const updatedAt = ref<number | null>(null);
const lookup = ref<DistrictLookup | null>(null);
const lookupError = ref('');
const preferredDistrictId = ref<number | null>(readPreferredDistrict());
let pending: Promise<Coordinates | null> | null = null;
let lookupPending: Promise<DistrictLookup | null> | null = null;

function readPreferredDistrict(): number | null {
    try {
        const value = window.localStorage.getItem(PREFERRED_DISTRICT_KEY);

        return value ? Number(value) || null : null;
    } catch {
        return null;
    }
}

const messages: Record<GeolocationStatus, string> = {
    idle: 'Ubicación no solicitada.',
    locating: 'Obteniendo tu ubicación…',
    granted: 'Ubicación detectada.',
    denied: 'Permiso de ubicación denegado. Puedes elegir tu distrito manualmente.',
    unavailable:
        'No se pudo determinar tu ubicación. Revisa el GPS o la conexión e inténtalo de nuevo.',
    timeout: 'La ubicación tardó demasiado en responder. Inténtalo de nuevo.',
    unsupported:
        'Tu navegador no permite obtener la ubicación. Elige tu distrito manualmente.',
};

/**
 * Distancia en línea recta en kilómetros (Haversine).
 */
export function distanceKm(
    from: { latitude: number; longitude: number },
    to: { latitude: number; longitude: number },
): number {
    const toRadians = (value: number) => (value * Math.PI) / 180;
    const latitudeDelta = toRadians(to.latitude - from.latitude);
    const longitudeDelta = toRadians(to.longitude - from.longitude);
    const a =
        Math.sin(latitudeDelta / 2) ** 2 +
        Math.cos(toRadians(from.latitude)) *
            Math.cos(toRadians(to.latitude)) *
            Math.sin(longitudeDelta / 2) ** 2;

    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

/**
 * Enlace de indicaciones hacia un destino. Solo se envía el destino: el
 * servicio de mapas usa la posición del propio dispositivo como origen.
 */
export function directionsUrl(latitude: number, longitude: number): string {
    return `https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}&travelmode=walking`;
}

export function useGeolocation() {
    const isFresh = () =>
        coords.value !== null &&
        updatedAt.value !== null &&
        Date.now() - updatedAt.value < FRESHNESS_MS;

    /**
     * Solicita la ubicación solo cuando el usuario lo pide o cuando el permiso
     * ya fue concedido antes. Reutiliza una lectura reciente para no repetir
     * solicitudes ni consumir batería.
     */
    const locate = (
        options: { force?: boolean } = {},
    ): Promise<Coordinates | null> => {
        if (!options.force && isFresh()) {
            return Promise.resolve(coords.value);
        }

        if (pending) {
            return pending;
        }

        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            status.value = 'unsupported';

            return Promise.resolve(null);
        }

        status.value = 'locating';
        pending = new Promise<Coordinates | null>((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    coords.value = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                    };
                    updatedAt.value = Date.now();
                    status.value = 'granted';
                    lookup.value = null;
                    resolve(coords.value);
                },
                (error) => {
                    status.value =
                        error.code === error.PERMISSION_DENIED
                            ? 'denied'
                            : error.code === error.TIMEOUT
                              ? 'timeout'
                              : 'unavailable';
                    resolve(null);
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
            );
        }).finally(() => {
            pending = null;
        });

        return pending;
    };

    /**
     * Obtiene la ubicación sin mostrar el diálogo del navegador: solo actúa si
     * el usuario ya concedió el permiso en una visita anterior.
     */
    const locateIfPermitted = async (): Promise<Coordinates | null> => {
        if (isFresh()) {
            return coords.value;
        }

        try {
            const permission = await navigator.permissions?.query({
                name: 'geolocation',
            });

            if (permission?.state === 'granted') {
                return locate();
            }

            if (permission?.state === 'denied') {
                status.value = 'denied';
            }
        } catch {
            // La API de permisos no está disponible (por ejemplo, Safari antiguo).
        }

        return null;
    };

    /**
     * Consulta al servidor el distrito y las zonas seguras cercanas. Las
     * coordenadas no se almacenan en el servidor.
     */
    const lookupDistrict = async (): Promise<DistrictLookup | null> => {
        if (!coords.value) {
            return null;
        }

        if (lookup.value) {
            return lookup.value;
        }

        if (lookupPending) {
            return lookupPending;
        }

        lookupError.value = '';
        const params = new URLSearchParams({
            latitude: String(coords.value.latitude),
            longitude: String(coords.value.longitude),
        });

        lookupPending = fetch(`/location/lookup?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('lookup failed');
                }

                lookup.value = (await response.json()) as DistrictLookup;

                return lookup.value;
            })
            .catch(() => {
                lookupError.value =
                    'No se pudo identificar tu distrito en este momento. Inténtalo de nuevo o elígelo manualmente.';

                return null;
            })
            .finally(() => {
                lookupPending = null;
            });

        return lookupPending;
    };

    const setPreferredDistrict = (districtId: number | null) => {
        preferredDistrictId.value = districtId;

        try {
            if (districtId) {
                window.localStorage.setItem(
                    PREFERRED_DISTRICT_KEY,
                    String(districtId),
                );
            } else {
                window.localStorage.removeItem(PREFERRED_DISTRICT_KEY);
            }
        } catch {
            // El almacenamiento local puede estar bloqueado; la preferencia queda en memoria.
        }
    };

    const forget = () => {
        coords.value = null;
        updatedAt.value = null;
        lookup.value = null;
        status.value = 'idle';
    };

    return {
        coords: readonly(coords),
        status: readonly(status),
        lookup: readonly(lookup),
        lookupError: readonly(lookupError),
        preferredDistrictId: readonly(preferredDistrictId),
        statusMessage: computed(() => messages[status.value]),
        isLocating: computed(() => status.value === 'locating'),
        hasLocation: computed(() => coords.value !== null),
        locate,
        locateIfPermitted,
        lookupDistrict,
        setPreferredDistrict,
        forget,
    };
}
