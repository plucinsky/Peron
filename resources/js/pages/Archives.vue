<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Upload } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch, watchEffect } from 'vue';

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

interface ArchiveRow {
    id: number;
    name: string;
    parent_id: number | null;
}

interface DocumentRow {
    id: number;
    archive_id: number;
    name: string;
    type: string;
    extension: string | null;
    size: number | null;
    storage_path: string | null;
    original_filename: string | null;
    ocr_status: string | null;
    preview_status: string | null;
    preview_page_count: number | null;
}

const props = defineProps<{
    archives: ArchiveRow[];
    documents: DocumentRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Archiv',
        href: '/archives',
    },
];

const selectedArchiveId = ref<number | null>(null);

watchEffect(() => {
    if (!selectedArchiveId.value && props.archives.length > 0) {
        selectedArchiveId.value = props.archives[0].id;
    }
});

function buildArchiveList(archives: ArchiveRow[]) {
    const map = new Map<number | null, ArchiveRow[]>();
    archives.forEach((archive) => {
        const key = archive.parent_id ?? null;
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key)!.push(archive);
    });

    const result: Array<{ archive: ArchiveRow; depth: number }> = [];

    function walk(parentId: number | null, depth: number) {
        const children = map.get(parentId) ?? [];
        children.forEach((child) => {
            result.push({ archive: child, depth });
            walk(child.id, depth + 1);
        });
    }

    walk(null, 0);

    return result;
}

const flatArchives = computed(() => buildArchiveList(props.archives));

const filteredDocuments = computed(() => {
    if (!selectedArchiveId.value) {
        return [];
    }
    return props.documents.filter(
        (doc) => doc.archive_id === selectedArchiveId.value
    );
});

function formatBytes(size: number | null) {
    if (!size && size !== 0) {
        return '-';
    }
    if (size < 1024) {
        return `${size} B`;
    }
    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = size / 1024;
    let unitIndex = 0;
    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }
    const rounded = value >= 10 ? Math.round(value) : Math.round(value * 10) / 10;
    const formatted = String(rounded).replace('.', ',');
    return `${formatted} ${units[unitIndex]}`;
}

const showArchiveModal = ref(false);
const editingArchive = ref<ArchiveRow | null>(null);

const showUploadModal = ref(false);
const isDragging = ref(false);
const isUploading = ref(false);

type UploadStatus = 'queued' | 'uploading' | 'done' | 'duplicate' | 'error';

interface UploadItem {
    id: string;
    name: string;
    size: number;
    progress: number;
    status: UploadStatus;
    message?: string;
    file: File;
}

const uploadQueue = ref<UploadItem[]>([]);
const uploadInputRef = ref<HTMLInputElement | null>(null);

const archiveForm = useForm({
    name: '',
    parent_id: '',
});

function closeArchiveModal() {
    showArchiveModal.value = false;
    editingArchive.value = null;
    archiveForm.reset();
    archiveForm.clearErrors();
}

function handleArchiveModalChange(isOpen: boolean) {
    if (!isOpen) {
        closeArchiveModal();
        return;
    }

    showArchiveModal.value = true;
}

function closeUploadModal() {
    showUploadModal.value = false;
    uploadQueue.value = [];
    uploadForm.reset();
    uploadForm.clearErrors();
}

function handleUploadModalChange(isOpen: boolean) {
    if (!isOpen) {
        closeUploadModal();
        return;
    }

    showUploadModal.value = true;
}

function openCreateArchive() {
    editingArchive.value = null;
    archiveForm.reset();
    archiveForm.parent_id = selectedArchiveId.value
        ? String(selectedArchiveId.value)
        : '';
    archiveForm.clearErrors();
    showArchiveModal.value = true;
}

function openEditArchive(archive: ArchiveRow) {
    editingArchive.value = archive;
    archiveForm.name = archive.name;
    archiveForm.parent_id = archive.parent_id ? String(archive.parent_id) : '';
    archiveForm.clearErrors();
    showArchiveModal.value = true;
}

