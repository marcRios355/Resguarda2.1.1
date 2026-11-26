<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    MapPinned,
    Navigation,
    XCircle,
} from '@lucide/vue';
import { computed } from 'vue';
import { directionsUrl } from '@/composables/useGeolocation';

type Report = {
    id: number;
    title: string;
    description: string;
    incident_type: string;
    severity: 'baja' | 'media' | 'alta' | 'critica';
    status: string;
    status_label: string;
    district_name: string | null;
    user_name: string | null;
    confirmations_count: number;
    rejections_count: number;
    created_at: string | null;
    latitude: number;
    longitude: number;
    address_reference: string | null;
    vote: 'confirm' | 'reject' | null;
};

const props = defineProps<{
    report: Report;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mapa de Riesgo', href: '/map' },
            { title: 'Publicación', href: '#' },
        ],
    },
});

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token ?? '');

const incidentLabels: Record<string, string> = {
    inundacion: 'Inundación',
    huaico: 'Huaico',
    desborde: 'Desborde',
    calle_bloqueada: 'Calle bloqueada',
    zona_en_riesgo: 'Zona en riesgo',
    zona_segura: 'Zona segura',
    personas_atrapadas: 'Personas atrapadas',
    zona_despejada: 'Zona despejada',
    zona_en_proceso_despeje: 'Zona en proceso de despeje',
    otro: 'Otro',
};

const severityStyles: Record<
    Report['severity'],
    { color: string; label: string }
> = {
    baja: { color: '#0ea5e9', label: 'Gravedad baja' },
    media: { color: '#f59e0b', label: 'Gravedad media' },
    alta: { color: '#f97316', label: 'Gravedad alta' },
    critica: { color: '#dc2626', label: 'Gravedad crítica' },
};

const badge = computed(() =>
    props.report.incident_type === 'zona_segura'
        ? { color: '#10b981', label: 'Zona segura' }
        : (severityStyles[props.report.severity] ?? severityStyles.media),
);

const publishedAt = computed(() =>
    props.report.created_at
        ? new Date(props.report.created_at).toLocaleString('es-PE')
        : 'Reciente',
);
</script>

<template>
    <Head :title="report.title" />

    <div
        class="mx-auto flex w-full max-w-3xl min-w-0 flex-col gap-4 p-3 sm:p-6"
    >
        <Link
            href="/map"
            class="inline-flex w-fit items-center gap-1 text-sm font-medium text-slate-600 hover:underline dark:text-slate-300"
        >
            <ArrowLeft class="h-4 w-4" /> Volver al mapa
        </Link>

        <article
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold text-white"
                    :style="`background:${badge.color}`"
                >
                    {{ badge.label }}
                </span>
                <span
                    class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >{{ report.status_label }}</span
                >
            </div>

            <h1 class="mt-3 text-2xl font-bold text-slate-900 dark:text-white">
                {{ report.title }}
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ report.user_name ?? 'Ciudadano' }} ·
                {{ report.district_name ?? 'Sin distrito' }} · {{ publishedAt }}
            </p>

            <p
                class="mt-4 text-sm leading-6 whitespace-pre-line text-slate-700 dark:text-slate-200"
            >
                {{ report.description }}
            </p>

            <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">
                        Tipo
                    </dt>
                    <dd
                        class="mt-1 font-semibold text-slate-800 dark:text-slate-100"
                    >
                        {{
                            incidentLabels[report.incident_type] ??
                            report.incident_type
                        }}
                    </dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">
                        Referencia
                    </dt>
                    <dd
                        class="mt-1 font-semibold text-slate-800 dark:text-slate-100"
                    >
                        {{ report.address_reference ?? 'Sin referencia' }}
                    </dd>
                </div>
            </dl>

            <div class="mt-5 flex flex-wrap gap-2">
                <Link
                    :href="`/map?report=${report.id}`"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
                >
                    <MapPinned class="h-4 w-4" /> Ver en el mapa
                </Link>
                <a
                    :href="directionsUrl(report.latitude, report.longitude)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
                >
                    <Navigation class="h-4 w-4" /> Cómo llegar
                </a>
            </div>
        </article>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
        >
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                ¿Es confiable esta publicación?
            </p>
            <div
                class="mt-2 flex flex-wrap items-center gap-3 text-sm text-slate-600 dark:text-slate-300"
            >
                <span class="inline-flex items-center gap-1"
                    ><CheckCircle2 class="h-4 w-4 text-emerald-600" />
                    {{ report.confirmations_count }} confiable</span
                >
                <span class="inline-flex items-center gap-1"
                    ><XCircle class="h-4 w-4 text-red-600" />
                    {{ report.rejections_count }} no confiable</span
                >
            </div>
            <p
                v-if="report.vote"
                class="mt-3 text-sm text-slate-500 dark:text-slate-400"
            >
                Ya
                {{ report.vote === 'confirm' ? 'marcaste como confiable' : 'marcaste como no confiable' }}
                esta publicación.
            </p>
            <div v-else class="mt-3 flex gap-3">
                <form method="post" :action="`/reports/${report.id}/vote`">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input type="hidden" name="vote" value="confirm" />
                    <button
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                    >
                        <CheckCircle2 class="h-4 w-4" /> Es confiable
                    </button>
                </form>
                <form method="post" :action="`/reports/${report.id}/vote`">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input type="hidden" name="vote" value="reject" />
                    <button
                        class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
                    >
                        <XCircle class="h-4 w-4" /> No es confiable
                    </button>
                </form>
            </div>
        </section>
    </div>
</template>
