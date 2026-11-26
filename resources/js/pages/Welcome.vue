<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BellRing, LogIn, MapPinned, ShieldCheck, Sparkles, UserPlus } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { login, register } from '@/routes';

withDefaults(defineProps<{ canRegister?: boolean }>(), { canRegister: true });

const features = [
    { icon: MapPinned, title: 'Zonas seguras', text: 'La más cercana a ti y si todavía tiene espacio.' },
    { icon: BellRing, title: 'Alertas y reportes', text: 'Lo que pasa en tu distrito, validado por vecinos.' },
    { icon: Sparkles, title: 'SOS y asistente IA', text: 'Ayuda inmediata y orientación con datos reales.' },
];
</script>

<template>
    <Head title="Bienvenido" />

    <div class="relative flex min-h-dvh flex-col overflow-hidden bg-[#0b1a2e] text-white">
        <!-- Fondo: mapa de Trujillo con distritos y zonas seguras -->
        <img
            src="/images/fondo-trujillo.jpg"
            alt=""
            aria-hidden="true"
            class="absolute inset-0 h-full w-full scale-105 object-cover opacity-90"
        />
        <div class="absolute inset-0 bg-gradient-to-b from-[#0b1a2e]/80 via-[#0b1a2e]/55 to-[#0b1a2e]/95" />
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(16,185,129,0.18),transparent_60%)]" />

        <!-- Barra superior -->
        <header class="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
            <div class="flex items-center gap-2.5">
                <AppLogoIcon class="h-9 w-8 text-white" />
                <span class="text-lg font-bold tracking-[0.18em]">RESGUARDA</span>
            </div>
            <Link
                :href="login()"
                class="hidden rounded-full border border-white/25 px-4 py-2 text-sm font-medium text-white/90 backdrop-blur transition hover:bg-white/10 sm:inline-flex"
            >
                Iniciar sesión
            </Link>
        </header>

        <!-- Contenido principal -->
        <main class="relative z-10 flex flex-1 flex-col items-center justify-center px-5 pb-10 text-center sm:px-8">
            <div class="relative mb-7">
                <div class="absolute inset-0 -z-10 scale-150 rounded-full bg-emerald-500/25 blur-3xl" />
                <AppLogoIcon class="h-36 w-32 text-white drop-shadow-[0_10px_30px_rgba(16,185,129,0.45)] sm:h-44 sm:w-40" />
            </div>

            <h1 class="text-5xl font-extrabold tracking-[0.16em] sm:text-7xl">RESGUARDA</h1>
            <p class="mt-4 max-w-xl text-base text-white/80 sm:text-lg">
                Prevención, información y respuesta ante emergencias en los
                <span class="font-semibold text-emerald-300">12 distritos de Trujillo</span>.
            </p>

            <div class="mt-9 flex w-full max-w-sm flex-col gap-3 sm:max-w-none sm:flex-row sm:justify-center">
                <Link
                    v-if="canRegister"
                    :href="register()"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-7 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-900/40 transition hover:bg-emerald-400 focus-visible:ring-4 focus-visible:ring-emerald-300/50 focus-visible:outline-none"
                >
                    <UserPlus class="size-5" /> Crear cuenta
                </Link>
                <Link
                    :href="login()"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/40 bg-white/10 px-7 py-3.5 text-base font-semibold text-white backdrop-blur transition hover:bg-white/20 focus-visible:ring-4 focus-visible:ring-white/30 focus-visible:outline-none"
                >
                    <LogIn class="size-5" /> Iniciar sesión
                </Link>
            </div>

            <div class="mt-14 grid w-full max-w-4xl gap-3 sm:grid-cols-3">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-2xl border border-white/10 bg-white/[0.06] p-5 text-left backdrop-blur-md"
                >
                    <div class="mb-3 inline-flex rounded-lg bg-emerald-500/15 p-2 text-emerald-300">
                        <component :is="feature.icon" class="size-5" />
                    </div>
                    <p class="font-semibold">{{ feature.title }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-white/70">{{ feature.text }}</p>
                </div>
            </div>
        </main>

        <!-- Pie -->
        <footer class="relative z-10 mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-2 px-5 py-5 text-xs text-white/55 sm:flex-row sm:px-8">
            <span class="inline-flex items-center gap-1.5">
                <ShieldCheck class="size-3.5 text-emerald-300" />
                Emergencias: Policía 105 · Bomberos 116 · SAMU 106
            </span>
            <span>Mapa © OpenStreetMap · Tecsup Trujillo 2026</span>
        </footer>
    </div>
</template>
