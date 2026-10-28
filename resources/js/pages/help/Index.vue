<script setup lang="ts">
import { Head, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { HandHelping, MapPin, ShieldAlert, Siren } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { sos as sosRoute } from '@/routes/need-help';
import {
    beginSosAction,
    completeSosAction,
    getPendingSosKey,
    getSosPendingStorageKey,
} from '@/lib/sos-idempotency';

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token ?? '');

const props = defineProps<{
    districts: Array<{
        id: number;
        name: string;
        slug: string;
        risk_level: string;
    }>;
    requests: Array<{
        district_name: string | null;
        help_type: string;
        urgency_level: string;
        status_label: string;
        created_at: string | null;
    }>;
    myRequests: Array<{
        id: number;
        created_at: string | null;
    }>;
    sosContactCount: number;
    sosEmailContactCount: number;
    sosFeedback: string | null;
    sosAlert: {
        id: number;
        delivery_status:
            | 'registered'
            | 'pending'
            | 'accepted'
            | 'partially_accepted'
            | 'uncertain'
            | 'partially_uncertain'
            | 'failed'
            | 'no_recipients'
            | 'unavailable';
        created_at: string | null;
        deliveries_count: number;
        accepted_count: number;
        failed_count: number;
        uncertain_count: number;
        pending_count: number;
    } | null;
}>();

const latitude = ref('');
const longitude = ref('');
const sosLocationMessage = ref('');
const pendingSosKey = ref<string | null>(null);
const requestingSosLocation = ref(false);

if (typeof window !== 'undefined') {
    try {
        pendingSosKey.value = getPendingSosKey(
            window.localStorage,
            page.props.auth.user.id,
            window.sessionStorage,
        );
    } catch {
        sosLocationMessage.value =
            'No se pudo leer la clave SOS pendiente del almacenamiento persistente. No continúes hasta que el navegador permita usarlo.';
    }
}

const sosForm = useForm<{
    idempotency_key: string;
    latitude: number | null;
    longitude: number | null;
}>({
    idempotency_key: pendingSosKey.value ?? '',
    latitude: null,
    longitude: null,
});

const syncPendingSosKey = (key: string | null) => {
    pendingSosKey.value = key;
    sosForm.idempotency_key = key ?? '';
};

const handleSosStorageChange = (event: StorageEvent) => {
    if (event.key === getSosPendingStorageKey(page.props.auth.user.id)) {
        syncPendingSosKey(event.newValue);
    }
};

onMounted(() => {
    window.addEventListener('storage', handleSosStorageChange);
});

onBeforeUnmount(() => {
    window.removeEventListener('storage', handleSosStorageChange);
});

const ensureSosActionKey = (): boolean => {
    try {
        const key = beginSosAction(
            window.localStorage,
            page.props.auth.user.id,
            () => crypto.randomUUID(),
        );
        pendingSosKey.value = key;
        sosForm.idempotency_key = key;

        return true;
    } catch {
        sosLocationMessage.value =
            'No se pudo guardar de forma segura la clave de esta solicitud en el navegador. El SOS no se enviará para evitar duplicados.';

        return false;
    }
};

const submitSos = () => {
    sosForm.clearErrors();
    sosForm.post(sosRoute.url(), {
        preserveScroll: true,
        onSuccess: () => {
            try {
                completeSosAction(
                    window.localStorage,
                    page.props.auth.user.id,
                    window.sessionStorage,
                );
                pendingSosKey.value = null;
                sosForm.idempotency_key = '';
            } catch {
                sosLocationMessage.value =
                    'El SOS fue registrado, pero el navegador no pudo liberar la clave de esta acción. No inicies otro SOS hasta que puedas limpiar el almacenamiento persistente.';
            }
        },
    });
};

