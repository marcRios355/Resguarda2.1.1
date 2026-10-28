<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Bot,
    Camera,
    LoaderCircle,
    MapPin,
    RotateCcw,
    Send,
    ShieldAlert,
    Sparkles,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import LocationPanel from '@/components/LocationPanel.vue';
import { Button } from '@/components/ui/button';
import { useGeolocation } from '@/composables/useGeolocation';

type Mode = 'general' | 'first_aid' | 'infrastructure' | 'objects';
type ChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    state: 'sent' | 'pending' | 'failed';
    error?: string;
    image?: File | null;
    imageOnly?: boolean;
    mode?: Mode;
};

const props = defineProps<{
    districts: Array<{ id: number; name: string }>;
}>();

const IMAGE_ONLY_LABEL = 'He adjuntado una imagen para analizar.';

const mode = ref<Mode>('general');
const prompt = ref('');
const image = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const messages = ref<ChatMessage[]>([]);
const loading = ref(false);
const notice = ref('');
const useLocation = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const conversation = ref<HTMLElement | null>(null);
let nextMessageId = 1;

const { coords, preferredDistrictId, locate } = useGeolocation();

const modes: Array<{ id: Mode; label: string; description: string }> = [
    {
        id: 'general',
        label: 'Emergencias y RESGUARDA',
        description: 'Prevención, distritos, refugios y alertas',
    },
    {
        id: 'first_aid',
        label: 'Primeros auxilios',
        description: 'Orientación básica y señales de alarma',
    },
    {
        id: 'infrastructure',
        label: 'Infraestructura',
        description: 'Observaciones de daños visibles',
    },
    {
        id: 'objects',
        label: 'Objetos',
        description: 'Identificación posible y usos',
    },
];

const suggestions = [
    '¿Cuál es la zona segura registrada más cercana a mí?',
    '¿Hay alertas activas en mi distrito?',
    '¿Qué debo hacer durante un sismo?',
    '¿Cómo preparo una mochila de emergencia?',
];

const districtName = computed(
    () =>
        props.districts.find(
            (district) => district.id === preferredDistrictId.value,
        )?.name ?? null,
);

/** Verdadero en cuanto empiezan a llegar fragmentos de la respuesta. */
const receivingAnswer = computed(() => {
    const last = messages.value[messages.value.length - 1];

    return (
        last?.role === 'assistant' &&
        last.state === 'pending' &&
        last.content !== ''
    );
});

const releasePreview = () => {
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
    }

    imagePreview.value = null;
};

const clearImage = () => {
    releasePreview();
    image.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const selectImage = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    releasePreview();
    image.value = file;
    imagePreview.value = file ? URL.createObjectURL(file) : null;
};

const scrollToBottom = async () => {
    await nextTick();
    conversation.value?.scrollTo({
        top: conversation.value.scrollHeight,
        behavior: 'smooth',
    });
};

const readPayload = async (
    response: Response,
): Promise<{
    answer?: string;
    message?: string;
    errors?: Record<string, string[]>;
    notice?: string;
}> => {
    try {
        return await response.json();
    } catch {
        return {};
    }
};

const xsrfToken = () =>
    decodeURIComponent(
        document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '',
    );

/**
 * Envía la consulta. Ante un fallo del servidor o de la red se reintenta una
 * vez de forma automática antes de mostrar el error al usuario.
 */
