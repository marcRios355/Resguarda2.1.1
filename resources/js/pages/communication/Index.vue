<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, MessagesSquare, Search, Send, UsersRound } from '@lucide/vue';
import { computed, ref } from 'vue';

type CommunityMessage = {
    id: number;
    user_name: string | null;
    district_name: string | null;
    message: string;
    created_at: string | null;
};

type ConversationSummary = {
    id: number;
    other_user_name: string | null;
    last_message: string | null;
    last_message_at: string | null;
};

type PrivateMessage = {
    id: number;
    sender_id: number;
    sender_name: string | null;
    message: string;
    created_at: string | null;
};

const props = defineProps<{
    tab: 'community' | 'private';
    districts: Array<{ id: number; name: string }>;
    selectedDistrictId: number | null;
    communityMessages: CommunityMessage[];
    conversations: ConversationSummary[];
    users: Array<{ id: number; name: string; username: string | null; email: string }>;
    search: string;
    activeConversation: { id: number; other_user_id: number; other_user_name: string } | null;
    privateMessages: PrivateMessage[];
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);
const csrfToken = computed(() => page.props.csrf_token ?? '');
const search = ref(props.search);
const districtId = ref(props.selectedDistrictId ? String(props.selectedDistrictId) : '');
const statusMessage = computed(() => {
    const status = page.flash.status;

    return typeof status === 'string' ? status : '';
});

