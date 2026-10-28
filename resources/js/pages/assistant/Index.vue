<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import {
    Bot,
    Camera,
    LoaderCircle,
    Send,
    ShieldAlert,
    Sparkles,
    X,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';

type Mode = 'first_aid' | 'infrastructure' | 'objects';
type ChatMessage = { role: 'user' | 'assistant'; content: string };

const page = usePage();
const csrfToken = computed(() => String(page.props.csrf_token ?? ''));
const mode = ref<Mode>('first_aid');
const prompt = ref('');
const image = ref<File | null>(null);
const messages = ref<ChatMessage[]>([]);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const fileInput = ref<HTMLInputElement | null>(null);

const modes: Array<{ id: Mode; label: string; description: string }> = [
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

const selectImage = (event: Event) => {
    const target = event.target as HTMLInputElement;
    image.value = target.files?.[0] ?? null;
    error.value = '';
};

const clearImage = () => {
    image.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const send = async () => {
    const question = prompt.value.trim();
    if (loading.value || (!question && !image.value)) {
        return;
    }

    loading.value = true;
    error.value = '';
    notice.value = '';

    const form = new FormData();
    form.append('_token', csrfToken.value);
    form.append('mode', mode.value);
    form.append('prompt', question);
    messages.value.slice(-12).forEach((message, index) => {
        form.append(`messages[${index}][role]`, message.role);
        form.append(`messages[${index}][content]`, message.content);
    });
    if (image.value) {
        form.append('image', image.value);
    }

    try {
        const response = await fetch('/assistant/chat', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: form,
        });
        const payload = (await response.json()) as {
            answer?: string;
            message?: string;
            errors?: Record<string, string[]>;
            notice?: string;
        };

        if (!response.ok) {
            error.value =
                payload.message ??
                Object.values(payload.errors ?? {}).flat()[0] ??
                'No se pudo procesar la consulta.';
            return;
        }

        if (question) {
            messages.value.push({ role: 'user', content: question });
        } else if (image.value) {
            messages.value.push({
                role: 'user',
                content: 'He adjuntado una imagen para analizar.',
            });
        }
        messages.value.push({
            role: 'assistant',
            content: payload.answer ?? '',
        });
        notice.value = payload.notice ?? '';
        prompt.value = '';
        clearImage();
    } catch {
        error.value =
            'No se pudo conectar con el asistente. Comprueba tu conexión e inténtalo de nuevo.';
    } finally {
        loading.value = false;
    }
};

const resetConversation = () => {
    messages.value = [];
    notice.value = '';
    error.value = '';
};

onBeforeUnmount(clearImage);
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
                        Consulta orientación informativa o adjunta una imagen.
                        Las fotografías se envían a Google Gemini para su
                        análisis y no se guardan en RESGUARDA.
                    </p>
                </div>
            </div>
        </header>

        <section
            class="grid gap-3 md:grid-cols-3"
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

        <section
            class="flex min-h-64 flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-950"
            aria-live="polite"
        >
            <div
                v-if="messages.length === 0"
                class="m-auto max-w-md py-8 text-center"
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
            </div>

            <div
                v-for="(message, index) in messages"
                :key="index"
                class="flex gap-3"
                :class="
                    message.role === 'user' ? 'justify-end' : 'justify-start'
                "
            >
                <div
                    class="max-w-[90%] rounded-2xl px-4 py-3 text-sm break-words whitespace-pre-wrap sm:max-w-[80%]"
                    :class="
                        message.role === 'user'
                            ? 'bg-emerald-600 text-white'
                            : 'bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-100'
                    "
                >
                    {{ message.content }}
                </div>
            </div>

            <div
                v-if="loading"
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
            <p
                v-if="error"
                class="rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200"
                role="alert"
            >
                {{ error }}
            </p>
        </section>

        <form
            class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950"
            @submit.prevent="send"
        >
            <label class="sr-only" for="assistant-prompt">Tu pregunta</label>
            <textarea
                id="assistant-prompt"
                v-model="prompt"
                rows="3"
                maxlength="4000"
                class="w-full resize-y rounded-xl border border-slate-300 bg-transparent p-3 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700"
                placeholder="Escribe tu pregunta…"
            />
            <div
                v-if="image"
                class="flex items-center justify-between gap-3 rounded-lg bg-slate-100 px-3 py-2 text-sm dark:bg-slate-900"
            >
                <span class="min-w-0 truncate">{{ image.name }}</span>
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
                        capture="environment"
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
                La IA puede equivocarse. No ofrece diagnósticos ni certifica
                estructuras; ante peligro inmediato contacta los servicios de
                emergencia locales. Evita incluir rostros o documentos
                personales en las fotos.
            </p>
        </form>
    </main>
</template>
