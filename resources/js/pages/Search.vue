<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileSearch } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface SourceItem {
    id: number;
    name: string;
    original_filename: string | null;
    extension: string | null;
    distance: number;
    excerpts: string[];
}

const props = defineProps<{
    query: string;
    answer: string | null;
    sources: SourceItem[];
    error: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vyhľadávanie', href: '/search' },
];

const queryInput = ref(props.query ?? '');
const isSearching = ref(false);

const sortedSources = computed(() => {
    return [...props.sources].sort((a, b) => a.distance - b.distance);
});

function submitSearch() {
    if (!queryInput.value.trim()) {
        return;
    }

    isSearching.value = true;
    router.post(
        '/search',
        { query: queryInput.value },
        {
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
        }
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Vyhľadávanie" />

        <div class="flex flex-col gap-6 p-6">
            <div class="rounded-xl border bg-background p-6 shadow-sm">
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                            <FileSearch class="h-5 w-5 text-foreground" />
                        </div>
                        <div>
                            <div class="text-xl font-semibold">Vyhľadávanie</div>
                            <div class="text-sm text-muted-foreground">
                                Zadaj otázku a nájdeme odpoveď z archívu.
                            </div>
                        </div>
                    </div>

                    <form class="flex flex-col gap-3" @submit.prevent="submitSearch">
                        <Input
                            v-model="queryInput"
                            class="h-12 text-base"
                            placeholder="Napíš, čo hľadáš..."
                        />
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="isSearching">
                                {{ isSearching ? 'Hľadám...' : 'Vyhľadať' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <div class="rounded-xl border bg-background p-6 shadow-sm">
                    <div class="text-sm font-semibold uppercase text-muted-foreground">
                        Odpoveď
                    </div>
                    <div v-if="props.error" class="mt-4 text-sm text-destructive">
                        {{ props.error }}
                    </div>
                    <div
                        v-else
                        class="mt-4 whitespace-pre-wrap text-base text-foreground"
                    >
                        {{ props.answer || 'Zatiaľ nie je dostupná žiadna odpoveď.' }}
                    </div>
                </div>

                <div class="rounded-xl border bg-background p-6 shadow-sm">
                    <div class="text-sm font-semibold uppercase text-muted-foreground">
                        Použité dokumenty
                    </div>
                    <div v-if="sortedSources.length === 0" class="mt-4 text-sm text-muted-foreground">
                        Zatiaľ nemáme žiadne zdroje.
                    </div>
                    <div v-else class="mt-4 space-y-4">
                        <div
                            v-for="source in sortedSources"
                            :key="source.id"
                            class="rounded-lg border border-border/60 bg-muted/10 p-3"
                        >
                            <div class="text-sm font-semibold text-foreground">
                                {{ source.name }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ source.original_filename || 'Bez názvu' }}
                            </div>
                            <div v-if="source.excerpts.length" class="mt-2 space-y-1 text-xs text-muted-foreground">
                                <div v-for="(excerpt, idx) in source.excerpts.slice(0, 2)" :key="idx">
                                    “{{ excerpt }}”
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
