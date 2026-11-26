<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Bot,
    ImagePlus,
    LoaderCircle,
    MapPin,
    RotateCcw,
    Send,
    SquarePen,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
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

const mode: Mode = 'general';
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

const suggestions = [
    '¿Zona segura más cercana?',
    '¿Hay alertas en mi distrito?',
    '¿Qué hago en un sismo?',
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
    form.append('mode', message.mode ?? mode);
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
        mode,
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

/**
 * Activa o desactiva el uso de la ubicación. Al activarla se solicita el
 * permiso; si se deniega, la opción vuelve a desactivarse.
 */
const toggleLocation = async () => {
    if (useLocation.value) {
        useLocation.value = false;

        return;
    }

    useLocation.value = true;
    const position = coords.value ?? (await locate({ force: true }));

    if (!position) {
        useLocation.value = false;
        notice.value = 'No se pudo obtener tu ubicación.';
    }
};

const resetConversation = () => {
    messages.value = [];
    notice.value = '';
};

onBeforeUnmount(releasePreview);
</script>

<template>
    <Head title="Asistente" />

    <main
        class="mx-auto flex h-[calc(100dvh-8rem)] w-full max-w-3xl min-w-0 flex-col p-2 sm:p-4"
    >
        <section
            class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
        >
            <header
                class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <div
                        class="rounded-full bg-emerald-100 p-1.5 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                    >
                        <Bot class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h1
                            class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                        >
                            Asistente RESGUARDA
                        </h1>
                        <p
                            class="truncate text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{
                                useLocation && districtName
                                    ? `Cerca de ${districtName}`
                                    : 'En línea'
                            }}
                        </p>
                    </div>
                </div>
                <button
                    v-if="messages.length"
                    type="button"
                    class="rounded-full p-2 text-slate-500 hover:bg-slate-100 disabled:opacity-50 dark:hover:bg-slate-900"
                    :disabled="loading"
                    aria-label="Nueva conversación"
                    title="Nueva conversación"
                    @click="resetConversation"
                >
                    <SquarePen class="size-5" />
                </button>
            </header>

            <div
                ref="conversation"
                class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-4"
                aria-live="polite"
            >
                <div
                    v-if="messages.length === 0"
                    class="m-auto flex flex-col items-center gap-3 text-center"
                >
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Hola, ¿en qué te ayudo?
                    </p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <button
                            v-for="suggestion in suggestions"
                            :key="suggestion"
                            type="button"
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
                            :disabled="loading"
                            @click="send(suggestion)"
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
                    :class="
                        message.role === 'user' ? 'items-end' : 'items-start'
                    "
                >
                    <div
                        class="max-w-[85%] rounded-2xl px-3.5 py-2 text-sm break-words whitespace-pre-wrap"
                        :class="[
                            message.role === 'user'
                                ? 'rounded-br-sm bg-emerald-600 text-white'
                                : 'rounded-bl-sm bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-100',
                            message.state === 'pending' ? 'opacity-70' : '',
                            message.state === 'failed'
                                ? 'ring-2 ring-red-400'
                                : '',
                        ]"
                    >
                        {{ message.content }}
                    </div>
                    <div
                        v-if="message.state === 'failed'"
                        class="flex max-w-[85%] flex-wrap items-center justify-end gap-2 text-xs text-red-700 dark:text-red-300"
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
                    class="flex w-fit items-center gap-1 rounded-2xl rounded-bl-sm bg-slate-100 px-3.5 py-3 dark:bg-slate-900"
                    role="status"
                    aria-label="Escribiendo"
                >
                    <span
                        class="size-1.5 animate-bounce rounded-full bg-slate-400"
                    />
                    <span
                        class="size-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:150ms]"
                    />
                    <span
                        class="size-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:300ms]"
                    />
                </div>
                <p
                    v-if="notice"
                    class="mx-auto text-center text-xs text-amber-700 dark:text-amber-300"
                >
                    {{ notice }}
                </p>
            </div>

            <form
                class="border-t border-slate-200 p-2 dark:border-slate-800"
                @submit.prevent="send()"
            >
                <div v-if="image" class="mb-2 flex items-center gap-2 px-1">
                    <img
                        v-if="imagePreview"
                        :src="imagePreview"
                        alt="Imagen adjunta"
                        class="h-12 w-12 rounded-lg object-cover"
                    />
                    <button
                        type="button"
                        class="rounded-full p-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-900"
                        aria-label="Quitar imagen"
                        @click="clearImage"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div class="flex items-end gap-1">
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="selectImage"
                    />
                    <button
                        type="button"
                        class="rounded-full p-2.5 text-slate-500 hover:bg-slate-100 disabled:opacity-50 dark:hover:bg-slate-900"
                        :disabled="loading"
                        aria-label="Adjuntar foto"
                        title="Adjuntar foto"
                        @click="fileInput?.click()"
                    >
                        <ImagePlus class="size-5" />
                    </button>
                    <button
                        type="button"
                        class="rounded-full p-2.5 hover:bg-slate-100 dark:hover:bg-slate-900"
                        :class="
                            useLocation
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-slate-500'
                        "
                        :aria-pressed="useLocation"
                        :title="
                            useLocation
                                ? 'Ubicación activada'
                                : 'Usar mi ubicación'
                        "
                        aria-label="Usar mi ubicación"
                        @click="toggleLocation"
                    >
                        <MapPin class="size-5" />
                    </button>
                    <label class="sr-only" for="assistant-prompt"
                        >Mensaje</label
                    >
                    <textarea
                        id="assistant-prompt"
                        v-model="prompt"
                        rows="1"
                        maxlength="4000"
                        class="max-h-32 min-h-10 flex-1 resize-none rounded-2xl border border-slate-300 bg-transparent px-3.5 py-2 text-sm outline-none focus:border-emerald-600 dark:border-slate-700"
                        placeholder="Escribe un mensaje…"
                        @keydown.enter.exact.prevent="send()"
                    />
                    <button
                        type="submit"
                        class="rounded-full bg-emerald-600 p-2.5 text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="loading || (!prompt.trim() && !image)"
                        aria-label="Enviar"
                    >
                        <LoaderCircle
                            v-if="loading"
                            class="size-5 animate-spin"
                        />
                        <Send v-else class="size-5" />
                    </button>
                </div>
                <p
                    class="mt-1 text-center text-[11px] text-slate-400 dark:text-slate-500"
                >
                    Emergencias: 105 · 116 · 106
                </p>
            </form>
        </section>
    </main>
</template>
