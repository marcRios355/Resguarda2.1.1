<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    helpRequest: {
        id: number;
        district_name: string | null;
        help_type: string;
        urgency_level: string;
        status: string;
        status_label: string;
        description: string;
        address_reference: string | null;
        latitude: number | null;
        longitude: number | null;
        created_at: string | null;
    };
}>();
</script>

<template>
    <Head title="Detalles privados de solicitud" />

    <div
        class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6"
    >
        <Link
            href="/help"
            class="inline-flex min-h-11 w-fit items-center rounded-lg px-3 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/50"
        >
            Volver a solicitudes de ayuda
        </Link>

        <section
            class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950/70"
        >
            <p
                class="text-xs font-semibold tracking-[0.2em] text-rose-600 uppercase dark:text-rose-400"
            >
                Detalles privados
            </p>
            <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                Solicitud de ayuda
            </h1>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Distrito
                    </dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">
                        {{ helpRequest.district_name ?? 'Sin distrito' }}
                    </dd>
                </div>
                <div>
                    <dt
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Tipo de ayuda
                    </dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">
                        {{ helpRequest.help_type }}
                    </dd>
                </div>
                <div>
                    <dt
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Urgencia
                    </dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">
                        {{ helpRequest.urgency_level }}
                    </dd>
                </div>
                <div>
                    <dt
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Estado
                    </dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">
                        {{ helpRequest.status_label }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Descripción privada
                    </dt>
                    <dd
                        class="mt-1 whitespace-pre-wrap text-slate-900 dark:text-white"
                    >
                        {{ helpRequest.description }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Referencia de ubicación privada
                    </dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">
                        {{ helpRequest.address_reference ?? 'Sin referencia' }}
                    </dd>
                </div>
                <div
                    v-if="
                        helpRequest.latitude !== null &&
                        helpRequest.longitude !== null
                    "
                >
                    <dt
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Coordenadas privadas
                    </dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">
                        {{ helpRequest.latitude }}, {{ helpRequest.longitude }}
                    </dd>
                </div>
                <div>
                    <dt
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Fecha de creación
                    </dt>
                    <dd class="mt-1 text-slate-900 dark:text-white">
                        {{ helpRequest.created_at }}
                    </dd>
                </div>
            </dl>
        </section>
    </div>
</template>