const request = async (message: ChatMessage, attempt = 0): Promise<void> => {
    loading.value = true;
    message.state = 'pending';
    message.error = undefined;

    const history = messages.value
        .filter((item) => item.state === 'sent' && item.id < message.id)
        .slice(-12);

    const form = new FormData();
    form.append('mode', message.mode ?? mode.value);
    form.append('prompt', message.imageOnly ? '' : message.content);
    history.forEach((item, index) => {
        form.append(`messages[${index}][role]`, item.role);
        form.append(`messages[${index}][content]`, item.content);
    });

    if (message.image) {
        form.append('image', message.image);
    }

    if (useLocation.value) {
        const position = coords.value ?? (await locate());

        if (position) {
            form.append('latitude', String(position.latitude));
            form.append('longitude', String(position.longitude));
        }
    }

    if (preferredDistrictId.value) {
        form.append('district_id', String(preferredDistrictId.value));
    }

    // Respuesta que se va completando mientras llegan los fragmentos.
    let answer: ChatMessage | null = null;
    let retryAutomatically = false;

    const fail = (error: string) => {
        message.state = 'failed';
        message.error = error;

        if (answer) {
            messages.value = messages.value.filter((item) => item !== answer);
        }
    };

    try {
        const token = xsrfToken();
        const response = await fetch('/assistant/stream', {
            method: 'POST',
            headers: {
                Accept: 'text/event-stream, application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
            credentials: 'same-origin',
            body: form,
        });

        if (!response.ok || !response.body) {
            const payload = await readPayload(response);

            if (response.status >= 500 && attempt === 0) {
                retryAutomatically = true;

                return;
            }

            fail(
                response.status === 419
                    ? 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.'
                    : response.status === 429 && !payload.message
                      ? 'Hiciste muchas consultas seguidas. Espera un minuto e inténtalo de nuevo.'
                      : (payload.message ??
                        Object.values(payload.errors ?? {}).flat()[0] ??
                        'El asistente tardó demasiado en responder. Inténtalo de nuevo.'),
            );

            return;
        }

        messages.value.push({
            id: nextMessageId++,
            role: 'assistant',
            content: '',
            state: 'pending',
        });
        answer = messages.value[messages.value.length - 1];

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let finished = false;

        while (!finished) {
            const { value, done } = await reader.read();

            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });
            const events = buffer.split('\n\n');
            buffer = events.pop() ?? '';

            for (const raw of events) {
                const line = raw.trim();

                if (!line.startsWith('data:')) {
                    continue;
                }

                const event = JSON.parse(line.slice(5)) as {
                    type: 'delta' | 'done' | 'error';
                    text?: string;
                    notice?: string;
                    message?: string;
                };

                if (event.type === 'delta') {
                    answer.content += event.text ?? '';
                    void scrollToBottom();
                } else if (event.type === 'done') {
                    notice.value = event.notice ?? '';
                    finished = true;
                } else if (event.type === 'error') {
                    fail(event.message ?? 'La respuesta se interrumpió.');

                    return;
                }
            }
        }

        if (!answer.content.trim()) {
            fail(
                'El asistente devolvió una respuesta vacía. Inténtalo de nuevo.',
            );

            return;
        }

        answer.content = answer.content.trim();
        answer.state = 'sent';
        message.state = 'sent';
        message.image = null;
    } catch {
        if (attempt === 0 && !answer?.content) {
            if (answer) {
                messages.value = messages.value.filter(
                    (item) => item !== answer,
                );
            }

            retryAutomatically = true;
        } else {
            fail(
                'No se pudo conectar con el asistente. Comprueba tu conexión e inténtalo de nuevo.',
            );
        }
    } finally {
        loading.value = false;
        await scrollToBottom();
    }

    if (retryAutomatically) {
        await request(message, attempt + 1);
    }
};

const send = async (text?: string) => {
    const question = (text ?? prompt.value).trim();

    if (loading.value || (!question && !image.value)) {
        return;
    }

    messages.value.push({
        id: nextMessageId++,
        role: 'user',
        content: question || IMAGE_ONLY_LABEL,
        imageOnly: !question,
        state: 'pending',
        image: image.value,
        mode: mode.value,
    });
    prompt.value = '';
    releasePreview();
    image.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }

    await scrollToBottom();
    await request(messages.value[messages.value.length - 1]);
};

const retry = (message: ChatMessage) => {
    if (!loading.value) {
        void request(message);
    }
};

const discard = (message: ChatMessage) => {
    messages.value = messages.value.filter((item) => item.id !== message.id);
};

