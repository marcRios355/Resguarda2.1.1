<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    item: Listing;
    categories: Record<string, string>;
    types: Record<string, string>;
    statuses: Record<string, string>;
}>();

const page = usePage();

const statusMessage = computed(() => {
    const status = page.flash.status;

    return typeof status === 'string' ? status : '';
});
</script>

<template>
    <Head :title="props.item.title" />

    <div class="mx-auto flex w-full min-w-0 max-w-4xl flex-1 flex-col gap-4 p-3 sm:p-4 lg:p-6">
        <p v-if="statusMessage" role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ statusMessage }}
        </p>

        <Link href="/lost-found" class="inline-flex min-h-11 w-fit items-center rounded-lg px-3 text-sm font-medium text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/50">
            ← Volver a publicaciones
        </Link>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white/80 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
            <img v-if="props.item.photo_url" :src="props.item.photo_url" :alt="`Fotografía: ${props.item.title}`" class="max-h-[32rem] w-full object-cover" />
            <div class="p-4 sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="props.item.type === 'lost' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'">
                        {{ props.types[props.item.type] }}
                    </span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ props.categories[props.item.category] }}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ props.statuses[props.item.status] }}</span>
                </div>

                <h1 class="mt-4 break-words text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">{{ props.item.title }}</h1>
                <p class="mt-4 whitespace-pre-line break-words text-base leading-7 text-slate-700 dark:text-slate-200">{{ props.item.description }}</p>

                <dl class="mt-6 grid gap-4 border-t border-slate-200 pt-5 text-sm dark:border-slate-800 sm:grid-cols-2">
                    <div>
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">Zona aproximada</dt>
                        <dd class="mt-1 break-words text-slate-800 dark:text-slate-100">{{ props.item.approximate_zone }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">Fecha del suceso</dt>
                        <dd class="mt-1 text-slate-800 dark:text-slate-100">{{ props.item.event_date }}</dd>
                    </div>
                    <div v-if="props.item.created_at">
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">Publicado</dt>
                        <dd class="mt-1 text-slate-800 dark:text-slate-100">{{ props.item.created_at }}</dd>
                    </div>
                </dl>

                <p class="mt-5 rounded-xl bg-slate-50 p-3 text-sm leading-6 text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                    Por privacidad, esta publicación no muestra datos de contacto ni la identidad de quien la creó. No publiques direcciones particulares ni datos personales en tus mensajes.
                </p>
            </div>
        </article>

        <section v-if="props.item.is_owner" class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20 sm:p-6">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Gestionar mi publicación</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Solo tú puedes editar, resolver o eliminar este aviso.</p>

            <Form :action="`/lost-found/${props.item.id}`" method="put" enctype="multipart/form-data" v-slot="{ errors, processing }" class="mt-5 grid min-w-0 gap-4 md:grid-cols-2">
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Tipo de aviso
                    <select name="type" required :value="props.item.type" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option v-for="(label, value) in props.types" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <InputError class="mt-1" :message="errors.type" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Categoría
                    <select name="category" required :value="props.item.category" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option v-for="(label, value) in props.categories" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <InputError class="mt-1" :message="errors.category" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Título
                    <input name="title" required maxlength="150" :value="props.item.title" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" />
                    <InputError class="mt-1" :message="errors.title" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">
                    Descripción
                    <textarea name="description" required maxlength="2000" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">{{ props.item.description }}</textarea>
                    <InputError class="mt-1" :message="errors.description" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Fecha del suceso
                    <input name="event_date" type="date" required :value="props.item.event_date" :max="new Date().toISOString().slice(0, 10)" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" />
                    <InputError class="mt-1" :message="errors.event_date" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Zona aproximada
                    <input name="approximate_zone" required maxlength="150" :value="props.item.approximate_zone" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900" />
                    <span class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400">Usa un barrio o punto público; no una dirección particular.</span>
                    <InputError class="mt-1" :message="errors.approximate_zone" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Estado
                    <select name="status" required :value="props.item.status" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <option v-for="(label, value) in props.statuses" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <InputError class="mt-1" :message="errors.status" />
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Reemplazar fotografía (opcional)
                    <input name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900" />
                    <InputError class="mt-1" :message="errors.photo" />
                </label>

                <button type="submit" :disabled="processing" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60 md:col-span-2">
                    {{ processing ? 'Guardando…' : 'Guardar cambios' }}
                </button>
            </Form>

            <Form :action="`/lost-found/${props.item.id}`" method="delete" v-slot="{ processing }" class="mt-3">
                <button type="submit" :disabled="processing" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60 sm:w-auto dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40">
                    {{ processing ? 'Eliminando…' : 'Eliminar publicación' }}
                </button>
            </Form>
        </section>
    </div>
</template>
