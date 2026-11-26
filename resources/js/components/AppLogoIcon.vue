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
    <!--
        Escudo de RESGUARDA. Se dibuja como máscara para que tome el color del
        texto (currentColor) y funcione sobre fondos claros u oscuros.
    -->
    <span
        role="img"
        aria-label="RESGUARDA"
        class="inline-block shrink-0 bg-current"
        :class="className"
        :style="{
            maskImage: 'url(/images/resguarda-mark.png)',
            WebkitMaskImage: 'url(/images/resguarda-mark.png)',
            maskSize: 'contain',
            WebkitMaskSize: 'contain',
            maskRepeat: 'no-repeat',
            WebkitMaskRepeat: 'no-repeat',
            maskPosition: 'center',
            WebkitMaskPosition: 'center',
        }"
        v-bind="$attrs"
    />
</template>
