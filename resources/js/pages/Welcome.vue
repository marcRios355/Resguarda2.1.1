<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <Head title="RESGUARDA — Prevención y Emergencias en Trujillo">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-slate-100 to-emerald-50/30 text-slate-900 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 dark:text-slate-100 flex flex-col font-sans">
        <!-- Navegación Superior -->
        <header class="w-full border-b border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 dark:bg-emerald-500 text-white flex items-center justify-center shadow-md shadow-emerald-600/20">
                        <AppLogoIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <span class="font-extrabold text-xl tracking-tight text-slate-900 dark:text-white">RESGUARDA</span>
                        <span class="hidden sm:inline-block ml-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">Trujillo</span>
                    </div>
                </div>

                <nav class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition-all"
                    >
                        Ir al Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="px-4 py-2 text-sm font-medium rounded-lg text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            :href="register()"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition-all"
                        >
                            Crear cuenta
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="flex-1 flex flex-col justify-center items-center px-4 sm:px-6 lg:px-8 py-12 lg:py-20 max-w-5xl mx-auto w-full text-center">
            <!-- Badge de Alerta y Prevención -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 text-emerald-800 dark:text-emerald-300 text-xs sm:text-sm font-medium mb-6 animate-pulse">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Monitoreo activo para los 12 distritos de Trujillo</span>
            </div>

            <!-- Escudo Icono Hero -->
            <div class="mb-6 flex justify-center">
                <div class="relative">
                    <div class="absolute -inset-1 rounded-3xl bg-gradient-to-r from-emerald-500 to-teal-600 opacity-30 blur-xl"></div>
                    <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shadow-2xl">
                        <AppLogoIcon class="w-14 h-14 sm:w-16 sm:h-16" />
                    </div>
                </div>
            </div>

            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900 dark:text-white max-w-3xl leading-tight">
                Protección, Seguridad y Resguardo Ciudadano
            </h1>

            <p class="mt-4 text-base sm:text-lg lg:text-xl text-slate-600 dark:text-slate-300 max-w-2xl leading-relaxed">
                Plataforma integral de prevención, alerta temprana y comunicación en tiempo real ante situaciones de emergencia en la provincia de Trujillo.
            </p>

            <!-- Acciones Principales Hero -->
            <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3.5 w-full max-w-md">
                <Link
                    v-if="!$page.props.auth.user"
                    :href="register()"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 text-base font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/30 transition-all transform hover:-translate-y-0.5"
                >
                    🛡️ Registrarme en RESGUARDA
                </Link>
                <Link
                    v-if="!$page.props.auth.user"
                    :href="login()"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 text-base font-medium rounded-xl text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all"
                >
                    Iniciar sesión
                </Link>
                <Link
                    v-else
                    :href="dashboard()"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-3.5 text-base font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/30 transition-all"
                >
                    🗺️ Abrir Mapa de Emergencias
                </Link>
            </div>

            <!-- 4 Pilares de RESGUARDA -->
            <div class="mt-16 sm:mt-20 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 text-left w-full">
                <!-- Tarjeta 1: Mapa -->
                <div class="p-5 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/80 dark:border-slate-800/80 backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-xl mb-3">
                        🗺️
                    </div>
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Mapa de Riesgo</h3>
                    <p class="mt-1.5 text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Visualización por colores del estado de riesgo en los 12 distritos y ubicación en tiempo real.
                    </p>
                </div>

                <!-- Tarjeta 2: Zonas Seguras -->
                <div class="p-5 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/80 dark:border-slate-800/80 backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xl mb-3">
                        🟢
                    </div>
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Zonas Seguras</h3>
                    <p class="mt-1.5 text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Refugios verificados, aforo disponible, capacidad y servicios de primeros auxilios más cercanos.
                    </p>
                </div>

                <!-- Tarjeta 3: Reportes -->
                <div class="p-5 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/80 dark:border-slate-800/80 backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-950/70 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-xl mb-3">
                        📢
                    </div>
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Reportes Validados</h3>
                    <p class="mt-1.5 text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Reporte de huaicos, inundaciones y vías bloqueadas con validación comunitaria.
                    </p>
                </div>

                <!-- Tarjeta 4: Red y Chat -->
                <div class="p-5 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/80 dark:border-slate-800/80 backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-xl mb-3">
                        💬
                    </div>
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">Red Comunitaria</h3>
                    <p class="mt-1.5 text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Chat en vivo y mensajería privada con usuarios verificados mediante su @username único.
                    </p>
                </div>
            </div>
        </main>

        <!-- Pie de página -->
        <footer class="w-full border-t border-slate-200/80 dark:border-slate-800/80 py-6 text-center text-xs text-slate-500 dark:text-slate-400 bg-white/50 dark:bg-slate-900/50">
            <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center space-x-2">
                    <span class="font-bold text-slate-700 dark:text-slate-300">RESGUARDA</span>
                    <span>— Prevención y Respuesta Comunitaria</span>
                </div>
                <div class="text-slate-400 dark:text-slate-500">
                    Provincia de Trujillo, La Libertad, Perú • Enfoque Mobile-First
                </div>
            </div>
        </footer>
    </div>
</template>