function submitArchive() {
    const payload = {
        name: archiveForm.name,
        parent_id: archiveForm.parent_id
            ? Number(archiveForm.parent_id)
            : null,
    };

    if (editingArchive.value) {
        archiveForm.put(`/archives/${editingArchive.value.id}`, {
            data: payload,
            onSuccess: closeArchiveModal,
        });
        return;
    }

    archiveForm.post('/archives', {
        data: payload,
        onSuccess: closeArchiveModal,
    });
}

const uploadForm = useForm({
    archive_id: '',
    file: null as File | null,
});

function submitUpload(files: File[]) {
    if (!selectedArchiveId.value || files.length === 0 || isUploading.value) {
        return;
    }

    const existingFiles = new Set(
        props.documents
            .filter((doc) => doc.archive_id === selectedArchiveId.value)
            .map((doc) => `${doc.original_filename ?? doc.name}:${doc.size ?? 0}`)
    );
    const seen = new Set<string>();

    const newItems = files.map((file) => {
        const key = `${file.name}:${file.size}`;
        const duplicate =
            existingFiles.has(key) || seen.has(key);
        seen.add(key);

        return {
            id: `${file.name}-${file.size}-${file.lastModified}`,
            name: file.name,
            size: file.size,
            progress: 0,
            status: duplicate ? 'duplicate' : 'queued',
            message: duplicate ? 'Súbor už existuje v tomto adresári.' : undefined,
            file,
        };
    });

    uploadQueue.value = newItems;

    const pending = newItems.filter((item) => item.status === 'queued');
    if (pending.length === 0) {
        return;
    }

    uploadFilesSequentially(pending);
}

async function uploadFilesSequentially(items: UploadItem[]) {
    isUploading.value = true;

    for (const item of items) {
        await uploadSingleFile(item);
    }

    isUploading.value = false;
}

function uploadSingleFile(item: UploadItem): Promise<void> {
    uploadForm.clearErrors();
    uploadForm.archive_id = String(selectedArchiveId.value ?? '');
    uploadForm.file = item.file;
    item.status = 'uploading';
    item.progress = 0;

    return new Promise((resolve) => {
        uploadForm.post('/archive-documents', {
            forceFormData: true,
            onProgress: (event) => {
                item.progress = event.percentage;
            },
            onSuccess: () => {
                item.status = 'done';
                item.progress = 100;
            },
            onError: (errors) => {
                const message =
                    errors.file ??
                    errors.archive_id ??
                    'Nepodarilo sa nahrať súbor.';
                item.status =
                    message === 'Súbor už existuje v tomto adresári.'
                        ? 'duplicate'
                        : 'error';
                item.message = message;
            },
            onFinish: () => {
                uploadForm.reset('file');
                resolve();
            },
        });
    });
}

function handleUploadChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const files = target.files ? Array.from(target.files) : [];
    submitUpload(files);
    target.value = '';
}

function handleUploadDrop(event: DragEvent) {
    event.preventDefault();
    isDragging.value = false;
    const files = event.dataTransfer?.files
        ? Array.from(event.dataTransfer.files)
        : [];
    submitUpload(files);
}

function handleUploadDragOver(event: DragEvent) {
    event.preventDefault();
    isDragging.value = true;
}

function handleUploadDragLeave(event: DragEvent) {
    event.preventDefault();
    isDragging.value = false;
}

function triggerUploadPicker() {
    if (!selectedArchiveId.value) {
        return;
    }

    uploadInputRef.value?.click();
}

function startOcr(documentId: number) {
    router.post(`/archive-documents/${documentId}/ocr`, {}, { preserveScroll: true });
}

const showPreviewModal = ref(false);
const previewDocument = ref<DocumentRow | null>(null);
const previewPage = ref(1);
let previewPoller: ReturnType<typeof setInterval> | null = null;

const previewImageUrl = computed(() => {
    if (!previewDocument.value || previewDocument.value.preview_status !== 'done') {
        return '';
    }
    return `/archive-documents/${previewDocument.value.id}/preview/${previewPage.value}`;
});

function openPreview(document: DocumentRow) {
    previewDocument.value = document;
    previewPage.value = 1;
    showPreviewModal.value = true;

    if (document.preview_status !== 'done') {
        router.post(
            `/archive-documents/${document.id}/preview`,
            {},
            { preserveScroll: true }
        );
    }
}

