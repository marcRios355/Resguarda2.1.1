<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MessageSquareText } from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token ?? '');

const props = defineProps<{
    districts: Array<{ id: number; name: string }>;
    messages: Array<{
        id: number;
        user_name: string | null;
        district_name: string | null;
        message: string;
        created_at: string | null;
    }>;
}>();
</script>

<template>
    <Head title="Chat comunitario" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">Comunidad</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Chat comunitario</h1>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/50 dark:text-emerald-300">
                    <MessageSquareText class="h-3.5 w-3.5" />
                    {{ props.messages.length }} mensajes
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/20 sm:p-5">
            <form method="post" action="/chat" class="grid gap-3 md:grid-cols-[1fr_220px_auto]">
                <input type="hidden" name="_token" :value="csrfToken" />
                <textarea name="message" required maxlength="1000" rows="2" class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm dark:border-emerald-900 dark:bg-slate-900" placeholder="Comparte una actualización útil para la comunidad..." />
                <select name="district_id" class="rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm dark:border-emerald-900 dark:bg-slate-900">
                    <option value="">Todo Trujillo</option>
                    <option v-for="district in props.districts" :key="district.id" :value="district.id">{{ district.name }}</option>
                </select>
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Publicar</button>
            </form>
        </section>

        <section class="space-y-4">
            <div v-for="message in props.messages" :key="message.id" class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <div class="flex items-center justify-between gap-3">
                    <div class="font-semibold text-slate-800 dark:text-slate-100">{{ message.user_name ?? 'Usuario' }}</div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ message.district_name ?? 'General' }}</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ message.message }}</p>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ message.created_at ? new Date(message.created_at).toLocaleString() : 'Reciente' }}</p>
            </div>
        </section>
    </div>
</template>
