<script setup lang="ts">
import { LoaderCircle, LocateFixed, MapPin, RotateCcw } from '@lucide/vue';
import { computed, onMounted, watch } from 'vue';
import { useGeolocation } from '@/composables/useGeolocation';

const props = withDefaults(
    defineProps<{
        districts?: Array<{ id: number; name: string }>;
        /** Intenta ubicar sin mostrar el diálogo si el permiso ya fue concedido. */
        autoLocate?: boolean;
        /** Texto que explica para qué se usará la ubicación en esta sección. */
        purpose?: string;
    }>(),
    {
        districts: () => [],
        autoLocate: true,
        purpose:
            'Tu ubicación se usa solo en este dispositivo para mostrarte información cercana; no se comparte con otros usuarios.',
    },
);

const {
    status,
    statusMessage,
    isLocating,
    hasLocation,
    lookup,
    lookupError,
    preferredDistrictId,
    locate,
    locateIfPermitted,
    lookupDistrict,
    setPreferredDistrict,
} = useGeolocation();

const canRetry = computed(() =>
    ['denied', 'unavailable', 'timeout'].includes(status.value),
);

const detectedLabel = computed(() => {
    if (!lookup.value) {
        return '';
    }

    if (lookup.value.method === 'outside' || !lookup.value.district) {
        return 'Tu ubicación parece estar fuera de la provincia de Trujillo.';
    }

    return lookup.value.approximate
        ? `Distrito aproximado: ${lookup.value.district.name}. Confírmalo si estás cerca de un límite distrital.`
        : `Estás en el distrito ${lookup.value.district.name}.`;
});

const requestLocation = async (force = false) => {
    const result = await locate({ force });

    if (result) {
        await lookupDistrict();
    }
};

const onDistrictChange = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;
    setPreferredDistrict(value ? Number(value) : null);
};

watch(
    () => lookup.value?.district?.id,
    (districtId) => {
        if (districtId && !preferredDistrictId.value) {
            setPreferredDistrict(districtId);
        }
    },
);

onMounted(async () => {
    if (hasLocation.value) {
        await lookupDistrict();

        return;
    }

    if (props.autoLocate && (await locateIfPermitted())) {
        await lookupDistrict();
    }
});
</script>

<template>
    <section
        class="rounded-2xl border border-sky-200 bg-sky-50/70 p-4 text-sm dark:border-sky-900/70 dark:bg-sky-950/30"
        aria-label="Tu ubicación"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex min-w-0 items-start gap-2">
                <MapPin
                    class="mt-0.5 h-4 w-4 shrink-0 text-sky-600 dark:text-sky-400"
                />
                <div class="min-w-0" role="status" aria-live="polite">
                    <p class="font-medium text-slate-800 dark:text-slate-100">
                        {{ detectedLabel || statusMessage }}
                    </p>
                    <p
                        v-if="lookupError"
                        class="mt-1 text-red-700 dark:text-red-300"
                    >
                        {{ lookupError }}
                    </p>
                    <p
                        v-if="status === 'idle'"
                        class="mt-1 text-xs text-slate-600 dark:text-slate-400"
                    >
                        {{ purpose }}
                    </p>
                </div>
            </div>
            <button
                v-if="!hasLocation || canRetry"
                type="button"
                class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-sky-600 px-3 py-2 font-semibold text-white transition hover:bg-sky-700 disabled:opacity-60"
                :disabled="isLocating"
                @click="requestLocation(true)"
            >
                <LoaderCircle v-if="isLocating" class="h-4 w-4 animate-spin" />
                <RotateCcw v-else-if="canRetry" class="h-4 w-4" />
                <LocateFixed v-else class="h-4 w-4" />
                {{ canRetry ? 'Reintentar' : 'Usar mi ubicación' }}
            </button>
            <button
                v-else
                type="button"
                class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-sky-300 px-3 py-2 font-semibold text-sky-700 transition hover:bg-sky-100 dark:border-sky-800 dark:text-sky-300 dark:hover:bg-sky-900/40"
                :disabled="isLocating"
                @click="requestLocation(true)"
            >
                <LoaderCircle v-if="isLocating" class="h-4 w-4 animate-spin" />
                <LocateFixed v-else class="h-4 w-4" />
                Actualizar
            </button>
        </div>

        <label
            v-if="districts.length"
            class="mt-3 flex flex-col gap-1 text-xs font-medium text-slate-600 sm:flex-row sm:items-center sm:gap-3 dark:text-slate-300"
        >
            <span>Mi distrito</span>
            <select
                :value="preferredDistrictId ?? ''"
                class="min-h-10 rounded-lg border border-sky-200 bg-white px-3 py-2 text-sm text-slate-800 dark:border-sky-900 dark:bg-slate-900 dark:text-slate-100"
                @change="onDistrictChange"
            >
                <option value="">Sin seleccionar</option>
                <option
                    v-for="district in districts"
                    :key="district.id"
                    :value="district.id"
                >
                    {{ district.name }}
                </option>
            </select>
        </label>
    </section>
</template>
