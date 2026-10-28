<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Iniciar sesión en RESGUARDA',
        description: 'Ingresa tu correo o nombre de usuario para acceder',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Iniciar Sesión - RESGUARDA" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 p-2.5 rounded-md border border-emerald-200 dark:border-emerald-800"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-1.5">
                <Label for="email">Correo o @Nombre de usuario</Label>
                <Input
                    id="email"
                    type="text"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username email"
                    placeholder="correo@ejemplo.com o @usuario"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <Label for="password">Contraseña</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs text-muted-foreground hover:text-emerald-600 dark:hover:text-emerald-400 underline underline-offset-2"
                        :tabindex="5"
                    >
                        ¿Olvidaste tu contraseña?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="••••••••"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-2.5 cursor-pointer text-sm">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Recordar sesión</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 shadow-sm"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Ingresar a RESGUARDA
            </Button>
        </div>

        <div class="text-muted-foreground text-center text-sm">
            ¿Aún no tienes una cuenta?
            <TextLink :href="register()" class="underline underline-offset-4 text-emerald-600 dark:text-emerald-400 font-medium" :tabindex="5">Regístrate gratis</TextLink>
        </div>
    </Form>
</template>
