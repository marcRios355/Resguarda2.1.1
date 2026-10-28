<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';

type Listing = {
    id: number;
    type: 'lost' | 'found';
    category: string;
    title: string;
    description: string;
    event_date: string;
    approximate_zone: string;
    photo_url: string | null;
    status: 'open' | 'resolved';
    created_at: string | null;
    is_owner: boolean;
};

const props = defineProps<{
    items: {
        data: Listing[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
    };
    categories: Record<string, string>;
    filters: {
        type: string;
        category: string;
    };
}>();

const filters = ref({ ...props.filters });
const page = usePage();

const statusMessage = computed(() => {
    const status = page.flash.status;

    return typeof status === 'string' ? status : '';
});

const applyFilters = () => {
    router.get('/lost-found', filters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const typeLabel = (type: Listing['type']) => type === 'lost' ? 'Perdido' : 'Encontrado';
</script>

<template>
    <Head title="Objetos perdidos y encontrados" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <p v-if="statusMessage" role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ statusMessage }}
        </p>

        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">Comunidad</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Objetos perdidos y encontrados</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                Comparte avisos para ayudar a recuperar objetos o reunirlos con quien los encontró.
                Publica solo una zona aproximada; no agregues datos de contacto ni direcciones particulares.
            </p>
        </section>

        <section class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/20 sm:p-5">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Crear publicación</h2>
            <Form action="/lost-found" method="post" enctype="multipart/form-data" v-slot="{ errors, processing }" class="mt-4 grid min-w-0 gap-4 md:grid-cols-2">
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Tipo de aviso
                    <select name="type" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option value="lost">Objeto perdido</option>
                        <option value="found">Objeto encontrado</option>
                    </select>
                    <InputError class="mt-1" :message="errors.type" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Categoría
                    <select name="category" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option v-for="(label, value) in props.categories" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <InputError class="mt-1" :message="errors.category" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Título
                    <input name="title" required maxlength="150" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" placeholder="Ej. Llaves encontradas cerca del parque" />
                    <InputError class="mt-1" :message="errors.title" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Descripción
                    <textarea name="description" required maxlength="2000" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" placeholder="Describe el objeto y cualquier detalle que ayude a identificarlo. No incluyas datos de contacto." />
                    <InputError class="mt-1" :message="errors.description" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Fecha en que se perdió o encontró
                    <input name="event_date" type="date" required :max="new Date().toISOString().slice(0, 10)" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" />
                    <InputError class="mt-1" :message="errors.event_date" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Zona aproximada
                    <input name="approximate_zone" required maxlength="150" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" placeholder="Barrio o punto público cercano" />
                    <span class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400">No escribas número de casa, departamento ni dirección particular.</span>
                    <InputError class="mt-1" :message="errors.approximate_zone" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Fotografía opcional
                    <input name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900" />
                    <span class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400">JPG, PNG o WebP; máximo 4 MB. La foto solo se sirve a usuarios autenticados.</span>
                    <InputError class="mt-1" :message="errors.photo" />
                </label>

                <button type="submit" :disabled="processing" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60 md:col-span-2">
                    {{ processing ? 'Publicando…' : 'Publicar objeto' }}
                </button>
            </Form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Buscar publicaciones</h2>
            <form class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="applyFilters">
                <label class="sr-only" for="listing-type-filter">Filtrar por tipo</label>
                <select id="listing-type-filter" v-model="filters.type" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option value="">Todos los tipos</option>
                    <option value="lost">Perdidos</option>
                    <option value="found">Encontrados</option>
                </select>
                <label class="sr-only" for="listing-category-filter">Filtrar por categoría</label>
                <select id="listing-category-filter" v-model="filters.category" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option value="">Todas las categorías</option>
                    <option v-for="(label, value) in props.categories" :key="value" :value="value">{{ label }}</option>
                </select>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600">
                    <Search class="h-4 w-4" />
                    Filtrar
                </button>
            </form>
        </section>

        <section class="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="item in props.items.data" :key="item.id" class="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white/80 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <img v-if="item.photo_url" :src="item.photo_url" :alt="`Fotografía: ${item.title}`" class="h-48 w-full object-cover" loading="lazy" />
                <div class="flex flex-1 flex-col p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="item.type === 'lost' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'">
                            {{ typeLabel(item.type) }}
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ props.categories[item.category] }}</span>
                        <span v-if="item.status === 'resolved'" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">Resuelto</span>
                    </div>
                    <h3 class="mt-3 break-words text-lg font-bold text-slate-900 dark:text-white">{{ item.title }}</h3>
                    <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ item.description }}</p>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ item.approximate_zone }} · {{ item.event_date }}</p>
                    <Link :href="`/lost-found/${item.id}`" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-xl border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950/50">
                        Ver publicación
                    </Link>
                </div>
            </article>
        </section>

        <p v-if="props.items.data.length === 0" class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
            No hay publicaciones que coincidan con esos filtros.
        </p>

        <nav v-if="props.items.last_page > 1" aria-label="Paginación de objetos" class="flex flex-wrap justify-center gap-2">
            <template v-for="link in props.items.links" :key="link.label">
                <Link v-if="link.url" :href="link.url" preserve-scroll class="inline-flex min-h-10 items-center rounded-lg border px-3 text-sm" :class="link.active ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800'" v-html="link.label" />
                <span v-else class="inline-flex min-h-10 items-center rounded-lg border border-slate-200 px-3 text-sm text-slate-400 dark:border-slate-800" v-html="link.label" />
            </template>
        </nav>
    </div>
</template>
