<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Crear una cuenta en RESGUARDA',
        description: 'Únete a la red de prevención y respuesta ante emergencias de Trujillo',
    },
});
</script>

<template>
    <Head title="Crear cuenta" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="name">Nombres</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="given-name"
                        name="name"
                        placeholder="Ej. Víctor"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="last_name">Apellidos</Label>
                    <Input
                        id="last_name"
                        type="text"
                        required
                        :tabindex="2"
                        autocomplete="family-name"
                        name="last_name"
                        placeholder="Ej. Mendoza"
                    />
                    <InputError :message="errors.last_name" />
                </div>
            </div>

            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <Label for="username">Nombre de usuario</Label>
                    <span class="text-xs text-muted-foreground">Único para búsqueda y chats</span>
                </div>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground font-mono text-sm">@</span>
                    <Input
                        id="username"
                        type="text"
                        required
                        :tabindex="3"
                        autocomplete="username"
                        name="username"
                        class="pl-7 font-medium"
                        placeholder="VictorResguarda"
                    />
                </div>
                <InputError :message="errors.username" />
            </div>

            <div class="grid gap-1.5">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="4"
                    autocomplete="email"
                    name="email"
                    placeholder="victor@ejemplo.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-1.5">
                <Label for="password">Contraseña</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Mínimo 8 caracteres"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-1.5">
                <Label for="password_confirmation">Confirmar contraseña</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="6"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Repite tu contraseña"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 shadow-sm"
                tabindex="7"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Crear cuenta
            </Button>
        </div>

        <div class="text-muted-foreground text-center text-sm pt-2">
            ¿Ya tienes una cuenta?
            <TextLink
                :href="login()"
                class="underline underline-offset-4 text-emerald-600 dark:text-emerald-400 font-medium"
                :tabindex="8"
            >
                Iniciar sesión
            </TextLink>
        </div>
    </Form>
</template>
