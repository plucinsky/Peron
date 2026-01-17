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

interface CaveRow {
    id: number;
    name: string;
    total_length: number;
    total_drop: number;
    description: string;
}

const props = defineProps<{
    caves: CaveRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Jaskyne',
        href: '/caves',
    },
];

const showCaveModal = ref(false);
const editingCave = ref<CaveRow | null>(null);

const caveForm = useForm({
    name: '',
    total_length: '',
    total_drop: '',
    description: '',
});

function closeCaveModal() {
    showCaveModal.value = false;
    editingCave.value = null;
    caveForm.reset();
    caveForm.clearErrors();
}

function handleCaveModalChange(isOpen: boolean) {
    if (!isOpen) {
        closeCaveModal();
        return;
    }

    showCaveModal.value = true;
}

function openCreate() {
    editingCave.value = null;
    caveForm.reset();
    caveForm.clearErrors();
    showCaveModal.value = true;
}

function openEdit(cave: CaveRow) {
    editingCave.value = cave;
    caveForm.name = cave.name;
    caveForm.total_length = String(cave.total_length ?? '');
    caveForm.total_drop = String(cave.total_drop ?? '');
    caveForm.description = cave.description;
    caveForm.clearErrors();
    showCaveModal.value = true;
}

function submitCave() {
    if (editingCave.value) {
        caveForm.put(`/caves/${editingCave.value.id}`, {
            onSuccess: closeCaveModal,
        });
        return;
    }

    caveForm.post('/caves', {
        onSuccess: closeCaveModal,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Jaskyne" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Jaskyne</h1>
                <Button @click="openCreate">Pridat jaskynu</Button>
            </div>

            <div class="rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">
                                Nazov
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Celkova dlzka
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Celkove prevysenie
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Popis
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Akcie
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="cave in props.caves"
                            :key="cave.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2">{{ cave.name }}</td>
                            <td class="px-4 py-2">
                                {{ cave.total_length }}
                            </td>
                            <td class="px-4 py-2">
                                {{ cave.total_drop }}
                            </td>
                            <td class="px-4 py-2">{{ cave.description }}</td>
                            <td class="px-4 py-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openEdit(cave)"
                                >
                                    Upravit
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="props.caves.length === 0">
                            <td
                                class="px-4 py-6 text-center text-muted-foreground"
                                colspan="5"
                            >
                                Ziadne jaskyne.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>

    <Dialog :open="showCaveModal" @update:open="handleCaveModalChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ editingCave ? 'Upravit jaskynu' : 'Pridat jaskynu' }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitCave">
                <div class="grid gap-2">
                    <Label for="cave-name">Nazov</Label>
                    <Input
                        id="cave-name"
                        name="name"
                        v-model="caveForm.name"
                        autocomplete="off"
                    />
                    <InputError :message="caveForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="cave-length">Celkova dlzka</Label>
                    <Input
                        id="cave-length"
                        name="total_length"
                        type="number"
                        min="0"
                        v-model="caveForm.total_length"
                    />
                    <InputError :message="caveForm.errors.total_length" />
                </div>

                <div class="grid gap-2">
                    <Label for="cave-drop">Celkove prevysenie</Label>
                    <Input
                        id="cave-drop"
                        name="total_drop"
                        type="number"
                        min="0"
                        v-model="caveForm.total_drop"
                    />
                    <InputError :message="caveForm.errors.total_drop" />
                </div>

                <div class="grid gap-2">
                    <Label for="cave-description">Popis</Label>
                    <textarea
                        id="cave-description"
                        name="description"
                        rows="4"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        v-model="caveForm.description"
                    ></textarea>
                    <InputError :message="caveForm.errors.description" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeCaveModal"
                        >
                            Zrusit
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="caveForm.processing">
                        Ulozit
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
