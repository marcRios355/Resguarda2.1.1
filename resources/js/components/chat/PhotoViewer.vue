<script setup lang="ts">
import { X } from '@lucide/vue';
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps<{
    src: string | null;
    caption?: string;
}>();

const emit = defineEmits<{ close: [] }>();

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

watch(
    () => props.src,
    (src) => {
        if (src) {
            window.addEventListener('keydown', onKeydown);
        } else {
            window.removeEventListener('keydown', onKeydown);
        }
    },
);

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="src"
            class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/85 p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Fotografía ampliada"
            @click.self="emit('close')"
        >
            <button
                type="button"
                class="absolute top-4 right-4 rounded-full bg-white/15 p-2 text-white hover:bg-white/25"
                aria-label="Cerrar fotografía"
                @click="emit('close')"
            >
                <X class="h-5 w-5" />
            </button>
            <img
                :src="src"
                :alt="caption || 'Fotografía del chat'"
                class="max-h-[85vh] max-w-full rounded-lg object-contain"
            />
            <p
                v-if="caption"
                class="mt-3 max-w-2xl text-center text-sm text-white/90"
            >
                {{ caption }}
            </p>
        </div>
    </Teleport>
</template>
