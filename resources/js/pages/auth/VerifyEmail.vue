<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Acceso disponible" />

    <div class="space-y-6 text-center">
        <p class="text-muted-foreground text-sm">
            Ya no es necesario verificar el correo electrónico para utilizar
            RESGUARDA.
        </p>
        <Link
            href="/dashboard"
            class="text-foreground underline decoration-neutral-300 underline-offset-4"
        >
            Ir a la aplicación
        </Link>
    </div>
</template>
