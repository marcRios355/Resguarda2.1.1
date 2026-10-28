<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, ShieldAlert, XCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token ?? '');

const props = defineProps<{
    districts: Array<{
        id: number;
        name: string;
        slug: string;
        risk_level: string;
        latitude: number;
        longitude: number;
    }>;
    reports: Array<{
        id: number;
        title: string;
        description: string;
        incident_type: string;
        severity: string;
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
    }>;
    selected_district_id?: number;
}>();

const selectedDistrictId = ref<number | ''>(props.selected_district_id ?? props.districts[0]?.id ?? '');
const selectedDistrict = computed(() => props.districts.find((district) => district.id === selectedDistrictId.value));
const latitude = computed(() => selectedDistrict.value?.latitude ?? -8.1116);
const longitude = computed(() => selectedDistrict.value?.longitude ?? -79.0287);


const statusClasses: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
    community_alert: 'bg-orange-100 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300',
    confirmed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
    resolved: 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
};

const severityClasses: Record<string, string> = {
    baja: 'bg-slate-100 text-slate-700',
    media: 'bg-blue-100 text-blue-700',
    alta: 'bg-orange-100 text-orange-700',
    critica: 'bg-red-100 text-red-700',
};
</script>

<template>
    <Head title="Reportes" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-600 dark:text-orange-400">Reportes ciudadanos</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Reporte de incidentes</h1>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-700 dark:border-orange-900/70 dark:bg-orange-950/50 dark:text-orange-300">
                    <AlertTriangle class="h-3.5 w-3.5" />
                    Seguimiento comunitario
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-orange-200 bg-orange-50/70 p-4 shadow-sm dark:border-orange-900/60 dark:bg-orange-950/20 sm:p-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-700 dark:text-orange-300">Nuevo reporte</p>
                <h2 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Cuéntale a Trujillo qué está pasando</h2>
            </div>

            <form method="post" action="/reports" class="mt-5 grid gap-4 md:grid-cols-2">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="latitude" :value="latitude" />
                <input type="hidden" name="longitude" :value="longitude" />
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Distrito
                    <select v-model="selectedDistrictId" name="district_id" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option v-for="district in props.districts" :key="district.id" :value="district.id">{{ district.name }}</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Tipo de incidente
                    <select name="incident_type" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option value="inundacion">Inundación</option>
                        <option value="huaico">Huaico</option>
                        <option value="desborde">Desborde</option>
                        <option value="calle_bloqueada">Calle bloqueada</option>
                        <option value="zona_en_riesgo">Zona en riesgo</option>
                        <option value="personas_atrapadas">Personas atrapadas</option>
                        <option value="otro">Otro</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Título
                    <input name="title" required maxlength="150" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" placeholder="Ej. Calle bloqueada por agua" />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Descripción
                    <textarea name="description" required maxlength="1000" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" placeholder="Describe lo que viste, cuándo ocurrió y cómo afecta a la zona." />
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Severidad
                    <select name="severity" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option value="baja">Baja</option>
                        <option value="media">Media</option>
                        <option value="alta">Alta</option>
                        <option value="critica">Crítica</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Referencia
                    <input name="address_reference" maxlength="255" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" placeholder="Calle, avenida o punto cercano" />
                </label>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-700 md:col-span-2">
                    <AlertTriangle class="h-4 w-4" /> Enviar reporte
                </button>
            </form>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="space-y-4">
                <div v-for="report in props.reports" :key="report.id" class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ report.title }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ report.user_name ?? 'Ciudadano anónimo' }} · {{ report.district_name ?? 'Sin distrito' }}</p>
                        </div>
                        <span :class="['rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide', statusClasses[report.status] || 'bg-slate-100 text-slate-700']">
                            {{ report.status_label }}
                        </span>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ report.description }}</p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Tipo</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ report.incident_type }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Severidad</p>
                            <p :class="['mt-1 inline-flex rounded-full px-2 py-1 text-xs font-semibold', severityClasses[report.severity] || 'bg-slate-100 text-slate-700']">
                                {{ report.severity }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Ubicación</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ report.address_reference ?? 'Sin referencia' }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <span class="inline-flex items-center gap-1"><CheckCircle2 class="h-4 w-4 text-emerald-600" /> {{ report.confirmations_count }} confirmaciones</span>
                        <span class="inline-flex items-center gap-1"><XCircle class="h-4 w-4 text-red-600" /> {{ report.rejections_count }} rechazos</span>
                        <span>{{ report.created_at ? new Date(report.created_at).toLocaleString() : 'Reciente' }}</span>
                    </div>

                    <div class="mt-4 flex gap-3">
                        <form method="post" :action="`/reports/${report.id}/vote`">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <input type="hidden" name="vote" value="confirm" />
                            <button class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                <CheckCircle2 class="h-4 w-4" /> Confirmar
                            </button>
                        </form>
                        <form method="post" :action="`/reports/${report.id}/vote`">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <input type="hidden" name="vote" value="reject" />
                            <button class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                                <XCircle class="h-4 w-4" /> Rechazar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <aside class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <div class="flex items-center gap-2 text-orange-600 dark:text-orange-400">
                    <ShieldAlert class="h-4 w-4" />
                    <p class="text-xs font-semibold uppercase tracking-[0.2em]">Resumen</p>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Pendientes</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ props.reports.filter((r) => r.status === 'pending').length }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Alerta comunitaria</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ props.reports.filter((r) => r.status === 'community_alert').length }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Confirmados</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ props.reports.filter((r) => r.status === 'confirmed').length }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-dashed border-orange-200 bg-orange-50 p-3 dark:border-orange-900/60 dark:bg-orange-950/30">
                    <p class="text-sm font-semibold text-orange-700 dark:text-orange-300">Regla de validación</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-orange-700 dark:text-orange-300">
                        <li>1 reporte = Pendiente</li>
                        <li>3 reportes = Alerta comunitaria</li>
                        <li>5+ reportes = Riesgo confirmado</li>
                    </ul>
                </div>
            </aside>
        </section>
    </div>
</template>
