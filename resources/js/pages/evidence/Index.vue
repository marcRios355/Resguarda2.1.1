<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Camera, CheckCircle2, MapPin, ShieldAlert, XCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/CitizenEvidenceController';
import { useGeolocation } from '@/composables/useGeolocation';

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
    evidences: Array<{
        id: number;
        title: string;
        description: string;
        incident_type: string;
        severity: string;
        status: string;
        status_label: string;
        is_still_happening: boolean;
        district_name: string | null;
        user_name: string | null;
        photo_path: string | null;
        latitude: number;
        longitude: number;
        address_reference: string | null;
        confirmation_count: number;
        false_report_count: number;
        confidence_percentage: number;
        created_at: string | null;
        vote: string | null;
    }>;
    selected_district_id?: number;
}>();

const selectedDistrictId = ref<number | ''>(props.selected_district_id ?? props.districts[0]?.id ?? '');
const latitude = ref<string>('');
const longitude = ref<string>('');
const reportReason = ref('false_information');

const selectedDistrict = computed(() => props.districts.find((district) => district.id === selectedDistrictId.value));

const { statusMessage, isLocating, locate, lookupDistrict } = useGeolocation();
const locationNote = ref('');

const requestLocation = async () => {
    const position = await locate({ force: true });

    if (!position) {
        locationNote.value = statusMessage.value;

        return;
    }

    latitude.value = String(position.latitude);
    longitude.value = String(position.longitude);
    const result = await lookupDistrict();

    if (result?.district) {
        selectedDistrictId.value = result.district.id;
        locationNote.value = result.approximate
            ? `Ubicación añadida. Distrito sugerido: ${result.district.name} (aproximado, confírmalo).`
            : `Ubicación añadida en el distrito ${result.district.name}.`;
    } else {
        locationNote.value = 'Ubicación añadida. Parece estar fuera de Trujillo: elige el distrito manualmente.';
    }
};

const severityClass: Record<string, string> = {
    baja: 'bg-slate-100 text-slate-700',
    media: 'bg-blue-100 text-blue-700',
    alta: 'bg-orange-100 text-orange-700',
    critica: 'bg-red-100 text-red-700',
};

const statusClass: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    under_review: 'bg-orange-100 text-orange-700',
    confirmed: 'bg-emerald-100 text-emerald-700',
    resolved: 'bg-sky-100 text-sky-700',
    rejected: 'bg-red-100 text-red-700',
};
</script>

