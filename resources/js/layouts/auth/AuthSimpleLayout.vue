<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, BellRing, MapPinned, Sparkles } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const highlights = [
    { icon: MapPinned, text: 'Zonas seguras cercanas y su aforo en tiempo real' },
    { icon: BellRing, text: 'Alertas y reportes validados por tus vecinos' },
    { icon: Sparkles, text: 'Botón SOS y asistente con inteligencia artificial' },
];
</script>

<template>
    <div class="grid min-h-svh bg-background lg:grid-cols-[1.05fr_1fr]">
        <!-- Panel de marca (escritorio) -->
        <aside class="relative hidden overflow-hidden bg-[#0b1a2e] text-white lg:flex lg:flex-col">
            <img src="/images/fondo-trujillo.jpg" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover opacity-80" />
            <div class="absolute inset-0 bg-gradient-to-br from-[#0b1a2e]/90 via-[#0b1a2e]/60 to-[#0b1a2e]/90" />

            <Link :href="home()" class="relative z-10 flex items-center gap-2.5 p-10">
                <AppLogoIcon class="h-10 w-9 text-white" />
                <span class="text-xl font-bold tracking-[0.18em]">RESGUARDA</span>
            </Link>

            <div class="relative z-10 mt-auto p-10">
                <AppLogoIcon class="mb-6 h-24 w-20 text-emerald-300/90" />
                <h2 class="max-w-md text-3xl leading-tight font-bold">Información confiable cuando más se necesita.</h2>
                <p class="mt-3 max-w-md text-white/75">Prevención y respuesta ante emergencias en los 12 distritos de la provincia de Trujillo.</p>
                <ul class="mt-7 space-y-3">
                    <li v-for="item in highlights" :key="item.text" class="flex items-center gap-3 text-sm text-white/85">
                        <span class="inline-flex rounded-lg bg-emerald-500/15 p-2 text-emerald-300">
                            <component :is="item.icon" class="size-4" />
                        </span>
                        {{ item.text }}
                    </li>
                </ul>
                <p class="mt-10 text-xs text-white/45">Mapa © OpenStreetMap · Tecsup Trujillo 2026</p>
            </div>
        </aside>

        <!-- Formulario -->
        <main class="flex flex-col items-center justify-center p-5 sm:p-8">
            <div class="w-full max-w-sm">
                <Link
                    :href="home()"
                    class="mb-8 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> Volver al inicio
                </Link>

                <div class="mb-8 flex flex-col items-center gap-3 text-center">
                    <div class="flex items-center gap-2.5 lg:hidden">
                        <AppLogoIcon class="h-11 w-10 text-[#0b2545] dark:text-white" />
                        <span class="text-xl font-bold tracking-[0.18em] text-[#0b2545] dark:text-white">RESGUARDA</span>
                    </div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    <p class="text-sm text-muted-foreground">{{ description }}</p>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
