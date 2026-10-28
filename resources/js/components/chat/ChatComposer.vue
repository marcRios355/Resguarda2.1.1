<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ImagePlus, LoaderCircle, RotateCcw, Send, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        action: string;
        /** Campos adicionales que se envían junto al mensaje. */
        extra?: Record<string, string | number | null | undefined>;
        placeholder?: string;
        inputId: string;
        label: string;
        accent?: 'emerald' | 'violet';
        submitLabel?: string;
        /** Texto sugerido con el que empieza el mensaje. */
        initialMessage?: string;
    }>(),
    {
        extra: () => ({}),
        placeholder: 'Escribe un mensaje…',
        accent: 'emerald',
        submitLabel: 'Enviar',
        initialMessage: '',
    },
);

const emit = defineEmits<{ sent: [] }>();

const MAX_BYTES = 8 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const form = useForm<{ message: string; photo: File | null }>({
    message: props.initialMessage,
    photo: null,
});
const preview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const clientError = ref('');
const sendFailed = ref(false);

const errorMessage = computed(
    () =>
        clientError.value ||
        form.errors.photo ||
        form.errors.message ||
        Object.values(form.errors)[0] ||
        (sendFailed.value
            ? 'No se pudo enviar. Revisa tu conexión e inténtalo de nuevo.'
            : ''),
);
const canSend = computed(
    () =>
        !form.processing && (form.message.trim() !== '' || form.photo !== null),
);
const buttonClass = computed(() =>
    props.accent === 'violet'
        ? 'bg-violet-600 hover:bg-violet-700'
        : 'bg-emerald-600 hover:bg-emerald-700',
);
const borderClass = computed(() =>
    props.accent === 'violet'
        ? 'border-violet-200 dark:border-violet-900'
        : 'border-emerald-200 dark:border-emerald-900',
);

const releasePreview = () => {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = null;
};

const clearPhoto = () => {
    releasePreview();
    form.photo = null;
    form.clearErrors('photo');

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const selectPhoto = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    clientError.value = '';

    if (!file) {
        return;
    }

    if (!ALLOWED_TYPES.includes(file.type)) {
        clientError.value = 'Solo se permiten fotografías JPG, PNG o WebP.';
        clearPhoto();

        return;
    }

    if (file.size > MAX_BYTES) {
        clientError.value = 'La fotografía supera el tamaño máximo de 8 MB.';
        clearPhoto();

        return;
    }

    releasePreview();
    form.photo = file;
    preview.value = URL.createObjectURL(file);
};

const submit = () => {
    if (!canSend.value) {
        return;
    }

    clientError.value = '';
    sendFailed.value = false;
    let succeeded = false;

    form.transform((data) => ({ ...data, ...props.extra })).post(props.action, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            succeeded = true;
            form.reset();
            clearPhoto();
            emit('sent');
        },
        onFinish: () => {
            if (!succeeded && !form.hasErrors) {
                sendFailed.value = true;
            }
        },
    });
};

onBeforeUnmount(releasePreview);
</script>

<template>
    <form class="space-y-2" @submit.prevent="submit">
        <div
            v-if="preview"
            class="flex items-center gap-3 rounded-xl border bg-white p-2 dark:bg-slate-900"
            :class="borderClass"
        >
            <img
                :src="preview"
                alt="Vista previa de la fotografía"
                class="h-16 w-16 shrink-0 rounded-lg object-cover"
            />
            <div
                class="min-w-0 flex-1 text-xs text-slate-600 dark:text-slate-300"
            >
                <p class="truncate font-medium">{{ form.photo?.name }}</p>
                <p>Vista previa · puedes añadir un mensaje</p>
                <progress
                    v-if="form.progress"
                    class="mt-1 h-1.5 w-full"
                    :value="form.progress.percentage"
                    max="100"
                >
                    {{ form.progress.percentage }}%
                </progress>
            </div>
            <button
                type="button"
                class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"
                aria-label="Quitar fotografía"
                :disabled="form.processing"
                @click="clearPhoto"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
            <label class="sr-only" :for="inputId">{{ label }}</label>
            <textarea
                :id="inputId"
                v-model="form.message"
                maxlength="1000"
                rows="2"
                class="w-full rounded-xl border bg-white px-3 py-2.5 text-sm dark:bg-slate-900"
                :class="borderClass"
                :placeholder="placeholder"
                @keydown.enter.exact.prevent="submit"
            />
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="sr-only"
                @change="selectPhoto"
            />
            <button
                type="button"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                :class="borderClass"
                :disabled="form.processing"
                @click="fileInput?.click()"
            >
                <ImagePlus class="h-4 w-4" />
                <span>Foto</span>
            </button>
            <button
                type="submit"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                :class="buttonClass"
                :disabled="!canSend"
            >
                <LoaderCircle
                    v-if="form.processing"
                    class="h-4 w-4 animate-spin"
                />
                <Send v-else class="h-4 w-4" />
                {{ form.processing ? 'Enviando…' : submitLabel }}
            </button>
        </div>

        <slot />

        <div
            v-if="errorMessage"
            class="flex flex-wrap items-center gap-2 text-sm text-red-700 dark:text-red-300"
            role="alert"
        >
            <span>{{ errorMessage }}</span>
            <button
                v-if="sendFailed"
                type="button"
                class="inline-flex items-center gap-1 rounded-md border border-red-300 px-2 py-1 text-xs font-semibold hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-950/40"
                @click="submit"
            >
                <RotateCcw class="h-3 w-3" /> Reintentar
            </button>
        </div>
    </form>
</template>