<template>
    <Head title="Evidencias ciudadanas" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600 dark:text-cyan-400">Ciudadanía</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Evidencias ciudadanas</h1>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-semibold text-cyan-700 dark:border-cyan-900/70 dark:bg-cyan-950/50 dark:text-cyan-300">
                    <Camera class="h-3.5 w-3.5" />
                    Registro comunitario
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-cyan-200 bg-cyan-50/70 p-4 shadow-sm dark:border-cyan-900/60 dark:bg-cyan-950/20 sm:p-5">
            <div class="mb-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700 dark:text-cyan-300">Nueva evidencia</p>
                <h2 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Sube una fotografía y ayuda a la comunidad</h2>
            </div>

            <form v-bind="store.form()" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="latitude" :value="latitude" />
                <input type="hidden" name="longitude" :value="longitude" />

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Distrito
                    <select v-model="selectedDistrictId" name="district_id" required :disabled="props.districts.length === 0" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900">
                        <option v-if="props.districts.length === 0" value="" disabled>No hay distritos disponibles</option>
                        <option v-for="district in props.districts" :key="district.id" :value="district.id">
                            {{ district.name }}
                        </option>
                    </select>
                    <p v-if="props.districts.length === 0" role="status" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                        No hay distritos disponibles. Contacta al administrador para habilitar el registro de evidencias.
                    </p>
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Tipo de incidente
                    <select name="incident_type" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option value="inundacion">Inundación</option>
                        <option value="huaico">Huaico</option>
                        <option value="desborde">Desborde</option>
                        <option value="calle_bloqueada">Calle bloqueada</option>
                        <option value="materiales_caidos">Materiales caídos</option>
                        <option value="deslizamiento">Deslizamiento</option>
                        <option value="sismo">Sismo</option>
                        <option value="zona_afectada">Zona afectada</option>
                        <option value="refugio_saturado">Refugio saturado</option>
                        <option value="vialidad">Vialidad</option>
                        <option value="otro">Otro</option>
                    </select>
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Título
                    <input name="title" required maxlength="150" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" placeholder="Ej. Calle bloqueada por agua" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Descripción
                    <textarea name="description" required maxlength="1000" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" placeholder="Describe lo que observas, la zona, y si la situación sigue ocurriendo." />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Severidad
                    <select name="severity" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option value="baja">Baja</option>
                        <option value="media" selected>Media</option>
                        <option value="alta">Alta</option>
                        <option value="critica">Crítica</option>
                    </select>
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    ¿Sigue ocurriendo?
                    <select name="is_still_happening" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option :value="1" selected>Sigue ocurriendo</option>
                        <option :value="0">Ya fue solucionado</option>
                    </select>
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Ubicación y referencia
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input name="address_reference" maxlength="255" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" placeholder="Calle, avenida o punto cercano" />
                        <button type="button" :disabled="isLocating" @click="requestLocation" class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm font-medium text-emerald-700 disabled:opacity-60 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <MapPin class="h-4 w-4" />
                            {{ isLocating ? 'Ubicando…' : 'Usar mi ubicación' }}
                        </button>
                    </div>
                    <span v-if="locationNote" class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400" role="status">{{ locationNote }}</span>
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Fotografía
                    <input type="file" name="photo" accept="image/*" class="mt-2 block w-full rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" />
                </label>

                <button type="submit" :disabled="props.districts.length === 0" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-60 md:col-span-2">
                    <Camera class="h-4 w-4" />
                    Publicar evidencia
                </button>
            </form>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.25fr_0.75fr]">
            <div class="space-y-4">
                <div v-for="evidence in props.evidences" :key="evidence.id" class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ evidence.title }}</h2>
                                <span :class="statusClass[evidence.status] ?? 'bg-slate-100 text-slate-700'" class="rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide">
                                    {{ evidence.status_label }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ evidence.district_name }} · {{ evidence.created_at }}</p>
                        </div>
                        <span :class="severityClass[evidence.severity] ?? 'bg-slate-100 text-slate-700'" class="rounded-full px-2 py-1 text-xs font-semibold">{{ evidence.severity }}</span>
                    </div>

                    <div v-if="evidence.photo_path" class="mt-4 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                        <img :src="evidence.photo_path" :alt="evidence.title" class="h-64 w-full object-cover" />
                    </div>

                    <p class="mt-4 text-sm leading-6 text-slate-700 dark:text-slate-200">
                        {{ evidence.description }}
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                        <span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ evidence.incident_type }}</span>
                        <span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ evidence.is_still_happening ? 'Sigue ocurriendo' : 'Ya fue solucionado' }}</span>
                        <span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ evidence.confidence_percentage }}% confiable</span>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <form method="post" :action="`/evidence/${evidence.id}/vote`">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <button type="submit" name="vote_type" value="confirm" class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">
                                <CheckCircle2 class="h-4 w-4" />
                                Confirmar
                            </button>
                        </form>
                        <form method="post" :action="`/evidence/${evidence.id}/vote`">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <button type="submit" name="vote_type" value="mismatch" class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300">
                                <XCircle class="h-4 w-4" />
                                No coincide
                            </button>
                        </form>
                        <form method="post" :action="`/evidence/${evidence.id}/vote`">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <button type="submit" name="vote_type" value="outdated" class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300">
                                <AlertTriangle class="h-4 w-4" />
                                Información desactualizada
                            </button>
                        </form>
                    </div>

                    <form method="post" :action="`/evidence/${evidence.id}/report`" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60">
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Reportar evidencia</label>
                            <select v-model="reportReason" name="report_reason" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950">
                                <option value="false_information">Información falsa</option>
                                <option value="old_photo">Fotografía antigua</option>
                                <option value="incorrect_content">Contenido incorrecto</option>
                                <option value="incorrect_location">Ubicación incorrecta</option>
                                <option value="inappropriate_content">Contenido inapropiado</option>
                                <option value="spam">Spam</option>
                            </select>
                        </div>
                        <textarea name="notes" rows="2" class="mt-3 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" placeholder="Añade más detalle del reporte."></textarea>
                        <button type="submit" class="mt-3 inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                            <ShieldAlert class="h-4 w-4" />
                            Reportar
                        </button>
                    </form>
                </div>
            </div>

            <aside class="space-y-4 rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600 dark:text-cyan-400">Riesgo</p>
                    <h3 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Distritos activos</h3>
                </div>

                <div v-for="district in props.districts" :key="district.id" class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold text-slate-900 dark:text-white">{{ district.name }}</p>
                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                            {{ district.risk_level }}
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Coordenadas: {{ district.latitude }}, {{ district.longitude }}</p>
                </div>
            </aside>
        </section>
    </div>
</template>
