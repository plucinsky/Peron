<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    FileSearch,
    Minus,
    Plus,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface SourceItem {
    id: number;
    name: string;
    original_filename: string | null;
    extension: string | null;
    distance: number;
    preview_page_count: number | null;
    preview_status: string | null;
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

const isLanding = computed(() => {
    return (
        !props.query &&
        !props.answer &&
        !props.error &&
        props.sources.length === 0
    );
});

const showPreviewModal = ref(false);
const activeSource = ref<SourceItem | null>(null);
const previewPage = ref(1);
const zoom = ref(1);
const minZoom = 0.6;
const maxZoom = 2.5;

const previewUrl = computed(() => {
    if (!activeSource.value) {
        return '';
    }
    return `/archive-documents/${activeSource.value.id}/preview/${previewPage.value}`;
});

function openPreview(source: SourceItem) {
    activeSource.value = source;
    previewPage.value = 1;
    zoom.value = 1;
    showPreviewModal.value = true;
}

function closePreview(open: boolean) {
    if (open) {
        showPreviewModal.value = true;
        return;
    }
    showPreviewModal.value = false;
    activeSource.value = null;
    previewPage.value = 1;
    zoom.value = 1;
}

function zoomIn() {
    zoom.value = Math.min(maxZoom, +(zoom.value + 0.1).toFixed(2));
}

function zoomOut() {
    zoom.value = Math.max(minZoom, +(zoom.value - 0.1).toFixed(2));
}

function prevPage() {
    if (previewPage.value > 1) {
        previewPage.value -= 1;
    }
}

function nextPage() {
    const max = activeSource.value?.preview_page_count ?? null;
    if (!max || previewPage.value < max) {
        previewPage.value += 1;
    }
}

function renderMarkdown(raw: string): string {
    const escaped = raw
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    const lines = escaped.split('\n');
    let html = '';
    let index = 0;

    const inline = (text: string) => {
        return text
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/\*([^*]+)\*/g, '<em>$1</em>');
    };

    while (index < lines.length) {
        const line = lines[index];
        if (line.trim() === '') {
            index += 1;
            continue;
        }

        const headingMatch = line.match(/^(#{1,3})\s+(.*)$/);
        if (headingMatch) {
            const level = headingMatch[1].length;
            html += `<h${level}>${inline(headingMatch[2])}</h${level}>`;
            index += 1;
            continue;
        }

        if (line.startsWith('> ')) {
            const items: string[] = [];
            while (index < lines.length && lines[index].startsWith('> ')) {
                items.push(lines[index].slice(2));
                index += 1;
            }
            html += `<blockquote>${inline(items.join('<br>'))}</blockquote>`;
            continue;
        }

        const unorderedMatch = line.match(/^[-*]\s+(.*)$/);
        if (unorderedMatch) {
            const items: string[] = [];
            while (index < lines.length) {
                const match = lines[index].match(/^[-*]\s+(.*)$/);
                if (!match) {
                    break;
                }
                items.push(`<li>${inline(match[1])}</li>`);
                index += 1;
            }
            html += `<ul>${items.join('')}</ul>`;
            continue;
        }

        const orderedMatch = line.match(/^\d+\.\s+(.*)$/);
        if (orderedMatch) {
            const items: string[] = [];
            while (index < lines.length) {
                const match = lines[index].match(/^\d+\.\s+(.*)$/);
                if (!match) {
                    break;
                }
                items.push(`<li>${inline(match[1])}</li>`);
                index += 1;
            }
            html += `<ol>${items.join('')}</ol>`;
            continue;
        }

        const paragraph: string[] = [];
        while (index < lines.length && lines[index].trim() !== '') {
            paragraph.push(lines[index]);
            index += 1;
        }
        html += `<p>${inline(paragraph.join('<br>'))}</p>`;
    }

    return html;
}

const formattedAnswer = computed(() => {
    return props.answer ? renderMarkdown(props.answer) : '';
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

        <div class="flex min-h-[70vh] flex-col">
            <div v-if="isLanding" class="flex flex-1 items-center justify-center px-6 py-12">
                <div class="flex w-full max-w-3xl flex-col items-center gap-6 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                        <FileSearch class="h-7 w-7 text-foreground" />
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                            Mudrlant
                        </div>
                        <div class="text-3xl font-semibold text-foreground sm:text-4xl">
                            Čo ťa zaujíma?
                        </div>
                        <div class="text-base text-muted-foreground">
                            Opýtaj sa ma, čo ťa zaujíma a ja ti odpoviem s informácií z archívu.
                        </div>
                    </div>
                    <form class="flex w-full flex-col gap-3 sm:flex-row" @submit.prevent="submitSearch">
                        <Input
                            v-model="queryInput"
                            class="h-12 flex-1 text-base"
                            placeholder="Napíš svoju otázku..."
                        />
                        <Button type="submit" :disabled="isSearching" class="h-12 px-6">
                            {{ isSearching ? 'Hľadám...' : 'Vyhľadať' }}
                        </Button>
                    </form>
                </div>
            </div>

            <div v-else class="flex flex-col gap-6 p-6">
                <div class="rounded-xl border border-border/70 bg-muted p-6 shadow-sm">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                                <FileSearch class="h-5 w-5 text-foreground" />
                            </div>
                            <div>
                                <div class="text-xl font-semibold">Mudrlant</div>
                                <div class="text-sm text-muted-foreground">
                                    Vyhľadávanie v archívnych dokumentoch.
                                </div>
                            </div>
                        </div>

                        <form class="flex flex-col gap-3 sm:flex-row sm:items-center" @submit.prevent="submitSearch">
                            <Input
                                v-model="queryInput"
                                class="h-12 flex-1 bg-background text-base"
                                placeholder="Napíš, čo hľadáš..."
                            />
                            <Button type="submit" :disabled="isSearching" class="h-12 px-6">
                                {{ isSearching ? 'Hľadám...' : 'Vyhľadať' }}
                            </Button>
                        </form>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-xl border bg-background p-6 shadow-sm">
                        <div class="text-sm font-semibold uppercase text-muted-foreground">
                            Odpoveď
                        </div>
                        <div v-if="props.error" class="mt-4 text-sm text-destructive">
                            {{ props.error }}
                        </div>
                        <div
                            v-else-if="props.answer"
                            class="mt-4 text-base text-foreground [&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-3 [&_blockquote]:text-muted-foreground [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_h1]:mb-3 [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:mb-3 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mb-2 [&_h3]:text-base [&_h3]:font-semibold [&_ol]:mb-3 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-3 [&_ul]:mb-3 [&_ul]:list-disc [&_ul]:pl-5"
                            v-html="formattedAnswer"
                        />
                        <div v-else class="mt-4 text-base text-foreground">
                            Zatiaľ nie je dostupná žiadna odpoveď.
                        </div>
                    </div>

                    <div class="rounded-xl border bg-muted/20 p-6 shadow-sm">
                        <div class="text-sm font-semibold uppercase text-muted-foreground">
                            Pouzite zdroje informacii
                        </div>
                        <div v-if="sortedSources.length === 0" class="mt-4 text-sm text-muted-foreground">
                            Zatiaľ nemáme žiadne zdroje.
                        </div>
                        <div v-else class="mt-4 space-y-4">
                            <div
                                v-for="source in sortedSources"
                                :key="source.id"
                                class="cursor-pointer rounded-lg border border-border/60 bg-muted/10 p-3 transition hover:border-border"
                                @click="openPreview(source)"
                            >
                                <div class="flex gap-3">
                                    <div class="h-20 w-16 shrink-0 overflow-hidden rounded-md border border-border/60 bg-background">
                                        <img
                                            :src="`/archive-documents/${source.id}/preview/1`"
                                            :alt="`Náhľad ${source.name}`"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
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
                </div>
            </div>
        </div>
    </AppLayout>

    <Dialog :open="showPreviewModal" @update:open="closePreview">
        <DialogContent class="flex max-h-[85vh] flex-col overflow-hidden sm:max-w-6xl">
            <DialogHeader>
                <DialogTitle>
                    {{ activeSource?.name || 'Náhľad dokumentu' }}
                </DialogTitle>
            </DialogHeader>
            <div class="flex min-h-0 flex-1 flex-col gap-4">
                <div class="rounded-md border bg-background p-3">
                    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <div class="text-muted-foreground">
                            {{ activeSource?.original_filename || 'Bez názvu' }}
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                :disabled="zoom <= minZoom"
                                @click="zoomOut"
                                aria-label="Oddialiť"
                            >
                                <Minus class="h-4 w-4" />
                            </Button>
                            <div class="min-w-[56px] text-center text-xs font-medium text-muted-foreground">
                                {{ Math.round(zoom * 100) }}%
                            </div>
                            <Button
                                variant="outline"
                                size="icon"
                                :disabled="zoom >= maxZoom"
                                @click="zoomIn"
                                aria-label="Priblížiť"
                            >
                                <Plus class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="previewPage <= 1"
                                @click="prevPage"
                            >
                                <ChevronLeft class="mr-1 h-4 w-4" />
                                Predchádzajúca
                            </Button>
                            <div class="text-xs text-muted-foreground">
                                Strana {{ previewPage }}
                                <span v-if="activeSource?.preview_page_count">
                                    / {{ activeSource.preview_page_count }}
                                </span>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="activeSource?.preview_page_count ? previewPage >= activeSource.preview_page_count : false"
                                @click="nextPage"
                            >
                                Nasledujúca
                                <ChevronRight class="ml-1 h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1 flex-col items-start justify-start overflow-auto rounded-md border bg-muted p-4">
                    <div v-if="activeSource?.preview_status !== 'done'" class="text-sm text-muted-foreground">
                        Náhľad dokumentu ešte nie je pripravený.
                    </div>
                    <div v-else class="flex min-w-full justify-center">
                        <div
                            class="origin-top-left inline-block"
                            :style="{ transform: `scale(${zoom})` }"
                        >
                            <img
                                :src="previewUrl"
                                :alt="`Náhľad ${activeSource?.name || ''}`"
                                class="w-full max-w-[1000px] rounded-md border"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
