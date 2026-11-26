<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, LocateFixed, ShieldPlus } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { useGeolocation } from '@/composables/useGeolocation';

type DistrictOption = {
    id: number;
    name: string;
    latitude: number;
    longitude: number;
};

const props = defineProps<{
    districts: DistrictOption[];
}>();

const emit = defineEmits<{
    published: [];
}>();

const statusOptions = [
    { value: 'available', label: 'Disponible' },
    { value: 'limited', label: 'Capacidad limitada' },
    { value: 'almost_full', label: 'Casi llena' },
    { value: 'saturated', label: 'Saturada' },
    { value: 'unavailable', label: 'No disponible' },
];

const serviceOptions = [
    { value: 'agua', label: 'Agua' },
    { value: 'luz', label: 'Luz' },
    { value: 'banos', label: 'Baños' },
    { value: 'primeros_auxilios', label: 'Primeros auxilios' },
    { value: 'alimentos', label: 'Alimentos' },
    { value: 'internet', label: 'Internet' },
    { value: 'seguridad', label: 'Seguridad' },
];

const form = useForm({
    name: '',
    address: '',
    district_id: '' as number | '',
    latitude: null as number | null,
    longitude: null as number | null,
    capacity: 100,
    current_occupancy: 0,
    status: 'available',
    services: [] as string[],
    notes: '',
});

const { isLocating, statusMessage, locate, lookupDistrict } = useGeolocation();
const locationNote = ref('');
const usingDistrictCenter = ref(false);

const occupancyPercentage = computed(() =>
    form.capacity > 0
        ? Math.min(
              100,
              Math.round((form.current_occupancy / form.capacity) * 100),
          )
        : 0,
);

/**
 * Toma la ubicación actual del dispositivo y sugiere el distrito. Si no se
 * puede obtener, se usa el centro del distrito que elija la persona.
 */
const detectLocation = async () => {
    locationNote.value = '';
    const position = await locate({ force: true });

    if (!position) {
        usingDistrictCenter.value = true;
        locationNote.value = `${statusMessage.value} Elige el distrito: se usará su centro como ubicación.`;

        return;
    }

    usingDistrictCenter.value = false;
    form.latitude = position.latitude;
    form.longitude = position.longitude;

    const result = await lookupDistrict();

    if (result?.district) {
        form.district_id = result.district.id;
        locationNote.value = result.approximate
            ? `Ubicación detectada. Distrito aproximado: ${result.district.name}; confírmalo.`
            : `Ubicación detectada en ${result.district.name}.`;
    } else {
        locationNote.value =
            'Ubicación detectada, pero parece fuera de la provincia de Trujillo. Elige el distrito.';
    }
};

const submit = () => {
    if (usingDistrictCenter.value || form.latitude === null) {
        const district = props.districts.find(
            (item) => item.id === form.district_id,
        );

        form.latitude = district?.latitude ?? null;
        form.longitude = district?.longitude ?? null;
    }

    form.post('/safe-zones', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('published');
        },
    });
};

onMounted(detectLocation);
</script>

<template>
    <form
        class="grid gap-4 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 sm:grid-cols-2 sm:p-5 dark:border-emerald-900/60 dark:bg-emerald-950/20"
        @submit.prevent="submit"
    >
        <div
            class="flex flex-col gap-2 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-slate-600 dark:text-slate-300" role="status">
                {{ isLocating ? 'Obteniendo tu ubicación…' : locationNote }}
            </p>
            <button
                type="button"
                class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-60 dark:border-emerald-800 dark:bg-slate-900 dark:text-emerald-300"
                :disabled="isLocating"
                @click="detectLocation"
            >
                <LoaderCircle v-if="isLocating" class="h-4 w-4 animate-spin" />
                <LocateFixed v-else class="h-4 w-4" />
                Actualizar ubicación
            </button>
        </div>

        <label
            class="text-sm font-medium text-slate-700 sm:col-span-2 dark:text-slate-200"
        >
            Nombre del lugar
            <input
                v-model="form.name"
                required
                maxlength="150"
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                placeholder="Ej. Centro Cultural Moche"
            />
            <InputError :message="form.errors.name" />
        </label>

        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
            Dirección
            <input
                v-model="form.address"
                required
                maxlength="255"
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                placeholder="Ej. Calle Los Próceres 145"
            />
            <InputError :message="form.errors.address" />
        </label>

        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
            Distrito
            <select
                v-model="form.district_id"
                required
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
            >
                <option value="" disabled>Elige un distrito</option>
                <option
                    v-for="district in props.districts"
                    :key="district.id"
                    :value="district.id"
                >
                    {{ district.name }}
                </option>
            </select>
            <InputError
                :message="
                    form.errors.district_id ??
                    form.errors.latitude ??
                    form.errors.longitude
                "
            />
        </label>

        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
            Capacidad (personas)
            <input
                v-model.number="form.capacity"
                type="number"
                min="1"
                max="100000"
                required
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
            />
            <InputError :message="form.errors.capacity" />
        </label>

        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
            Ocupación actual ({{ occupancyPercentage }}%)
            <input
                v-model.number="form.current_occupancy"
                type="number"
                min="0"
                :max="form.capacity"
                required
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
            />
            <InputError :message="form.errors.current_occupancy" />
        </label>

        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
            Estado
            <select
                v-model="form.status"
                required
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
            >
                <option
                    v-for="option in statusOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <InputError :message="form.errors.status" />
        </label>

        <fieldset class="text-sm sm:col-span-2">
            <legend class="font-medium text-slate-700 dark:text-slate-200">
                Servicios disponibles
            </legend>
            <div class="mt-2 flex flex-wrap gap-2">
                <label
                    v-for="service in serviceOptions"
                    :key="service.value"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        form.services.includes(service.value)
                            ? 'border-emerald-600 bg-emerald-600 text-white'
                            : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'
                    "
                >
                    <input
                        v-model="form.services"
                        type="checkbox"
                        :value="service.value"
                        class="sr-only"
                    />
                    {{ service.label }}
                </label>
            </div>
            <InputError :message="form.errors.services" />
        </fieldset>

        <label
            class="text-sm font-medium text-slate-700 sm:col-span-2 dark:text-slate-200"
        >
            Información adicional (opcional)
            <textarea
                v-model="form.notes"
                rows="2"
                maxlength="1000"
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                placeholder="Horario, punto de acceso, contacto del responsable…"
            />
            <InputError :message="form.errors.notes" />
        </label>

        <button
            type="submit"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60 sm:col-span-2"
            :disabled="form.processing || isLocating"
        >
            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
            <ShieldPlus v-else class="h-4 w-4" />
            Publicar zona segura
        </button>
    </form>
</template>
