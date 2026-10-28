<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    district: {
        id: number;
        name: string;
        risk_level: string;
        risk_score: number;
        summary: string;
        factors: Record<string, number>;
        history: Array<{
            label: string;
            level: string;
            score: number;
        }>;
    };
}>();

const factorEntries = computed(() => Object.entries(props.district.factors ?? {}));
const maxScore = computed(() => Math.max(...props.district.history.map((point) => point.score), 100));

const riskColors: Record<string, string> = {
    safe: '#10b981',
    caution: '#f59e0b',
    risk: '#f97316',
    high_risk: '#ef4444',
    critical: '#8b5cf6',
    clearing: '#64748b',
};

</script>

<template>
    <Head :title="`Riesgo - ${props.district.name}`" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-600 dark:text-red-400">Detalle</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ props.district.name }}</h1>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Nivel actual: {{ props.district.risk_level }}</p>
                </div>
                <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/80 dark:bg-red-950/40">
                    <p class="text-xs uppercase tracking-[0.2em] text-red-600 dark:text-red-300">Puntaje</p>
                    <p class="mt-1 text-3xl font-black text-red-700 dark:text-red-300">{{ props.district.risk_score }}%</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Historial del riesgo</h2>
                <div class="mt-5 flex h-52 items-end gap-2 overflow-x-auto pb-2">
                    <div v-for="point in props.district.history" :key="point.label" class="flex min-w-[52px] flex-col items-center gap-2">
                        <div class="flex h-36 w-full items-end justify-center">
                            <div
                                class="w-full rounded-t-lg"
                                :style="{
                                    height: `${(point.score / maxScore) * 100}%`,
                                    background: riskColors[point.level] ?? '#60a5fa',
                                }"
                            />
                        </div>
                        <span class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ point.label }}</span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ point.score }}%</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Factores del riesgo</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ props.district.summary }}</p>
                <div class="mt-4 space-y-3">
                    <div v-for="[key, value] in factorEntries" :key="key" class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/80">
                        <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ key }}</span>
                            <span class="font-semibold text-slate-900 dark:text-white">{{ value }}</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${Math.min(100, Number(value) * 2.5)}%`,
                                    background: riskColors[props.district.risk_level] ?? '#60a5fa',
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
