```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PhoneCall, ShieldAlert } from '@lucide/vue';

type EmergencyContact = {
    id: number;
    name: string;
    relationship: string | null;
    phone: string;
};

const props = defineProps<{
    contacts: EmergencyContact[];
}>();
</script>

<template>
    <Head title="Emergencia" />

    <div
        class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6"
    >
        <!-- CABECERA DE EMERGENCIA -->
        <section
            class="rounded-2xl border border-red-200 bg-gradient-to-br from-red-600 to-rose-700 p-4 text-white shadow-sm shadow-red-600/20 sm:p-5"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.2em] text-red-100"
                    >
                        Línea de emergencia
                    </p>

                    <h1 class="mt-2 text-2xl font-bold">
                        Emergencia activa
                    </h1>
                </div>

                <div
                    class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-red-50"
                >
                    <PhoneCall class="h-3.5 w-3.5" />
                    911 / 105
                </div>
            </div>
        </section>

        <!-- CONTENIDO PRINCIPAL -->
        <section class="grid gap-4 xl:grid-cols-2">
            <!-- PROCEDIMIENTO -->
            <div
                class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
            >
                <div
                    class="flex items-center gap-2 text-red-600 dark:text-red-400"
                >
                    <ShieldAlert class="h-4 w-4" />

                    <p
                        class="text-xs font-semibold uppercase tracking-[0.2em]"
                    >
                        Procedimiento
                    </p>
                </div>

                <ul
                    class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300"
                >
                    <li>
                        • Mantén la calma y no te alejes de zonas seguras.
                    </li>
                    <li>
                        • Contacta con autoridades locales o servicios de emergencia.
                    </li>
                    <li>
                        • Usa el modo RESGUARDA para consultar rutas y alertas.
                    </li>
                    <li>
                        • Informa a vecinos cercanos y sigue instrucciones oficiales.
                    </li>
                </ul>
            </div>

            <!-- CONTACTOS DE EMERGENCIA -->
            <div
                class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
            >
                <div
                    class="flex items-center gap-2 text-slate-700 dark:text-slate-200"
                >
                    <PhoneCall
                        class="h-4 w-4 text-red-600 dark:text-red-400"
                    />

                    <p
                        class="text-xs font-semibold uppercase tracking-[0.2em]"
                    >
                        Contacto rápido
                    </p>
                </div>

                <!-- MOSTRAR CONTACTOS REGISTRADOS -->
                <div
                    v-if="props.contacts && props.contacts.length > 0"
                    class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300"
                >
                    <div
                        v-for="contact in props.contacts"
                        :key="contact.id"
                        class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80"
                    >
                        <div
                            class="flex items-center justify-between gap-4"
                        >
                            <strong>{{ contact.name }}</strong>

                            <span
                                class="text-xs uppercase tracking-wide text-red-600 dark:text-red-400"
                            >
                                {{ contact.relationship || 'Contacto' }}
                            </span>
                        </div>

                        <p class="mt-2">
                            {{ contact.phone }}
                        </p>

                        <a
                            :href="`tel:${contact.phone}`"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-2 font-semibold text-white transition hover:bg-red-700"
                        >
                            <PhoneCall class="h-4 w-4" />
                            Llamar al contacto
                        </a>
                    </div>
                </div>

                <!-- MENSAJE CUANDO NO HAY CONTACTOS -->
                <div
                    v-else
                    class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-900/80 dark:text-slate-300"
                >
                    No tienes contactos de emergencia registrados.
                </div>
            </div>
        </section>
    </div>
</template>
```