const resetConversation = () => {
    messages.value = [];
    notice.value = '';
};

onBeforeUnmount(releasePreview);
</script>

<template>
    <Head title="Asistente inteligente" />

    <main
        class="mx-auto flex w-full max-w-5xl min-w-0 flex-col gap-5 p-3 pb-24 sm:p-6"
    >
        <header
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="flex items-start gap-3">
                <div
                    class="rounded-xl bg-emerald-100 p-2 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                >
                    <Sparkles class="size-6" />
                </div>
                <div class="min-w-0">
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                    >
                        Asistente inteligente
                    </h1>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        Pregunta sobre prevención, emergencias, los 12 distritos
                        de Trujillo y las zonas seguras registradas en
                        RESGUARDA. Las consultas y fotografías se envían a
                        Google Gemini para su análisis y no se guardan en
                        RESGUARDA.
                    </p>
                </div>
            </div>
        </header>

        <section
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
            aria-label="Tipo de consulta"
        >
            <button
                v-for="item in modes"
                :key="item.id"
                type="button"
                class="rounded-xl border p-4 text-left transition"
                :class="
                    mode === item.id
                        ? 'border-emerald-600 bg-emerald-50 dark:border-emerald-500 dark:bg-emerald-950/40'
                        : 'border-slate-200 bg-white hover:border-slate-400 dark:border-slate-800 dark:bg-slate-950'
                "
                :aria-pressed="mode === item.id"
                @click="mode = item.id"
            >
                <span
                    class="font-semibold text-slate-900 dark:text-slate-100"
                    >{{ item.label }}</span
                >
                <span
                    class="mt-1 block text-sm text-slate-600 dark:text-slate-300"
                    >{{ item.description }}</span
                >
            </button>
        </section>

        <div class="space-y-2">
            <label
                class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <input
                    v-model="useLocation"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600"
                />
                <span>
                    <span class="font-medium text-slate-800 dark:text-slate-100"
                        >Usar mi ubicación para respuestas cercanas</span
                    >
                    <span
                        class="block text-xs text-slate-500 dark:text-slate-400"
                        >Solo se envía al servidor cuando preguntas; a la IA
                        únicamente se le comunica tu distrito y las distancias a
                        los lugares registrados, nunca tus coordenadas.</span
                    >
                </span>
            </label>
            <LocationPanel
                v-if="useLocation"
                :districts="props.districts"
                :auto-locate="true"
            />
            <p
                v-else-if="districtName"
                class="flex items-center gap-1 px-1 text-xs text-slate-500 dark:text-slate-400"
            >
                <MapPin class="h-3.5 w-3.5" /> Las respuestas considerarán el
                distrito {{ districtName }}.
            </p>
        </div>

        <section
            ref="conversation"
            class="flex max-h-[60vh] min-h-64 flex-col gap-4 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-950"
            aria-live="polite"
        >
            <div
                v-if="messages.length === 0"
                class="m-auto max-w-lg py-8 text-center"
            >
                <Bot
                    class="mx-auto size-10 text-emerald-600 dark:text-emerald-400"
                />
                <p class="mt-3 font-medium text-slate-800 dark:text-slate-200">
                    ¿En qué puedo orientarte?
                </p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Puedes hacer preguntas adicionales para continuar la
                    conversación.
                </p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <button
                        v-for="suggestion in suggestions"
                        :key="suggestion"
                        type="button"
                        class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
                        :disabled="loading"
                        @click="
                            mode = 'general';
                            send(suggestion);
                        "
                    >
                        {{ suggestion }}
                    </button>
                </div>
            </div>

            <div
                v-for="message in messages"
                v-show="message.content"
                :key="message.id"
                class="flex flex-col gap-1"
                :class="message.role === 'user' ? 'items-end' : 'items-start'"
            >
                <div
                    class="max-w-[90%] rounded-2xl px-4 py-3 text-sm break-words whitespace-pre-wrap sm:max-w-[80%]"
                    :class="[
                        message.role === 'user'
                            ? 'bg-emerald-600 text-white'
                            : 'bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-100',
                        message.state === 'pending' ? 'opacity-70' : '',
                        message.state === 'failed' ? 'ring-2 ring-red-400' : '',
                    ]"
                >
                    {{ message.content }}
                </div>
                <div
                    v-if="message.state === 'failed'"
                    class="flex max-w-[90%] flex-wrap items-center justify-end gap-2 text-xs text-red-700 sm:max-w-[80%] dark:text-red-300"
                    role="alert"
                >
                    <span>{{ message.error }}</span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md border border-red-300 px-2 py-1 font-semibold hover:bg-red-50 disabled:opacity-50 dark:border-red-800 dark:hover:bg-red-950/40"
                        :disabled="loading"
                        @click="retry(message)"
                    >
                        <RotateCcw class="h-3 w-3" /> Reintentar
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 hover:bg-red-50 dark:hover:bg-red-950/40"
                        aria-label="Descartar mensaje"
                        @click="discard(message)"
                    >
                        <Trash2 class="h-3 w-3" />
                    </button>
                </div>
            </div>

            <div
                v-if="loading && !receivingAnswer"
                class="flex items-center gap-2 text-sm text-slate-500"
                role="status"
            >
                <LoaderCircle class="size-4 animate-spin" /> Analizando la
                consulta…
            </div>
            <p
                v-if="notice"
                class="rounded-lg bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
            >
                <ShieldAlert class="mr-1 inline size-4" />{{ notice }}
            </p>
        </section>

        <form
            class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950"
            @submit.prevent="send()"
        >
            <label class="sr-only" for="assistant-prompt">Tu pregunta</label>
            <textarea
                id="assistant-prompt"
                v-model="prompt"
                rows="3"
                maxlength="4000"
                class="w-full resize-y rounded-xl border border-slate-300 bg-transparent p-3 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700"
                placeholder="Escribe tu pregunta…"
                @keydown.enter.exact.prevent="send()"
            />
            <div
                v-if="image"
                class="flex items-center justify-between gap-3 rounded-lg bg-slate-100 px-3 py-2 text-sm dark:bg-slate-900"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <img
                        v-if="imagePreview"
                        :src="imagePreview"
                        alt="Vista previa de la imagen adjunta"
                        class="h-12 w-12 shrink-0 rounded-md object-cover"
                    />
                    <span class="min-w-0 truncate">{{ image.name }}</span>
                </div>
                <button
                    type="button"
                    class="rounded p-1"
                    aria-label="Quitar imagen"
                    @click="clearImage"
                >
                    <X class="size-4" />
                </button>
            </div>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-wrap gap-2">
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="selectImage"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="loading"
                        @click="fileInput?.click()"
                    >
                        <Camera class="mr-2 size-4" /> Adjuntar fotografía
                    </Button>
                    <Button
                        v-if="messages.length"
                        type="button"
                        variant="ghost"
                        :disabled="loading"
                        @click="resetConversation"
                    >
                        Nueva conversación
                    </Button>
                </div>
                <Button
                    type="submit"
                    :disabled="loading || (!prompt.trim() && !image)"
                >
                    <LoaderCircle
                        v-if="loading"
                        class="mr-2 size-4 animate-spin"
                    />
                    <Send v-else class="mr-2 size-4" />
                    Enviar consulta
                </Button>
            </div>
            <p
                class="text-xs leading-relaxed text-slate-500 dark:text-slate-400"
            >
                La IA puede equivocarse y solo conoce los refugios y alertas
                registrados en RESGUARDA. No ofrece diagnósticos ni certifica
                estructuras; ante peligro inmediato llama al 105 (Policía), 116
                (Bomberos) o 106 (SAMU), o usa el botón SOS. Evita incluir
                rostros o documentos personales en las fotos.
            </p>
        </form>
    </main>
</template>
