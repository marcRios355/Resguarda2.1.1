<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Recuperar contraseña',
        description: 'Ingresa tu correo registrado para recibir el enlace de recuperación',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Recuperar Contraseña - RESGUARDA" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 p-2.5 rounded-md border border-emerald-200 dark:border-emerald-800"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">Correo electrónico registrado</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="correo@ejemplo.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 shadow-sm"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Enviar enlace de recuperación
                </Button>
            </div>
        </Form>

        <div class="text-muted-foreground space-x-1 text-center text-sm">
            <span>O volver al</span>
            <TextLink :href="login()" class="underline underline-offset-4 text-emerald-600 dark:text-emerald-400 font-medium">inicio de sesión</TextLink>
        </div>
    </div>
</template>