const applySearch = () => {
    router.get('/communication', { tab: 'private', search: search.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const applyDistrict = () => {
    router.get('/communication', {
        tab: 'community',
        ...(districtId.value ? { district_id: districtId.value } : {}),
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const formatDate = (value: string | null) => value ? new Date(value).toLocaleString() : 'Reciente';
</script>

<template>
    <Head title="Centro de comunicación" />

    <main class="flex h-full min-w-0 flex-1 flex-col gap-4 p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">RESGUARDA</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Centro de comunicación</h1>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Conversación comunitaria y mensajes privados en un solo lugar.</p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                    <MessagesSquare class="h-4 w-4" />
                    Mensajes no instantáneos
                </div>
            </div>
        </section>

        <p v-if="statusMessage" role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ statusMessage }}
        </p>

        <nav aria-label="Secciones del centro de comunicación" class="grid grid-cols-2 rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <Link href="/communication?tab=community" class="flex min-h-11 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition-colors" :class="props.tab === 'community' && !props.activeConversation ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'">
                <UsersRound class="h-4 w-4" />
                Comunidad
            </Link>
            <Link href="/communication?tab=private" class="flex min-h-11 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition-colors" :class="props.tab === 'private' ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'">
                <MessagesSquare class="h-4 w-4" />
                Mensajes privados
            </Link>
        </nav>

        <template v-if="props.tab === 'community'">
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/20 sm:p-5">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Publicar en la comunidad</h2>
                <form method="post" action="/chat" class="mt-3 grid gap-3 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <label class="sr-only" for="community-message">Mensaje para la comunidad</label>
                    <textarea id="community-message" name="message" required maxlength="1000" rows="2" class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm dark:border-emerald-900 dark:bg-slate-900" placeholder="Comparte una actualización útil para la comunidad..." />
                    <label class="sr-only" for="community-district">Distrito del mensaje</label>
                    <select id="community-district" name="district_id" :value="props.selectedDistrictId ?? ''" class="rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-sm dark:border-emerald-900 dark:bg-slate-900">
                        <option value="">Todo Trujillo</option>
                        <option v-for="district in props.districts" :key="district.id" :value="district.id">{{ district.name }}</option>
                    </select>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        <Send class="h-4 w-4" />
                        Publicar
                    </button>
                </form>
            </section>

            <section class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Mensajes recientes</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ props.communityMessages.length }} mensajes mostrados</p>
                    </div>
                    <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="applyDistrict">
                        <label class="sr-only" for="district-filter">Filtrar mensajes por distrito</label>
                        <select id="district-filter" v-model="districtId" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <option value="">Todos los distritos</option>
                            <option v-for="district in props.districts" :key="district.id" :value="String(district.id)">{{ district.name }}</option>
                        </select>
                        <button type="submit" class="min-h-11 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Filtrar</button>
                    </form>
                </div>

                <article v-for="message in props.communityMessages" :key="message.id" class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-slate-800 dark:text-slate-100">{{ message.user_name ?? 'Usuario' }}</h3>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">{{ message.district_name ?? 'General' }}</span>
                    </div>
                    <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700 dark:text-slate-300">{{ message.message }}</p>
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ formatDate(message.created_at) }}</p>
                </article>
                <p v-if="props.communityMessages.length === 0" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">Aún no hay mensajes para este distrito.</p>
            </section>
        </template>

        <template v-else-if="props.activeConversation">
            <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-violet-200 bg-white/80 shadow-sm dark:border-violet-900/60 dark:bg-slate-950/70">
                <header class="flex items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
                    <Link href="/communication?tab=private" aria-label="Volver a mensajes privados" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800">
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold text-slate-900 dark:text-white">{{ props.activeConversation.other_user_name }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Conversación privada</p>
                    </div>
                </header>

                <div class="flex-1 space-y-3 overflow-y-auto p-4">
                    <p v-if="props.privateMessages.length === 0" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Todavía no hay mensajes en esta conversación.</p>
                    <div v-for="message in props.privateMessages" :key="message.id" class="flex" :class="message.sender_id === currentUserId ? 'justify-end' : 'justify-start'">
                        <article class="max-w-[90%] break-words rounded-2xl px-4 py-3 sm:max-w-[75%]" :class="message.sender_id === currentUserId ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'">
                            <p v-if="message.sender_id !== currentUserId" class="mb-1 text-xs font-semibold">{{ message.sender_name ?? props.activeConversation.other_user_name }}</p>
                            <p class="whitespace-pre-line text-sm leading-6">{{ message.message }}</p>
                            <p class="mt-2 text-right text-[11px] opacity-75">{{ formatDate(message.created_at) }}</p>
                        </article>
                    </div>
                </div>

                <form method="post" :action="`/communication/conversations/${props.activeConversation.id}`" class="grid gap-2 border-t border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-4 dark:border-slate-800">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <label class="sr-only" for="private-reply">Escribe un mensaje privado</label>
                    <textarea id="private-reply" name="message" required maxlength="1000" rows="2" class="w-full rounded-xl border border-violet-200 bg-white px-3 py-2.5 text-sm dark:border-violet-900 dark:bg-slate-900" placeholder="Escribe un mensaje..." />
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">
                        <Send class="h-4 w-4" />
                        Enviar
                    </button>
                </form>
            </section>
        </template>

        <template v-else>
            <section class="rounded-2xl border border-violet-200 bg-violet-50/70 p-4 shadow-sm dark:border-violet-900/60 dark:bg-violet-950/20 sm:p-5">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Buscar personas</h2>
                <form class="mt-3 flex flex-col gap-3 sm:flex-row" @submit.prevent="applySearch">
                    <label class="sr-only" for="user-search">Buscar por nombre, usuario o correo</label>
                    <div class="relative min-w-0 flex-1">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input id="user-search" v-model="search" type="search" class="min-h-11 w-full rounded-xl border border-violet-200 bg-white py-2.5 pl-10 pr-3 text-sm dark:border-violet-900 dark:bg-slate-900" placeholder="Nombre, usuario o correo" />
                    </div>
                    <button type="submit" class="min-h-11 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">Buscar</button>
                </form>

                <div v-if="props.users.length" class="mt-4 grid gap-3 lg:grid-cols-2">
                    <form v-for="user in props.users" :key="user.id" method="post" action="/private-chat" class="flex min-w-0 flex-col gap-3 rounded-xl border border-violet-100 bg-white p-3 sm:flex-row sm:items-center dark:border-violet-900/60 dark:bg-slate-900">
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <input type="hidden" name="recipient_id" :value="user.id" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ user.name }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ user.username ? `@${user.username}` : user.email }}</p>
                        </div>
                        <label class="sr-only" :for="`message-${user.id}`">Escribe a {{ user.name }}</label>
                        <input :id="`message-${user.id}`" name="message" required maxlength="1000" class="min-h-10 w-full min-w-0 rounded-lg border border-slate-200 px-2 py-2 text-sm sm:w-44 dark:border-slate-700 dark:bg-slate-950" placeholder="Escribe un mensaje" />
                        <button type="submit" class="min-h-10 shrink-0 rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white hover:bg-violet-700">Enviar</button>
                    </form>
                </div>
                <p v-else-if="props.search" class="mt-4 text-sm text-slate-600 dark:text-slate-300">No encontramos usuarios con esa búsqueda.</p>
            </section>

            <section>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Conversaciones</h2>
                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ props.conversations.length }}</span>
                </div>
                <div v-if="props.conversations.length" class="grid gap-3 lg:grid-cols-2">
                    <Link v-for="conversation in props.conversations" :key="conversation.id" :href="`/communication/conversations/${conversation.id}`" class="block min-w-0 rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm transition-colors hover:border-violet-300 dark:border-slate-800 dark:bg-slate-950/70 dark:hover:border-violet-800">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="min-w-0 truncate font-semibold text-slate-800 dark:text-slate-100">{{ conversation.other_user_name ?? 'Contacto' }}</h3>
                            <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ formatDate(conversation.last_message_at) }}</span>
                        </div>
                        <p class="mt-2 truncate text-sm text-slate-600 dark:text-slate-300">{{ conversation.last_message ?? 'Sin mensajes aún' }}</p>
                    </Link>
                </div>
                <p v-else class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">Todavía no tienes conversaciones. Busca a alguien para iniciar una.</p>
            </section>
        </template>
    </main>
</template>
