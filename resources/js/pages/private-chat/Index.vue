<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { MessagesSquare, Search } from '@lucide/vue';
import { ref } from 'vue';

const search = ref('');
const page = usePage();
const csrfToken = page.props.csrf_token ?? '';

const searchUsers = () => {
    router.get('/private-chat', { search: search.value }, { preserveState: true, replace: true });
};

const props = defineProps<{
    conversations: Array<{
        id: number;
        other_user_name: string | null;
        last_message: string | null;
        last_message_at: string | null;
    }>;
    users: Array<{
        id: number;
        name: string;
        username: string | null;
        email: string;
    }>;
    search: string;
}>();
</script>

<template>
    <Head title="Mensajes privados" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-600 dark:text-violet-400">Mensajería</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Mensajes privados</h1>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 dark:border-violet-900/70 dark:bg-violet-950/50 dark:text-violet-300">
                    <MessagesSquare class="h-3.5 w-3.5" />
                    {{ props.conversations.length }} conversaciones
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-violet-200 bg-violet-50/70 p-4 shadow-sm dark:border-violet-900/60 dark:bg-violet-950/20 sm:p-5">
            <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="searchUsers">
                <label class="sr-only" for="user-search">Buscar usuarios</label>
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input id="user-search" v-model="search" type="search" class="w-full rounded-xl border border-violet-200 bg-white py-2.5 pl-10 pr-3 text-sm dark:border-violet-900 dark:bg-slate-900" placeholder="Buscar por nombre, usuario o correo" />
                </div>
                <button type="submit" class="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">Buscar</button>
            </form>

            <div v-if="props.users.length" class="mt-4 grid gap-3 sm:grid-cols-2">
                <form v-for="user in props.users" :key="user.id" method="post" action="/private-chat" class="flex min-w-0 flex-col gap-3 rounded-xl border border-violet-100 bg-white p-3 sm:flex-row sm:items-center dark:border-violet-900/60 dark:bg-slate-900">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input type="hidden" name="recipient_id" :value="user.id" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ user.name }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ user.username ? `@${user.username}` : user.email }}</p>
                    </div>
                    <input name="message" required maxlength="1000" class="w-full min-w-0 rounded-lg border border-slate-200 px-2 py-2 text-xs sm:w-40 sm:py-1.5 dark:border-slate-700 dark:bg-slate-950" placeholder="Escribe un mensaje" />
                    <button type="submit" class="w-full shrink-0 rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-700 sm:w-auto sm:py-1.5">Enviar</button>
                </form>
            </div>
            <p v-else-if="props.search" class="mt-4 text-sm text-slate-600 dark:text-slate-300">No encontramos usuarios con esa búsqueda.</p>
        </section>

        <section class="space-y-4">
            <div v-for="conversation in props.conversations" :key="conversation.id" class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0 break-words font-semibold text-slate-800 dark:text-slate-100">{{ conversation.other_user_name ?? 'Contacto' }}</div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ conversation.last_message_at ? new Date(conversation.last_message_at).toLocaleString() : 'Sin actividad' }}</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ conversation.last_message ?? 'Sin mensajes aún' }}</p>
            </div>
        </section>
    </div>
</template>
