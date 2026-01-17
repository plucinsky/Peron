<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface PersonRow {
    id: number;
    first_name: string;
    last_name: string;
    city: string;
    email: string | null;
    phone: string | null;
    country: string;
}

const props = defineProps<{
    persons: PersonRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Osoby',
        href: '/persons',
    },
];

const showPersonModal = ref(false);
const editingPerson = ref<PersonRow | null>(null);

const personForm = useForm({
    first_name: '',
    last_name: '',
    city: '',
    email: '',
    phone: '',
    country: '',
});

function closePersonModal() {
    showPersonModal.value = false;
    editingPerson.value = null;
    personForm.reset();
    personForm.clearErrors();
}

function handlePersonModalChange(isOpen: boolean) {
    if (!isOpen) {
        closePersonModal();
        return;
    }

    showPersonModal.value = true;
}

function openCreate() {
    editingPerson.value = null;
    personForm.reset();
    personForm.clearErrors();
    showPersonModal.value = true;
}

function openEdit(person: PersonRow) {
    editingPerson.value = person;
    personForm.first_name = person.first_name;
    personForm.last_name = person.last_name;
    personForm.city = person.city;
    personForm.email = person.email ?? '';
    personForm.phone = person.phone ?? '';
    personForm.country = person.country;
    personForm.clearErrors();
    showPersonModal.value = true;
}

function submitPerson() {
    if (editingPerson.value) {
        personForm.put(`/persons/${editingPerson.value.id}`, {
            onSuccess: closePersonModal,
        });
        return;
    }

    personForm.post('/persons', {
        onSuccess: closePersonModal,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Osoby" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Osoby</h1>
                <Button @click="openCreate">Pridat osobu</Button>
            </div>

            <div class="rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">
                                Meno
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Priezvisko
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Mesto
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Email
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Telefon
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Krajina
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Akcie
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="person in props.persons"
                            :key="person.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2">{{ person.first_name }}</td>
                            <td class="px-4 py-2">{{ person.last_name }}</td>
                            <td class="px-4 py-2">{{ person.city }}</td>
                            <td class="px-4 py-2">{{ person.email }}</td>
                            <td class="px-4 py-2">{{ person.phone }}</td>
                            <td class="px-4 py-2">{{ person.country }}</td>
                            <td class="px-4 py-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openEdit(person)"
                                >
                                    Upravit
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="props.persons.length === 0">
                            <td
                                class="px-4 py-6 text-center text-muted-foreground"
                                colspan="7"
                            >
                                Ziadne osoby.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>

    <Dialog :open="showPersonModal" @update:open="handlePersonModalChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ editingPerson ? 'Upravit osobu' : 'Pridat osobu' }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitPerson">
                <div class="grid gap-2">
                    <Label for="person-first-name">Meno</Label>
                    <Input
                        id="person-first-name"
                        name="first_name"
                        v-model="personForm.first_name"
                        autocomplete="given-name"
                    />
                    <InputError :message="personForm.errors.first_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="person-last-name">Priezvisko</Label>
                    <Input
                        id="person-last-name"
                        name="last_name"
                        v-model="personForm.last_name"
                        autocomplete="family-name"
                    />
                    <InputError :message="personForm.errors.last_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="person-city">Mesto</Label>
                    <Input
                        id="person-city"
                        name="city"
                        v-model="personForm.city"
                        autocomplete="address-level2"
                    />
                    <InputError :message="personForm.errors.city" />
                </div>

                <div class="grid gap-2">
                    <Label for="person-email">Email</Label>
                    <Input
                        id="person-email"
                        name="email"
                        type="email"
                        v-model="personForm.email"
                        autocomplete="email"
                    />
                    <InputError :message="personForm.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="person-phone">Telefon</Label>
                    <Input
                        id="person-phone"
                        name="phone"
                        type="tel"
                        v-model="personForm.phone"
                        autocomplete="tel"
                    />
                    <InputError :message="personForm.errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="person-country">Krajina</Label>
                    <Input
                        id="person-country"
                        name="country"
                        v-model="personForm.country"
                        autocomplete="country-name"
                    />
                    <InputError :message="personForm.errors.country" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closePersonModal"
                        >
                            Zrusit
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="personForm.processing">
                        Ulozit
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
