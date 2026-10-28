<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <path
            d="M24 4L8 10.5V22.2C8 32.8 14.8 42.4 24 44.5C33.2 42.4 40 32.8 40 22.2V10.5L24 4Z"
            fill="currentColor"
            fill-opacity="0.2"
            stroke="currentColor"
            stroke-width="3"
            stroke-linejoin="round"
        />
        <path
            d="M24 13V35M16 23H32M18 17L24 13L30 17"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
