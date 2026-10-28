<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    districts: Array<{
        id: number;
        name: string;
        risk_level: string;
        risk_score: number;
        summary: string;
        history: Array<{
            label: string;
            level: string;
            score: number;
        }>;
    }>;
}>();

const riskBadgeStyles: Record<string, string> = {
    safe: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    caution: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    risk: 'bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300',
    high_risk: 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300',
    critical: 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
    clearing: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

</script>

<template>
    <Head title="Índice de riesgo RESGUARDA" />

    <div class="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-3 sm:p-4 lg:p-6">
        <section class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 sm:p-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-600 dark:text-red-400">Riesgo</p>
                <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Índice de riesgo RESGUARDA</h1>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="district in props.districts" :key="district.id" class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ district.name }}</h2>
                    <span :class="['rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide', riskBadgeStyles[district.risk_level] ?? 'bg-slate-200 text-slate-700']">
                        {{ district.risk_level }}
                    </span>
                </div>
                <p class="mt-4 text-4xl font-black text-slate-900 dark:text-white">{{ district.risk_score }}%</p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ district.summary }}</p>
                <div class="mt-4 space-y-2">
                    <div v-for="point in district.history.slice(-4)" :key="point.label" class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
                        <span>{{ point.label }}</span>
                        <span>{{ point.level }}</span>
                        <span>{{ point.score }}%</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
