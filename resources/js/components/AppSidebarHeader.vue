<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import TopEmergencyBar from '@/components/navigation/TopEmergencyBar.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="border-sidebar-border/70 flex h-16 min-w-0 shrink-0 items-center justify-between gap-2 border-b px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:px-4 md:px-6 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="min-w-0 truncate">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </div>
            </template>
        </div>

        <TopEmergencyBar />
    </header>
</template>
