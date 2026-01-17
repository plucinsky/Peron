<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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

interface UserRow {
    id: number;
    name: string;
    email: string;
    active: boolean;
    created_at: string;
}

const props = defineProps<{
    users: UserRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pouzivatelia',
        href: '/users',
    },
];

const showUserModal = ref(false);
const showPasswordModal = ref(false);
const editingUser = ref<UserRow | null>(null);
const passwordUser = ref<UserRow | null>(null);

const userForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

function closeUserModal() {
    showUserModal.value = false;
    editingUser.value = null;
    userForm.reset();
    userForm.clearErrors();
}

function handleUserModalChange(isOpen: boolean) {
    if (!isOpen) {
        closeUserModal();
        return;
    }

    showUserModal.value = true;
}

function openCreate() {
    editingUser.value = null;
    userForm.reset();
    userForm.clearErrors();
    showUserModal.value = true;
}

function openEdit(user: UserRow) {
    editingUser.value = user;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.clearErrors();
    showUserModal.value = true;
}

function submitUser() {
    if (editingUser.value) {
        userForm.put(`/users/${editingUser.value.id}`, {
            onSuccess: closeUserModal,
        });
        return;
    }

    userForm.post('/users', {
        onSuccess: closeUserModal,
    });
}

function closePasswordModal() {
    showPasswordModal.value = false;
    passwordUser.value = null;
    passwordForm.reset();
    passwordForm.clearErrors();
}

function handlePasswordModalChange(isOpen: boolean) {
    if (!isOpen) {
        closePasswordModal();
        return;
    }

    showPasswordModal.value = true;
}
function openPassword(user: UserRow) {
    passwordUser.value = user;
    passwordForm.reset();
    passwordForm.clearErrors();
    showPasswordModal.value = true;
}

function submitPassword() {
    if (!passwordUser.value) {
        return;
    }

    passwordForm.put(`/users/${passwordUser.value.id}/password`, {
        onSuccess: closePasswordModal,
    });
}

function toggleStatus(user: UserRow) {
    router.put(
        `/users/${user.id}/status`,
        { active: !user.active },
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pouzivatelia" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Pouzivatelia</h1>
                <Button @click="openCreate">Pridat pouzivatela</Button>
            </div>

            <div class="rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">
                                Meno
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Email
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Stav
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Akcie
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in props.users"
                            :key="user.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2">{{ user.name }}</td>
                            <td class="px-4 py-2">{{ user.email }}</td>
                            <td class="px-4 py-2">
                                {{ user.active ? 'Aktivny' : 'Neaktivny' }}
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEdit(user)"
                                    >
                                        Upravit
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openPassword(user)"
                                    >
                                        Zmenit heslo
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="toggleStatus(user)"
                                    >
                                        {{
                                            user.active
                                                ? 'Deaktivovat'
                                                : 'Aktivovat'
                                        }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="props.users.length === 0">
                            <td
                                class="px-4 py-6 text-center text-muted-foreground"
                                colspan="4"
                            >
                                Ziadni pouzivatelia.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>

    <Dialog :open="showUserModal" @update:open="handleUserModalChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        editingUser
                            ? 'Upravit pouzivatela'
                            : 'Pridat pouzivatela'
                    }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitUser">
                <div class="grid gap-2">
                    <Label for="user-name">Meno</Label>
                    <Input
                        id="user-name"
                        name="name"
                        v-model="userForm.name"
                        autocomplete="name"
                    />
                    <InputError :message="userForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="user-email">Email</Label>
                    <Input
                        id="user-email"
                        type="email"
                        name="email"
                        v-model="userForm.email"
                        autocomplete="email"
                    />
                    <InputError :message="userForm.errors.email" />
                </div>

                <div v-if="!editingUser" class="grid gap-2">
                    <Label for="user-password">Heslo</Label>
                    <Input
                        id="user-password"
                        type="password"
                        name="password"
                        v-model="userForm.password"
                        autocomplete="new-password"
                    />
                    <InputError :message="userForm.errors.password" />
                </div>

                <div v-if="!editingUser" class="grid gap-2">
                    <Label for="user-password-confirmation">
                        Potvrdit heslo
                    </Label>
                    <Input
                        id="user-password-confirmation"
                        type="password"
                        name="password_confirmation"
                        v-model="userForm.password_confirmation"
                        autocomplete="new-password"
                    />
                    <InputError
                        :message="userForm.errors.password_confirmation"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeUserModal"
                        >
                            Zrusit
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="userForm.processing">
                        Ulozit
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="showPasswordModal"
        @update:open="handlePasswordModalChange"
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Zmenit heslo</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitPassword">
                <div class="grid gap-2">
                    <Label for="new-password">Nove heslo</Label>
                    <Input
                        id="new-password"
                        type="password"
                        name="password"
                        v-model="passwordForm.password"
                        autocomplete="new-password"
                    />
                    <InputError :message="passwordForm.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="new-password-confirmation">
                        Potvrdit nove heslo
                    </Label>
                    <Input
                        id="new-password-confirmation"
                        type="password"
                        name="password_confirmation"
                        v-model="passwordForm.password_confirmation"
                        autocomplete="new-password"
                    />
                    <InputError
                        :message="passwordForm.errors.password_confirmation"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closePasswordModal"
                        >
                            Zrusit
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="passwordForm.processing">
                        Ulozit heslo
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
