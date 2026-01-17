<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface DiaryRow {
    id: number;
    report_number: string | null;
    locality_name: string;
    action_date: string | null;
    leader_person_id: number | null;
    work_time: string | null;
}

interface PersonOption {
    id: number;
    first_name: string;
    last_name: string;
}

const props = defineProps<{
    diaries: DiaryRow[];
    persons: PersonOption[];
    filters: {
        report_number: string;
        locality_name: string;
        leader_person_id: string;
        date_from: string;
        date_to: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Denniky',
        href: '/denniky',
    },
];

const filterForm = reactive({
    report_number: props.filters.report_number ?? '',
    locality_name: props.filters.locality_name ?? '',
    leader_person_id: props.filters.leader_person_id ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const personMap = computed(() => {
    const map = new Map<number, string>();
    props.persons.forEach((person) => {
        map.set(person.id, `${person.first_name} ${person.last_name}`.trim());
    });
    return map;
});

function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }
    const [year, month, day] = value.split('-');
    if (!year || !month || !day) {
        return value;
    }
    return `${day}.${month}.${year}`;
}

function formatWorkTime(value: string | null) {
    return value && value.trim() !== '' ? value : '-';
}

function applyFilters() {
    router.get('/denniky', { ...filterForm }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function resetFilters() {
    filterForm.report_number = '';
    filterForm.locality_name = '';
    filterForm.leader_person_id = '';
    filterForm.date_from = '';
    filterForm.date_to = '';

    applyFilters();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Denniky" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">Denniky</h1>
                <Button as-child>
                    <Link href="/denniky/create">Vytvorit dennik</Link>
                </Button>
            </div>

            <div class="rounded-md border p-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <div class="grid gap-2">
                        <Label for="filter-report-number">Cislo dennika</Label>
                        <Input
                            id="filter-report-number"
                            v-model="filterForm.report_number"
                            placeholder="Napr. 2025-01"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="filter-locality">Lokalita</Label>
                        <Input
                            id="filter-locality"
                            v-model="filterForm.locality_name"
                            placeholder="Nazov lokality"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="filter-leader">Veduci akcie</Label>
                        <select
                            id="filter-leader"
                            v-model="filterForm.leader_person_id"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        >
                            <option value="">Vsetci</option>
                            <option
                                v-for="person in props.persons"
                                :key="person.id"
                                :value="String(person.id)"
                            >
                                {{ person.first_name }} {{ person.last_name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="filter-date-from">Datum od</Label>
                        <Input
                            id="filter-date-from"
                            type="date"
                            v-model="filterForm.date_from"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="filter-date-to">Datum do</Label>
                        <Input
                            id="filter-date-to"
                            type="date"
                            v-model="filterForm.date_to"
                        />
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button @click="applyFilters">Filtrovat</Button>
                    <Button variant="outline" @click="resetFilters">
                        Zrusit filtre
                    </Button>
                </div>
            </div>

            <div class="rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">
                                Cislo
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Lokalita
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Datum
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Veduci
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Pracovna doba
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Akcie
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="diary in props.diaries"
                            :key="diary.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2">
                                {{ diary.report_number ?? '-' }}
                            </td>
                            <td class="px-4 py-2">
                                {{ diary.locality_name }}
                            </td>
                            <td class="px-4 py-2">
                                {{ formatDate(diary.action_date) }}
                            </td>
                            <td class="px-4 py-2">
                                {{
                                    diary.leader_person_id
                                        ? personMap.get(diary.leader_person_id) ?? '-'
                                        : '-'
                                }}
                            </td>
                            <td class="px-4 py-2">
                                {{
                                    formatWorkTime(diary.work_time)
                                }}
                            </td>
                            <td class="px-4 py-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="`/denniky/${diary.id}/edit`">
                                        Upravit
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="props.diaries.length === 0">
                            <td
                                class="px-4 py-6 text-center text-muted-foreground"
                                colspan="6"
                            >
                                Ziadne dennicky.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
