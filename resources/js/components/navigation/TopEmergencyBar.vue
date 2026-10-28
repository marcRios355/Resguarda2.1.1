<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Shield, PhoneCall } from '@lucide/vue';

defineProps<{
    districts?: Array<{ id: number; name: string; risk_level: string }>;
}>();
</script>

<template>
    <div class="flex items-center gap-2 ml-auto">
        <!-- Botón Modo RESGUARDA -->
        <Link
            href="/resguarda-mode"
            class="hidden items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-100 px-2 py-1.5 text-xs font-semibold text-emerald-800 shadow-xs transition-colors hover:bg-emerald-200 dark:border-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 dark:hover:bg-emerald-900/80 sm:inline-flex sm:px-3"
        >
            <Shield class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
            <span>Modo RESGUARDA</span>
        </Link>

        <!-- Botón SOS de Emergencia -->
        <Link
            href="/emergency"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-red-600 px-2.5 py-1.5 text-xs font-bold text-white shadow-sm shadow-red-600/30 transition-colors hover:bg-red-700 sm:px-3"
        >
            <PhoneCall class="w-3.5 h-3.5" />
            <span>SOS</span>
        </Link>
    </div>
</template>