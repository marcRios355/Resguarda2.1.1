<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, Check, CheckCheck, Image as ImageIcon, MessagesSquare, Reply, Search, UsersRound, X } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import ChatComposer from '@/components/chat/ChatComposer.vue';
import PhotoViewer from '@/components/chat/PhotoViewer.vue';
import { useNotificationSummary } from '@/composables/useNotificationSummary';

type CommunityMessage = {
    id: number;
    user_id: number;
    user_name: string | null;
    user_username: string | null;
    is_mine: boolean;
    district_name: string | null;
    message: string;
    photo_url: string | null;
    reply_to: { id: number; user_name: string | null; excerpt: string } | null;
    created_at: string | null;
};

type ConversationSummary = {
    id: number;
    other_user_name: string | null;
    last_message: string | null;
    last_message_has_photo: boolean;
    last_message_at: string | null;
    unread_count: number;
};

type PrivateMessage = {
    id: number;
    sender_id: number;
    sender_name: string | null;
    message: string;
    photo_url: string | null;
    lost_found_item: { id: number; title: string; type: 'lost' | 'found' } | null;
    read_at: string | null;
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
const replyingTo = ref<CommunityMessage | null>(null);
const postDistrictId = ref(props.selectedDistrictId ? String(props.selectedDistrictId) : '');
const viewer = ref<{ src: string; caption: string } | null>(null);
const messageList = ref<HTMLElement | null>(null);
const { refresh: refreshNotifications } = useNotificationSummary();

/*
 * Actualización periódica de los mensajes sin recargar la página. Solo se
 * piden las propiedades de la vista activa para mantener las respuestas ligeras.
 */
usePoll(8000, {
    only: props.activeConversation
        ? ['privateMessages']
        : props.tab === 'private'
          ? ['conversations']
          : ['communityMessages'],
    onSuccess: () => void refreshNotifications(),
});

const communityExtra = computed(() => ({
    district_id: postDistrictId.value || null,
    reply_to_id: replyingTo.value?.id ?? null,
    filter_district_id: props.selectedDistrictId,
}));

const startReply = (message: CommunityMessage) => {
    replyingTo.value = message;
    document.getElementById('community-message')?.focus();
};

const openPhoto = (src: string, caption: string) => {
    viewer.value = { src, caption };
};

const scrollConversationToBottom = async () => {
    await nextTick();
    messageList.value?.scrollTo({ top: messageList.value.scrollHeight });
};

watch(() => props.privateMessages.length, scrollConversationToBottom);

onMounted(() => {
    void scrollConversationToBottom();
    void refreshNotifications();

    const hash = window.location.hash;

    if (hash.startsWith('#mensaje-')) {
        document.querySelector(hash)?.scrollIntoView({ block: 'center' });
    }
});
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
                    Se actualiza automáticamente
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
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Menciona a alguien con @usuario para avisarle. Puedes adjuntar una fotografía (JPG, PNG o WebP, máx. 8 MB).</p>
                <div v-if="replyingTo" class="mt-3 flex items-start justify-between gap-2 rounded-xl border border-emerald-200 bg-white px-3 py-2 text-xs dark:border-emerald-900 dark:bg-slate-900">
                    <p class="min-w-0 truncate text-slate-600 dark:text-slate-300">
                        Respondiendo a <strong>{{ replyingTo.user_name ?? 'Usuario' }}</strong>: {{ replyingTo.message || 'Fotografía' }}
                    </p>
                    <button type="button" class="shrink-0 rounded p-0.5 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Cancelar respuesta" @click="replyingTo = null">
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>
                <ChatComposer
                    class="mt-3"
                    action="/chat"
                    input-id="community-message"
                    label="Mensaje para la comunidad"
                    placeholder="Comparte una actualización útil para la comunidad..."
                    submit-label="Publicar"
                    :extra="communityExtra"
                    @sent="replyingTo = null"
                >
                    <label class="flex flex-col gap-1 text-xs font-medium text-slate-600 sm:flex-row sm:items-center sm:gap-2 dark:text-slate-300">
                        <span>Distrito del mensaje</span>
                        <select v-model="postDistrictId" class="rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm dark:border-emerald-900 dark:bg-slate-900">
                            <option value="">Todo Trujillo</option>
                            <option v-for="district in props.districts" :key="district.id" :value="String(district.id)">{{ district.name }}</option>
                        </select>
                    </label>
                </ChatComposer>
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

                <article v-for="message in props.communityMessages" :id="`mensaje-${message.id}`" :key="message.id" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-slate-800 dark:text-slate-100">
                            {{ message.user_name ?? 'Usuario' }}
                            <span v-if="message.user_username" class="text-xs font-normal text-slate-500 dark:text-slate-400">@{{ message.user_username }}</span>
                        </h3>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">{{ message.district_name ?? 'General' }}</span>
                    </div>
                    <a v-if="message.reply_to" :href="`#mensaje-${message.reply_to.id}`" class="mt-3 block rounded-lg border-l-4 border-emerald-400 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                        En respuesta a <strong>{{ message.reply_to.user_name ?? 'Usuario' }}</strong>: {{ message.reply_to.excerpt }}
                    </a>
                    <p v-if="message.message" class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700 dark:text-slate-300">{{ message.message }}</p>
                    <button v-if="message.photo_url" type="button" class="mt-3 block overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700" :aria-label="`Ampliar fotografía de ${message.user_name ?? 'Usuario'}`" @click="openPhoto(message.photo_url, message.message)">
                        <img :src="message.photo_url" :alt="`Fotografía compartida por ${message.user_name ?? 'Usuario'}`" loading="lazy" class="max-h-80 w-auto max-w-full object-cover" />
                    </button>
                    <div class="mt-3 flex items-center justify-between gap-2">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(message.created_at) }}</p>
                        <button v-if="!message.is_mine" type="button" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/40" @click="startReply(message)">
                            <Reply class="h-3.5 w-3.5" /> Responder
                        </button>
                    </div>
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

                <div ref="messageList" class="max-h-[60vh] flex-1 space-y-3 overflow-y-auto p-4">
                    <p v-if="props.privateMessages.length === 0" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Todavía no hay mensajes en esta conversación.</p>
                    <div v-for="message in props.privateMessages" :key="message.id" class="flex" :class="message.sender_id === currentUserId ? 'justify-end' : 'justify-start'">
                        <article class="max-w-[90%] break-words rounded-2xl px-4 py-3 sm:max-w-[75%]" :class="message.sender_id === currentUserId ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'">
                            <p v-if="message.sender_id !== currentUserId" class="mb-1 text-xs font-semibold">{{ message.sender_name ?? props.activeConversation.other_user_name }}</p>
                            <Link v-if="message.lost_found_item" :href="`/lost-found/${message.lost_found_item.id}`" class="mb-2 block rounded-lg border border-current/20 bg-black/5 px-3 py-2 text-xs dark:bg-white/5">
                                {{ message.lost_found_item.type === 'lost' ? 'Sobre el objeto perdido' : 'Sobre el objeto encontrado' }}: <strong>{{ message.lost_found_item.title }}</strong>
                            </Link>
                            <button v-if="message.photo_url" type="button" class="mb-2 block overflow-hidden rounded-xl" aria-label="Ampliar fotografía" @click="openPhoto(message.photo_url, message.message)">
                                <img :src="message.photo_url" alt="Fotografía enviada en la conversación" loading="lazy" class="max-h-72 w-auto max-w-full object-cover" />
                            </button>
                            <p v-if="message.message" class="whitespace-pre-line text-sm leading-6">{{ message.message }}</p>
                            <p class="mt-2 flex items-center justify-end gap-1 text-[11px] opacity-75">
                                {{ formatDate(message.created_at) }}
                                <template v-if="message.sender_id === currentUserId">
                                    <CheckCheck v-if="message.read_at" class="h-3.5 w-3.5" aria-label="Leído" />
                                    <Check v-else class="h-3.5 w-3.5" aria-label="Enviado" />
                                </template>
                            </p>
                        </article>
                    </div>
                </div>

                <ChatComposer
                    class="border-t border-slate-200 p-3 sm:p-4 dark:border-slate-800"
                    :action="`/communication/conversations/${props.activeConversation.id}`"
                    input-id="private-reply"
                    label="Escribe un mensaje privado"
                    placeholder="Escribe un mensaje..."
                    accent="violet"
                />
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
                            <span class="flex shrink-0 items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                {{ formatDate(conversation.last_message_at) }}
                                <span v-if="conversation.unread_count > 0" class="rounded-full bg-violet-600 px-2 py-0.5 text-[10px] font-bold text-white" :aria-label="`${conversation.unread_count} mensajes sin leer`">{{ conversation.unread_count }}</span>
                            </span>
                        </div>
                        <p class="mt-2 flex items-center gap-1 truncate text-sm" :class="conversation.unread_count > 0 ? 'font-semibold text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-300'">
                            <ImageIcon v-if="conversation.last_message_has_photo" class="h-3.5 w-3.5 shrink-0" />
                            {{ conversation.last_message ?? 'Sin mensajes aún' }}
                        </p>
                    </Link>
                </div>
                <p v-else class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">Todavía no tienes conversaciones. Busca a alguien para iniciar una.</p>
            </section>
        </template>

        <PhotoViewer :src="viewer?.src ?? null" :caption="viewer?.caption" @close="viewer = null" />
    </main>
</template>