function closePreviewModal() {
    showPreviewModal.value = false;
    previewDocument.value = null;
    previewPage.value = 1;
    stopPreviewPolling();
}

function goToPrevPage() {
    if (previewPage.value > 1) {
        previewPage.value -= 1;
    }
}

function goToNextPage() {
    const total = previewDocument.value?.preview_page_count ?? 0;
    if (previewPage.value < total) {
        previewPage.value += 1;
    }
}

function stopPreviewPolling() {
    if (previewPoller) {
        clearInterval(previewPoller);
        previewPoller = null;
    }
}

watch(
    () => showPreviewModal.value,
    (isOpen) => {
        if (!isOpen) {
            stopPreviewPolling();
            return;
        }

        if (!previewDocument.value || previewDocument.value.preview_status === 'done') {
            stopPreviewPolling();
            return;
        }

        stopPreviewPolling();
        previewPoller = setInterval(() => {
            router.reload({ only: ['documents'], preserveScroll: true });
        }, 3000);
    }
);

watchEffect(() => {
    if (!previewDocument.value) {
        return;
    }

    const updated = props.documents.find(
        (doc) => doc.id === previewDocument.value?.id
    );
    if (updated) {
        previewDocument.value = updated;
    }

    if (previewDocument.value.preview_status === 'done') {
        stopPreviewPolling();
    }
});

