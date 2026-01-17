<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Technicke spravy',
        href: '/technicke-spravy',
    },
];
</script>

<template>
    <Head title="Technicke spravy" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <h1 class="text-2xl font-semibold">Vyrocne spravy</h1>
        </div>
    </AppLayout>
</template>
