<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import {
    index as sosDeliveriesIndex,
    resolve as resolveSosDelivery,
} from '@/routes/admin/sos-deliveries';

const props = defineProps<{
    schemaReady: boolean;
    status: string | null;
    error: string | null;
    deliveries: Array<{
        id: number;
        alert_id: number;
        owner_name: string;
        recipient_email: string;
        failure_code: string | null;
        attempt_count: number;
        first_attempted_at: string | null;
    }>;
}>();

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token ?? '');
const outcomes = reactive<Record<number, string>>({});

const confirmResolution = (event: SubmitEvent) => {
    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement)) {
        event.preventDefault();
        return;
    }

    const outcome = new FormData(form).get('outcome');
    const message =
        outcome === 'accepted'
            ? 'Confirma que verificaste la aceptación en Resend. Esto no confirma entrega o lectura.'
            : 'Confirma que verificaste que Resend no aceptó este mensaje. No se volverá a enviar automáticamente.';

    if (!window.confirm(message)) {
        event.preventDefault();
    }
};
</script>

<template>
    <Head title="Revisión de correos SOS" />

    <div
        class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6"
    >
        <section
            class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950/70"
        >
            <p
                class="text-xs font-semibold tracking-[0.2em] text-amber-600 uppercase dark:text-amber-400"
            >
                Administración
            </p>
            <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                Revisión manual de notificaciones SOS inciertas
            </h1>
            <p
                class="mt-3 max-w-3xl text-sm text-slate-700 dark:text-slate-300"
            >
                Verifica cada entrega en el panel de Resend antes de resolverla.
                Un resultado incierto no se reenvía automáticamente porque el
                proveedor pudo haber aceptado el mensaje. La opción «No
                aceptado» solo registra una verificación manual; no vuelve a
                encolar el correo.
            </p>
            <Link
                :href="sosDeliveriesIndex.url()"
                class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-900"
            >
                Actualizar lista
            </Link>
        </section>

        <p
            v-if="props.status"
            role="status"
            class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100"
        >
            {{ props.status }}
        </p>
        <p
            v-if="props.error"
            role="alert"
            class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100"
        >
            {{ props.error }}
        </p>

        <section
            v-if="!props.schemaReady"
            role="status"
            class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
        >
            La revisión no está disponible hasta aplicar la migración de
            seguimiento manual de entregas SOS.
        </section>

        <section
            v-else-if="!props.deliveries.length"
            class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300"
        >
            No hay entregas SOS pendientes de revisión manual.
        </section>

        <section
            v-for="delivery in props.deliveries"
            :key="delivery.id"
            class="rounded-2xl border border-amber-200 bg-white/90 p-4 shadow-sm sm:p-5 dark:border-amber-900/70 dark:bg-slate-950/70"
        >
            <div
                class="grid gap-3 text-sm text-slate-700 sm:grid-cols-2 dark:text-slate-300"
            >
                <p>
                    <span class="font-semibold">Alerta:</span> #{{
                        delivery.alert_id
                    }}
                </p>
                <p>
                    <span class="font-semibold">Entrega interna:</span> #{{
                        delivery.id
                    }}
                </p>
                <p>
                    <span class="font-semibold">Propietario:</span>
                    {{ delivery.owner_name }}
                </p>
                <p>
                    <span class="font-semibold">Destinatario:</span>
                    {{ delivery.recipient_email }}
                </p>
                <p>
                    <span class="font-semibold">Intentos:</span>
                    {{ delivery.attempt_count }}
                </p>
                <p>
                    <span class="font-semibold">Primer intento:</span>
                    {{ delivery.first_attempted_at ?? 'Sin fecha registrada' }}
                </p>
                <p class="sm:col-span-2">
                    <span class="font-semibold">Código interno:</span>
                    {{ delivery.failure_code ?? 'Sin código' }}
                </p>
            </div>

            <form
                v-bind="resolveSosDelivery.form(delivery.id)"
                method="post"
                class="mt-4 grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-2 dark:border-slate-800"
                @submit="confirmResolution"
            >
                <input type="hidden" name="_token" :value="csrfToken" />
                <label
                    class="text-sm font-medium text-slate-800 dark:text-slate-200"
                >
                    Resultado verificado en Resend
                    <select
                        v-model="outcomes[delivery.id]"
                        name="outcome"
                        required
                        class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option disabled value="">
                            Selecciona un resultado
                        </option>
                        <option value="accepted">
                            Proveedor aceptó (requiere ID de mensaje)
                        </option>
                        <option value="not_accepted">
                            Proveedor confirmó que no aceptó
                        </option>
                    </select>
                </label>

                <label
                    v-if="outcomes[delivery.id] === 'accepted'"
                    class="text-sm font-medium text-slate-800 dark:text-slate-200"
                >
                    ID de mensaje de Resend
                    <input
                        name="provider_message_id"
                        required
                        maxlength="255"
                        class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                    />
                </label>
                <div v-else class="hidden sm:block" aria-hidden="true" />

                <p
                    class="text-xs text-slate-600 sm:col-span-2 dark:text-slate-400"
                >
                    La decisión se atribuye a tu cuenta administrativa. No se
                    muestran coordenadas ni contenido del mensaje.
                </p>
                <button
                    type="submit"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-800 sm:col-span-2"
                >
                    Registrar revisión
                </button>
            </form>
        </section>
    </div>
</template>