onUnmounted(() => {
    stopPreviewPolling();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Archiv" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Archiv</h1>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="openCreateArchive">
                        Pridať adresár
                    </Button>
                    <Button
                        :disabled="!selectedArchiveId"
                        @click="showUploadModal = true"
                    >
                        Pridať súbory
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
                <div class="rounded-md border">
                    <div class="border-b px-4 py-2 text-sm font-medium">
                        Adresáre
                    </div>
                    <div class="divide-y">
                        <button
                            v-for="item in flatArchives"
                            :key="item.archive.id"
                            type="button"
                            class="flex w-full items-center justify-between px-4 py-2 text-left text-sm hover:bg-accent"
                            :class="
                                selectedArchiveId === item.archive.id
                                    ? 'bg-accent'
                                    : ''
                            "
                            @click="selectedArchiveId = item.archive.id"
                        >
                            <span
                                class="truncate"
                                :style="{
                                    paddingLeft: `${item.depth * 16}px`,
                                }"
                            >
                                {{ item.archive.name }}
                            </span>
                            <span
                                class="ml-2 text-xs text-muted-foreground"
                                @click.stop="openEditArchive(item.archive)"
                            >
                                Upraviť
                            </span>
                        </button>
                        <div
                            v-if="flatArchives.length === 0"
                            class="px-4 py-6 text-center text-sm text-muted-foreground"
                        >
                            Žiadne adresáre.
                        </div>
                    </div>
                </div>

                <div class="rounded-md border">
                    <div class="flex items-center justify-between border-b px-4 py-2">
                        <div class="text-sm font-medium">Dokumenty</div>
                        <div class="text-xs text-muted-foreground">
                            {{
                                selectedArchiveId
                                    ? `Adresar #${selectedArchiveId}`
                                    : 'Vyber adresár'
                            }}
                        </div>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-muted">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">
                                    Názov
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Typ
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Veľkosť
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Náhľad
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    OCR
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Akcie
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="document in filteredDocuments"
                                :key="document.id"
                                class="border-t"
                            >
                                <td class="px-4 py-2">
                                    {{ document.name }}
                                </td>
                                <td class="px-4 py-2">{{ document.type }}</td>
                                <td class="px-4 py-2">
                                    {{ formatBytes(document.size) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-muted-foreground">
                                    <span class="inline-flex items-center gap-2">
                                        <span
                                            v-if="document.preview_status === 'processing'"
                                            class="inline-flex h-3 w-3 animate-spin rounded-full border-2 border-muted-foreground/40 border-t-muted-foreground"
                                        ></span>
                                        {{
                                            document.preview_status === 'done'
                                                ? 'Hotovo'
                                                : document.preview_status === 'processing'
                                                  ? 'Generuje sa'
                                                  : document.preview_status === 'queued'
                                                    ? 'V rade'
                                                    : document.preview_status === 'failed'
                                                      ? 'Chyba'
                                                      : 'Nezadané'
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-muted-foreground">
                                    <span class="inline-flex items-center gap-2">
                                        <span
                                            v-if="document.ocr_status === 'processing'"
                                            class="inline-flex h-3 w-3 animate-spin rounded-full border-2 border-muted-foreground/40 border-t-muted-foreground"
                                        ></span>
                                        {{
                                            document.ocr_status === 'done'
                                                ? 'Hotovo'
                                                : document.ocr_status === 'processing'
                                                  ? 'Spracúva sa'
                                                  : document.ocr_status === 'queued'
                                                    ? 'V rade'
                                                    : document.ocr_status === 'failed'
                                                      ? 'Chyba'
                                                      : 'Nezadané'
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <a
                                        :href="`/archive-documents/${document.id}/download`"
                                        class="text-sm font-medium underline underline-offset-4"
                                    >
                                        Stiahnuť
                                    </a>
                                    <button
                                        type="button"
                                        class="ml-3 text-sm font-medium underline underline-offset-4"
                                        @click="openPreview(document)"
                                    >
                                        Zobraziť
                                    </button>
                                    <button
                                        type="button"
                                        class="ml-3 text-sm font-medium underline underline-offset-4"
                                        @click="startOcr(document.id)"
                                    >
                                        Spustiť OCR
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredDocuments.length === 0">
                                <td
                                    class="px-4 py-6 text-center text-muted-foreground"
                                    colspan="6"
                                >
                                    Žiadne dokumenty.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>

    <Dialog :open="showArchiveModal" @update:open="handleArchiveModalChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ editingArchive ? 'Upraviť adresár' : 'Pridať adresár' }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitArchive">
                <div class="grid gap-2">
                    <Label for="archive-name">Názov</Label>
                    <Input
                        id="archive-name"
                        name="name"
                        v-model="archiveForm.name"
                        autocomplete="off"
                    />
                    <InputError :message="archiveForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="archive-parent">Nadradený adresár</Label>
                    <select
                        id="archive-parent"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        v-model="archiveForm.parent_id"
                    >
                        <option value="">-- koreň --</option>
                        <option
                            v-for="archive in props.archives"
                            :key="archive.id"
                            :value="archive.id"
                            :disabled="editingArchive?.id === archive.id"
                        >
                            {{ archive.name }}
                        </option>
                    </select>
                    <InputError :message="archiveForm.errors.parent_id" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeArchiveModal"
                        >
                            Zrušiť
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="archiveForm.processing">
                        Uložiť
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="showUploadModal" @update:open="handleUploadModalChange">
        <DialogContent class="sm:max-w-5xl max-h-[85vh] overflow-hidden">
            <DialogHeader>
                <DialogTitle>Pridať súbory</DialogTitle>
            </DialogHeader>

            <form class="flex max-h-[70vh] flex-col gap-4" @submit.prevent>
                <div class="grid gap-2">
                    <Label for="archive-upload">Súbory</Label>
                    <div
                        class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-md border border-dashed bg-muted/40 p-8 text-sm text-muted-foreground"
                        :class="isDragging ? 'bg-accent' : ''"
                        role="button"
                        tabindex="0"
                        @click="triggerUploadPicker"
                        @keydown.enter.prevent="triggerUploadPicker"
                        @keydown.space.prevent="triggerUploadPicker"
                        @drop="handleUploadDrop"
                        @dragover="handleUploadDragOver"
                        @dragleave="handleUploadDragLeave"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-muted-foreground/15 text-foreground"
                        >
                            <Upload class="h-7 w-7" />
                        </div>
                        <div class="text-sm font-medium text-foreground">
                            Pretiahni súbory sem alebo klikni sem.
                        </div>
                        <input
                            id="archive-upload"
                            ref="uploadInputRef"
                            type="file"
                            class="hidden"
                            multiple
                            :disabled="!selectedArchiveId"
                            @change="handleUploadChange"
                        />
                    </div>
                    <div
                        v-if="!selectedArchiveId"
                        class="text-xs text-destructive"
                    >
                        Najprv vyber adresár.
                    </div>
                    <InputError :message="uploadForm.errors.file" />
                </div>

                <div
                    v-if="uploadQueue.length > 0"
                    class="min-h-0 flex-1 overflow-auto rounded-md border"
                >
                    <table class="w-full table-fixed text-xs">
                        <thead class="bg-muted">
                            <tr>
                                <th class="w-1/2 px-3 py-2 text-left font-medium">
                                    Súbor
                                </th>
                                <th class="w-1/5 px-3 py-2 text-left font-medium">
                                    Stav
                                </th>
                                <th class="w-1/3 px-3 py-2 text-left font-medium">
                                    Priebeh
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in uploadQueue"
                                :key="item.id"
                                class="border-t"
                            >
                                <td class="px-3 py-2">
                                    <div class="truncate">{{ item.name }}</div>
                                    <div
                                        v-if="item.message"
                                        class="text-destructive"
                                    >
                                        {{ item.message }}
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">
                                    {{
                                        item.status === 'done'
                                            ? 'Hotovo'
                                            : item.status === 'uploading'
                                              ? 'Nahráva sa'
                                              : item.status === 'duplicate'
                                                ? 'Duplicita'
                                                : item.status === 'error'
                                                  ? 'Chyba'
                                                  : 'Čaká'
                                    }}
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-1.5 w-24 rounded-full bg-muted"
                                        >
                                            <div
                                                class="h-1.5 rounded-full transition-all"
                                                :class="
                                                    item.status === 'done'
                                                        ? 'bg-primary'
                                                        : item.status ===
                                                            'error'
                                                          ? 'bg-destructive'
                                                          : item.status ===
                                                              'duplicate'
                                                            ? 'bg-muted-foreground'
                                                            : 'bg-primary/60'
                                                "
                                                :style="{
                                                    width:
                                                        item.status ===
                                                        'duplicate'
                                                            ? '100%'
                                                            : `${item.progress}%`,
                                                }"
                                            ></div>
                                        </div>
                                        <span class="text-muted-foreground">
                                            {{
                                                item.status === 'duplicate'
                                                    ? '100%'
                                                    : `${item.progress}%`
                                            }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeUploadModal"
                        >
                            Zrušiť
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="showPreviewModal" @update:open="closePreviewModal">
        <DialogContent class="sm:max-w-5xl max-h-[85vh] overflow-hidden">
            <DialogHeader>
                <DialogTitle>Náhľad dokumentu</DialogTitle>
            </DialogHeader>

            <div class="flex max-h-[70vh] flex-col gap-4">
                <div
                    v-if="!previewDocument"
                    class="text-sm text-muted-foreground"
                >
                    Vyber dokument.
                </div>
                <div v-else-if="previewDocument.preview_status !== 'done'">
                    <div class="flex items-center gap-3 text-sm text-muted-foreground">
                        <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-muted-foreground/40 border-t-muted-foreground"></span>
                        <span>Náhľad sa generuje. Skús to o chvíľu znova.</span>
                    </div>
                </div>
                <div v-else class="min-h-0 flex-1 overflow-auto">
                    <img
                        :src="previewImageUrl"
                        alt="Náhľad dokumentu"
                        class="mx-auto max-h-[60vh] w-auto rounded-md border"
                    />
                </div>

                <div
                    v-if="
                        previewDocument &&
                        previewDocument.preview_status === 'done'
                    "
                    class="flex items-center justify-between"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="previewPage <= 1"
                        @click="goToPrevPage"
                    >
                        Predchádzajúca
                    </Button>
                    <div class="text-sm text-muted-foreground">
                        Strana {{ previewPage }} /
                        {{ previewDocument.preview_page_count ?? 1 }}
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="
                            previewPage >=
                            (previewDocument.preview_page_count ?? 1)
                        "
                        @click="goToNextPage"
                    >
                        Nasledujúca
                    </Button>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closePreviewModal"
                        >
                            Zavrieť
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>

</template>
