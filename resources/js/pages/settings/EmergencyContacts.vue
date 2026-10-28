<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyEmergencyContact,
    store as storeEmergencyContact,
    update as updateEmergencyContact,
} from '@/routes/emergency-contacts';

type EmergencyContact = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    relationship: string | null;
};

const props = defineProps<{
    contacts: EmergencyContact[];
}>();

const form = useForm({
    name: '',
    phone: '',
    email: '',
    relationship: '',
});

const editForm = useForm({
    name: '',
    phone: '',
    email: '',
    relationship: '',
});
const editingContactId = ref<number | null>(null);

const submit = () => {
    form.post(storeEmergencyContact.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const startEditing = (contact: EmergencyContact) => {
    editingContactId.value = contact.id;
    editForm.name = contact.name;
    editForm.phone = contact.phone;
    editForm.email = contact.email ?? '';
    editForm.relationship = contact.relationship ?? '';
    editForm.clearErrors();
};

const update = (id: number) => {
    editForm.put(updateEmergencyContact.url({ emergencyContact: id }), {
        preserveScroll: true,
        onSuccess: () => {
            editingContactId.value = null;
            editForm.reset();
        },
    });
};

const remove = (id: number) => {
    router.delete(destroyEmergencyContact.url({ emergencyContact: id }));
};
</script>

<template>
    <Head title="Contactos de emergencia" />

    <div class="space-y-6">
        <div
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
        >
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                Contactos de emergencia
            </h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                Añade personas de confianza para que estén disponibles en caso
                de emergencia.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <div
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <h2
                    class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                >
                    Lista
                </h2>

                <div v-if="props.contacts.length" class="mt-4 space-y-3">
                    <div
                        v-for="contact in props.contacts"
                        :key="contact.id"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/80"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="font-medium break-words text-slate-900 dark:text-slate-100"
                            >
                                {{ contact.name }}
                            </p>
                            <p
                                class="text-sm text-slate-600 dark:text-slate-300"
                            >
                                {{ contact.relationship || 'Sin relación' }}
                            </p>
                            <p
                                class="text-sm break-all text-slate-600 dark:text-slate-300"
                            >
                                {{ contact.phone }}
                            </p>
                            <p
                                class="text-sm break-all text-slate-600 dark:text-slate-300"
                            >
                                {{ contact.email || 'Sin correo electrónico' }}
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            <Button
                                v-if="editingContactId !== contact.id"
                                variant="outline"
                                size="sm"
                                @click="startEditing(contact)"
                            >
                                Editar
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                @click="remove(contact.id)"
                            >
                                Eliminar
                            </Button>
                        </div>

                        <form
                            v-if="editingContactId === contact.id"
                            class="grid w-full gap-3 sm:grid-cols-2"
                            @submit.prevent="update(contact.id)"
                        >
                            <div class="space-y-2">
                                <Label :for="`edit-name-${contact.id}`"
                                    >Nombre</Label
                                >
                                <Input
                                    :id="`edit-name-${contact.id}`"
                                    v-model="editForm.name"
                                    required
                                />
                                <p
                                    v-if="editForm.errors.name"
                                    role="alert"
                                    class="text-sm text-red-600"
                                >
                                    {{ editForm.errors.name }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label :for="`edit-phone-${contact.id}`"
                                    >Teléfono</Label
                                >
                                <Input
                                    :id="`edit-phone-${contact.id}`"
                                    v-model="editForm.phone"
                                    required
                                />
                                <p
                                    v-if="editForm.errors.phone"
                                    role="alert"
                                    class="text-sm text-red-600"
                                >
                                    {{ editForm.errors.phone }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label :for="`edit-email-${contact.id}`"
                                    >Correo electrónico (opcional)</Label
                                >
                                <Input
                                    :id="`edit-email-${contact.id}`"
                                    v-model="editForm.email"
                                    type="email"
                                    autocomplete="email"
                                />
                                <p
                                    v-if="editForm.errors.email"
                                    role="alert"
                                    class="text-sm text-red-600"
                                >
                                    {{ editForm.errors.email }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label :for="`edit-relationship-${contact.id}`"
                                    >Relación</Label
                                >
                                <Input
                                    :id="`edit-relationship-${contact.id}`"
                                    v-model="editForm.relationship"
                                />
                            </div>
                            <div class="flex gap-2 sm:col-span-2">
                                <Button
                                    type="submit"
                                    :disabled="editForm.processing"
                                >
                                    {{
                                        editForm.processing
                                            ? 'Guardando…'
                                            : 'Guardar cambios'
                                    }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="editingContactId = null"
                                >
                                    Cancelar
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-4 rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                >
                    Aún no has agregado contactos de emergencia.
                </div>
            </div>

            <form
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                @submit.prevent="submit"
            >
                <h2
                    class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                >
                    Nuevo contacto
                </h2>

                <div class="mt-4 space-y-4">
                    <div class="space-y-2">
                        <Label for="name">Nombre</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Ej. María López"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="phone">Teléfono</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            placeholder="Ej. +51987654321"
                            required
                        />
                        <p
                            v-if="form.errors.phone"
                            role="alert"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors.phone }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="email">Correo electrónico (opcional)</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="Ej. maria@example.com"
                        />
                        <p
                            v-if="form.errors.email"
                            role="alert"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="relationship">Relación</Label>
                        <Input
                            id="relationship"
                            v-model="form.relationship"
                            placeholder="Ej. Hermana"
                        />
                    </div>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing ? 'Guardando…' : 'Guardar contacto'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