const requestSosLocation = () => {
    if (sosForm.processing || requestingSosLocation.value) {
        return;
    }

    if (!ensureSosActionKey()) {
        return;
    }

    sosLocationMessage.value = '';
    sosForm.latitude = null;
    sosForm.longitude = null;

    if (!navigator.geolocation) {
        sosLocationMessage.value =
            'Este dispositivo no permite obtener GPS. El SOS se registrará sin coordenadas.';
        submitSos();
        return;
    }

    requestingSosLocation.value = true;
    sosLocationMessage.value = 'Solicitando permiso de ubicación…';
    navigator.geolocation.getCurrentPosition(
        (position) => {
            requestingSosLocation.value = false;
            sosForm.latitude = position.coords.latitude;
            sosForm.longitude = position.coords.longitude;
            sosLocationMessage.value =
                'Ubicación obtenida; registrando el SOS con coordenadas.';
            submitSos();
        },
        () => {
            requestingSosLocation.value = false;
            sosForm.latitude = null;
            sosForm.longitude = null;
            sosLocationMessage.value =
                'No se obtuvo permiso o ubicación. El SOS continuará sin coordenadas.';
            submitSos();
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
    );
};

const startNewSosAction = () => {
    if (sosForm.processing || requestingSosLocation.value) {
        return;
    }

    try {
        completeSosAction(
            window.localStorage,
            page.props.auth.user.id,
            window.sessionStorage,
        );
        pendingSosKey.value = null;
        sosForm.idempotency_key = '';
        requestSosLocation();
    } catch {
        sosLocationMessage.value =
            'No se pudo liberar la clave anterior. No se inició una nueva alerta SOS.';
    }
};

const requestLocation = () => {
    if (!navigator.geolocation) {
        return;
    }

    navigator.geolocation.getCurrentPosition((position) => {
        latitude.value = String(position.coords.latitude);
        longitude.value = String(position.coords.longitude);
    });
};

const sosStatusMessage = computed(() => {
    const alert = props.sosAlert;

    if (!alert) {
        return null;
    }

    switch (alert.delivery_status) {
        case 'registered':
            return 'SOS registrado; todavía no hay una notificación en cola.';
        case 'pending':
            return `Notificación pendiente: ${alert.pending_count}. Aceptadas por el proveedor: ${alert.accepted_count}; fallidas: ${alert.failed_count}.`;
        case 'accepted':
            return `El proveedor aceptó la notificación para ${alert.accepted_count} contacto(s). Esto no confirma entrega ni lectura.`;
        case 'partially_accepted':
            return `Resultado parcial: el proveedor aceptó ${alert.accepted_count} notificación(es) y ${alert.failed_count} fallaron. La aceptación no confirma entrega ni lectura.`;
        case 'uncertain':
            return `El resultado es incierto para ${alert.uncertain_count} notificación(es): no se puede confirmar si el proveedor las aceptó. No se reintentará fuera de la ventana segura.`;
        case 'partially_uncertain':
            return `Resultado parcial: aceptadas ${alert.accepted_count}, fallidas ${alert.failed_count} e inciertas ${alert.uncertain_count}. No se puede confirmar si las inciertas fueron aceptadas.`;
        case 'failed':
            return 'El SOS quedó registrado, pero el proveedor no aceptó ninguna notificación.';
        case 'no_recipients':
            return 'El SOS quedó registrado, pero no hay contactos con correo válido para notificar.';
        case 'unavailable':
            return 'El SOS quedó registrado, pero el esquema de notificaciones no está disponible. Contacta al equipo de soporte.';
    }
});

const { start: startStatusPolling, stop: stopStatusPolling } = usePoll(
    5000,
    { only: ['sosAlert'] },
    { autoStart: props.sosAlert?.delivery_status === 'pending' },
);

watch(
    () => props.sosAlert?.delivery_status,
    (status) => {
        if (status === 'pending') {
            startStatusPolling();
        } else {
            stopStatusPolling();
        }
    },
);

const urgencyClass: Record<string, string> = {
    low: 'bg-slate-100 text-slate-700',
    medium: 'bg-amber-100 text-amber-700',
    high: 'bg-orange-100 text-orange-700',
    critical: 'bg-red-100 text-red-700',
};
</script>

<template>
    <Head title="Necesito ayuda" />

    <div
        class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6"
    >
        <section
            class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950/70"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-rose-600 uppercase dark:text-rose-400"
                    >
                        Emergencia
                    </p>
                    <h1
                        class="mt-2 text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        Necesito ayuda
                    </h1>
                </div>
                <div
                    class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:border-rose-900/70 dark:bg-rose-950/50 dark:text-rose-300"
                >
                    <HandHelping class="h-3.5 w-3.5" />
                    Ayuda inmediata
                </div>
            </div>
        </section>

        <section
            class="rounded-2xl border-2 border-red-300 bg-red-50 p-4 shadow-sm sm:p-5 dark:border-red-900 dark:bg-red-950/30"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-red-700 uppercase dark:text-red-300"
                    >
                        Emergencia inmediata
                    </p>
                    <h2
                        class="mt-2 text-xl font-bold text-slate-900 dark:text-white"
                    >
                        Botón de pánico SOS
                    </h2>
                    <p
                        class="mt-2 max-w-2xl text-sm text-slate-700 dark:text-slate-200"
                    >
                        El SOS se registra aunque no tengas ubicación o correo
                        configurado. Si autorizas compartir ubicación, se
                        incluirá en el correo a tus contactos de confianza y la
                        procesará Resend. El correo se envía en segundo plano.
                    </p>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                        Contactos configurados: {{ props.sosContactCount }}; con
                        correo válido: {{ props.sosEmailContactCount }}. Los
                        datos de contacto no se publican.
                    </p>
                    <p
                        v-if="sosStatusMessage && props.sosAlert"
                        role="status"
                        aria-live="polite"
                        class="mt-3 rounded-lg border border-red-200 bg-white/70 p-3 text-sm font-medium text-red-900 dark:border-red-900 dark:bg-slate-950/70 dark:text-red-100"
                    >
                        SOS #{{ props.sosAlert.id }}:
                        {{ sosStatusMessage }}
                    </p>
                    <p
                        v-if="sosLocationMessage"
                        aria-live="polite"
                        class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200"
                    >
                        {{ sosLocationMessage }}
                    </p>
                    <p
                        v-if="pendingSosKey"
                        role="status"
                        class="mt-2 text-sm font-medium text-amber-800 dark:text-amber-200"
                    >
                        Hay una acción SOS sin confirmar. Su clave se conserva
                        en este navegador para reutilizarla tras recargar,
                        cerrar la pestaña o continuar desde otra pestaña de esta
                        cuenta.
                    </p>
                    <p
                        v-if="props.sosFeedback"
                        role="status"
                        class="mt-2 text-sm font-semibold text-red-800 dark:text-red-200"
                    >
                        {{ props.sosFeedback }}
                    </p>
                    <p
                        v-if="
                            sosForm.errors.latitude || sosForm.errors.longitude
                        "
                        role="alert"
                        class="mt-2 text-sm font-semibold text-red-800 dark:text-red-200"
                    >
                        No se pudo validar la ubicación. Revisa los permisos e
                        inténtalo otra vez.
                    </p>
                </div>
                <div class="flex shrink-0 flex-col gap-2">
                    <button
                        type="button"
                        :disabled="sosForm.processing || requestingSosLocation"
                        @click="requestSosLocation"
                        class="inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-red-700 px-6 py-3 text-base font-bold text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                    >
                        <Siren class="h-5 w-5" />
                        {{
                            sosForm.processing
                                ? 'Registrando SOS…'
                                : requestingSosLocation
                                  ? 'Obteniendo ubicación…'
                                  : pendingSosKey
                                    ? 'Reintentar SOS pendiente'
                                    : 'Registrar SOS'
                        }}
                    </button>
                    <button
                        v-if="pendingSosKey"
                        type="button"
                        :disabled="sosForm.processing || requestingSosLocation"
                        @click="startNewSosAction"
                        class="rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-800 dark:bg-slate-950 dark:text-red-200"
                    >
                        Iniciar otra alerta SOS (puede duplicar el aviso)
                    </button>
                </div>
            </div>
        </section>

        <section
            class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 shadow-sm sm:p-5 dark:border-rose-900/60 dark:bg-rose-950/20"
        >
            <div class="mb-4">
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-rose-700 uppercase dark:text-rose-300"
                >
                    Solicitud de apoyo
                </p>
                <h2
                    class="mt-2 text-xl font-bold text-slate-900 dark:text-white"
                >
                    Describe la situación para recibir ayuda
                </h2>
            </div>

            <form
                method="post"
                action="/help"
                class="grid gap-4 md:grid-cols-2"
            >
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="latitude" :value="latitude" />
                <input type="hidden" name="longitude" :value="longitude" />

                <label
                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                >
                    Distrito
                    <select
                        name="district_id"
                        required
                        class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option
                            v-for="district in props.districts"
                            :key="district.id"
                            :value="district.id"
                        >
                            {{ district.name }}
                        </option>
                    </select>
                </label>

                <label
                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                >
                    Tipo de ayuda
                    <select
                        name="help_type"
                        required
                        class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="medical">Médica</option>
                        <option value="rescue">Rescate</option>
                        <option value="evacuation">Evacuación</option>
                        <option value="food">Alimentos</option>
                        <option value="transport">Transporte</option>
                        <option value="other">Otro</option>
                    </select>
                </label>

                <label
                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                >
                    Urgencia
                    <select
                        name="urgency_level"
                        required
                        class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="low">Baja</option>
                        <option value="medium" selected>Media</option>
                        <option value="high">Alta</option>
                        <option value="critical">Crítica</option>
                    </select>
                </label>

                <div
                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                >
                    Ubicación
                    <div class="mt-2 flex min-w-0 flex-col gap-2 sm:flex-row">
                        <input
                            name="address_reference"
                            maxlength="255"
                            class="w-full min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                            placeholder="Referencia de la zona"
                        />
                        <button
                            type="button"
                            @click="requestLocation"
                            class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 sm:w-auto dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300"
                        >
                            <MapPin class="h-4 w-4" />
                            Ubicación
                        </button>
                    </div>
                </div>

                <label
                    class="text-sm font-medium text-slate-700 md:col-span-2 dark:text-slate-200"
                >
                    Descripción
                    <textarea
                        name="description"
                        required
                        maxlength="1000"
                        rows="4"
                        class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                        placeholder="Explica qué ocurre, cuántas personas están involucradas y si hay peligro inmediato."
                    />
                </label>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 md:col-span-2"
                >
                    <ShieldAlert class="h-4 w-4" />
                    Solicitar ayuda
                </button>
            </form>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
        >
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">
                Solicitudes recientes
            </h2>
            <div class="mt-4 space-y-3">
                <div
                    v-for="(requestItem, index) in props.requests"
                    :key="index"
                    class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="font-semibold text-slate-900 dark:text-white"
                            >
                                {{ requestItem.help_type }} ·
                                {{ requestItem.district_name }}
                            </p>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ requestItem.status_label }} ·
                                {{ requestItem.created_at }}
                            </p>
                        </div>
                        <span
                            :class="
                                urgencyClass[requestItem.urgency_level] ??
                                'bg-slate-100 text-slate-700'
                            "
                            class="rounded-full px-2 py-1 text-[10px] font-semibold tracking-wide uppercase"
                        >
                            {{ requestItem.urgency_level }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="props.myRequests.length"
            class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
        >
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">
                Mis solicitudes privadas
            </h2>
            <ul class="mt-4 space-y-2">
                <li
                    v-for="requestItem in props.myRequests"
                    :key="requestItem.id"
                >
                    <a
                        :href="`/help/${requestItem.id}`"
                        class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/50"
                    >
                        Ver detalles de la solicitud del
                        {{ requestItem.created_at }}
                    </a>
                </li>
            </ul>
        </section>
    </div>
</template>
