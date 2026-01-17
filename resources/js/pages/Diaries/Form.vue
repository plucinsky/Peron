<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronUp,
    Download,
    Loader2,
    Save,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import PersonMultiSelect from '@/components/PersonMultiSelect.vue';
import PersonSelect from '@/components/PersonSelect.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
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

interface DiaryFormData {
    id?: number;
    report_number: string;
    locality_name: string;
    locality_position: string;
    karst_area: string;
    orographic_unit: string;
    action_date: string;
    work_time: string;
    weather: string;
    leader_person_id: string;
    member_person_ids: string[];
    other_person_ids: string[];
    sss_participants_note: string;
    other_participants: string;
    work_description: string;
    excavated_length_m: string;
    discovered_length_m: string;
    surveyed_length_m: string;
    surveyed_depth_m: string;
    leader_signed_person_id: string;
    leader_signed_at: string;
    club_signed_person_id: string;
    club_signed_at: string;
}

interface PersonOption {
    id: number;
    first_name: string;
    last_name: string;
}

interface AttachmentRow {
    id: number;
    name: string;
    original_filename: string | null;
    caption: string | null;
    seq: number | null;
    download_url: string;
    created_at: string | null;
}

interface AttachmentView {
    id: string | number;
    captionDraft: string;
    seqDraft: string;
    isPending: boolean;
    saving?: boolean;
}

const props = defineProps<{
    diary: DiaryFormData | null;
    persons: PersonOption[];
    attachments: AttachmentRow[];
}>();

const isEdit = computed(() => Boolean(props.diary?.id));

const tabs = computed(() => {
    const base = [
        'Zakladne informacie',
        'Ucastnici akcie',
        'Pracovna cinnost',
        'Zaverecna cast',
    ] as const;
    if (props.diary?.id) {
        return [...base, 'Prilohy'] as const;
    }
    return base;
});

const activeTab = ref<(typeof tabs.value)[number]>(tabs.value[0]);

const membersPlaceholder = 'Zacnite pisat meno';

const pendingAttachments = ref<
    Array<{
        id: string;
        file: File;
        name: string;
        original_filename: string;
        download_url: string;
        captionDraft: string;
        seqDraft: string;
        saving: false;
        isPending: true;
    }>
>([]);
const dropActive = ref(false);
const uploadInputRef = ref<HTMLInputElement | null>(null);
const deleteTarget = ref<{ id: string | number; isPending: boolean } | null>(
    null
);
const showDeleteModal = ref(false);
const existingAttachments = ref(
    props.attachments.map((item) => ({
        ...item,
        captionDraft: item.caption ?? '',
        seqDraft: item.seq !== null && item.seq !== undefined ? String(item.seq) : '',
        saving: false,
        isPending: false,
    }))
);

watch(
    () => props.attachments,
    (next) => {
        existingAttachments.value = next.map((item) => ({
            ...item,
            captionDraft: item.caption ?? '',
            seqDraft: item.seq !== null && item.seq !== undefined ? String(item.seq) : '',
            saving: false,
            isPending: false,
        }));
    }
);

const hasPendingAttachments = computed(
    () => pendingAttachments.value.length > 0
);

const combinedAttachments = computed(() => {
    const items = [
        ...pendingAttachments.value,
        ...existingAttachments.value,
    ];
    return [...items].sort((a, b) => {
        const aSeq = a.seqDraft === '' ? Number.POSITIVE_INFINITY : Number(a.seqDraft);
        const bSeq = b.seqDraft === '' ? Number.POSITIVE_INFINITY : Number(b.seqDraft);
        if (aSeq === bSeq) {
            return 0;
        }
        return aSeq - bSeq;
    });
});

function removePendingAttachment(id: string) {
    const target = pendingAttachments.value.find((item) => item.id === id);
    if (target) {
        URL.revokeObjectURL(target.download_url);
    }
    pendingAttachments.value = pendingAttachments.value.filter(
        (item) => item.id !== id
    );
}

function ensureSeqDefaults() {
    const items = [
        ...pendingAttachments.value,
        ...existingAttachments.value,
    ];
    items.forEach((item, index) => {
        if (item.seqDraft === '' || item.seqDraft === null || item.seqDraft === undefined) {
            item.seqDraft = String(index + 1);
        }
    });
}

watch(
    () => [pendingAttachments.value.length, existingAttachments.value.length],
    () => {
        ensureSeqDefaults();
    },
    { immediate: true }
);

function moveAttachment(itemId: string, direction: 'up' | 'down') {
    const ordered = combinedAttachments.value;
    const index = ordered.findIndex((item) => item.id === itemId);
    if (index === -1) {
        return;
    }
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= ordered.length) {
        return;
    }
    const current = ordered[index];
    const neighbor = ordered[targetIndex];
    const currentSeq = current.seqDraft;
    current.seqDraft = neighbor.seqDraft;
    neighbor.seqDraft = currentSeq;

    if (!current.isPending) {
        saveAttachmentCaption(current);
    }
    if (!neighbor.isPending) {
        saveAttachmentCaption(neighbor);
    }
}

function addFiles(files: FileList | File[]) {
    Array.from(files).forEach((file) => {
        const previewUrl = URL.createObjectURL(file);
        pendingAttachments.value.push({
            id: `row-${Date.now()}-${Math.random().toString(16).slice(2)}`,
            file,
            name: file.name,
            original_filename: file.name,
            download_url: previewUrl,
            captionDraft: '',
            seqDraft: '',
            saving: false,
            isPending: true,
        });
    });
}

function handleFileInput(event: Event) {
    const target = event.target as HTMLInputElement;
    const files = target.files;
    if (files && files.length > 0) {
        addFiles(files);
        target.value = '';
    }
}

function handleDrop(event: DragEvent) {
    event.preventDefault();
    dropActive.value = false;
    if (event.dataTransfer?.files?.length) {
        addFiles(event.dataTransfer.files);
    }
}

function saveAttachmentCaption(item: AttachmentView) {
    if (!props.diary?.id || item.isPending) {
        return;
    }
    item.saving = true;
    router.put(
        `/denniky/${props.diary.id}/attachments/${item.id}`,
        {
            caption: item.captionDraft || null,
            seq: item.seqDraft !== '' ? Number(item.seqDraft) : null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                item.saving = false;
            },
        }
    );
}

function requestDeleteAttachment(id: string | number, isPending: boolean) {
    deleteTarget.value = { id, isPending };
    showDeleteModal.value = true;
}

function closeDeleteModal() {
    showDeleteModal.value = false;
    deleteTarget.value = null;
}

function confirmDeleteAttachment() {
    if (!deleteTarget.value || !props.diary?.id) {
        return;
    }

    if (deleteTarget.value.isPending) {
        removePendingAttachment(String(deleteTarget.value.id));
        closeDeleteModal();
        return;
    }

    router.delete(
        `/denniky/${props.diary.id}/attachments/${deleteTarget.value.id}`,
        {
            preserveScroll: true,
            onFinish: closeDeleteModal,
        }
    );
}

function parseDownloadFilename(disposition: string | null, fallback: string) {
    if (!disposition) {
        return fallback;
    }
    const match = disposition.match(/filename\*?=(?:UTF-8''|")?([^;"]+)/i);
    if (!match) {
        return fallback;
    }
    return decodeURIComponent(match[1]).replace(/["']/g, '') || fallback;
}

async function downloadAttachment(url: string) {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) {
        throw new Error('Download failed');
    }
    const blob = await response.blob();
    const filename = parseDownloadFilename(
        response.headers.get('Content-Disposition'),
        'dennik.pdf'
    );
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    link.rel = 'noopener';
    link.click();
    URL.revokeObjectURL(objectUrl);
}

function submitAttachments() {
    if (!props.diary?.id) {
        return;
    }
    const payload = new FormData();
    pendingAttachments.value.forEach((row) => {
        payload.append('files[]', row.file);
        payload.append('captions[]', row.captionDraft);
        payload.append('seqs[]', row.seqDraft !== '' ? row.seqDraft : '');
    });
    if (!payload.has('files[]')) {
        return;
    }
    payload.append('relation_type', 'attachment');

    router.post(`/denniky/${props.diary.id}/attachments`, payload, {
        forceFormData: true,
        onSuccess: () => {
            pendingAttachments.value.forEach((row) => {
                URL.revokeObjectURL(row.download_url);
            });
            pendingAttachments.value = [];
        },
    });
}


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Denniky',
        href: '/denniky',
    },
    {
        title: isEdit.value ? 'Upravit dennik' : 'Vytvorit dennik',
        href: isEdit.value
            ? `/denniky/${props.diary?.id}/edit`
            : '/denniky/create',
    },
];

const form = useForm({
    report_number: props.diary?.report_number ?? '',
    locality_name: props.diary?.locality_name ?? '',
    locality_position: props.diary?.locality_position ?? '',
    karst_area: props.diary?.karst_area ?? '',
    orographic_unit: props.diary?.orographic_unit ?? '',
    action_date: props.diary?.action_date ?? '',
    work_time: props.diary?.work_time ?? '',
    weather: props.diary?.weather ?? '',
    leader_person_id: props.diary?.leader_person_id
        ? String(props.diary?.leader_person_id)
        : '',
    member_person_ids: (props.diary?.member_person_ids ?? []).map((id) =>
        String(id)
    ),
    other_person_ids: (props.diary?.other_person_ids ?? []).map((id) =>
        String(id)
    ),
    sss_participants_note: props.diary?.sss_participants_note ?? '',
    other_participants: props.diary?.other_participants ?? '',
    work_description: props.diary?.work_description ?? '',
    excavated_length_m: props.diary?.excavated_length_m
        ? String(props.diary?.excavated_length_m)
        : '',
    discovered_length_m: props.diary?.discovered_length_m
        ? String(props.diary?.discovered_length_m)
        : '',
    surveyed_length_m: props.diary?.surveyed_length_m
        ? String(props.diary?.surveyed_length_m)
        : '',
    surveyed_depth_m: props.diary?.surveyed_depth_m
        ? String(props.diary?.surveyed_depth_m)
        : '',
    leader_signed_person_id: props.diary?.leader_signed_person_id
        ? String(props.diary?.leader_signed_person_id)
        : '',
    leader_signed_at: props.diary?.leader_signed_at ?? '',
    club_signed_person_id: props.diary?.club_signed_person_id
        ? String(props.diary?.club_signed_person_id)
        : '',
    club_signed_at: props.diary?.club_signed_at ?? '',
});


function toNumber(value: string) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function submit() {
    const payload = {
        report_number: form.report_number || null,
        locality_name: form.locality_name,
        locality_position: form.locality_position,
        karst_area: form.karst_area,
        orographic_unit: form.orographic_unit,
        action_date: form.action_date || null,
        work_time: form.work_time || null,
        weather: form.weather || null,
        leader_person_id: form.leader_person_id
            ? Number(form.leader_person_id)
            : null,
        member_person_ids: form.member_person_ids.map((id) => Number(id)),
        other_person_ids: form.other_person_ids.map((id) => Number(id)),
        sss_participants_note: form.sss_participants_note || null,
        other_participants: form.other_participants || null,
        work_description: form.work_description || null,
        excavated_length_m: form.excavated_length_m
            ? toNumber(form.excavated_length_m)
            : null,
        discovered_length_m: form.discovered_length_m
            ? toNumber(form.discovered_length_m)
            : null,
        surveyed_length_m: form.surveyed_length_m
            ? toNumber(form.surveyed_length_m)
            : null,
        surveyed_depth_m: form.surveyed_depth_m
            ? toNumber(form.surveyed_depth_m)
            : null,
        leader_signed_person_id: form.leader_signed_person_id
            ? Number(form.leader_signed_person_id)
            : null,
        leader_signed_at: form.leader_signed_at || null,
        club_signed_person_id: form.club_signed_person_id
            ? Number(form.club_signed_person_id)
            : null,
        club_signed_at: form.club_signed_at || null,
    };

    if (isEdit.value && props.diary?.id) {
        form.put(`/denniky/${props.diary.id}`, {
            data: payload,
            onSuccess: () => {
                if (hasPendingAttachments.value) {
                    submitAttachments();
                }
            },
        });
        return;
    }

    form.post('/denniky', {
        data: payload,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="isEdit ? 'Upravit dennik' : 'Vytvorit dennik'" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ isEdit ? 'Upravit dennik' : 'Vytvorit dennik' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Udaje zodpovedaju rozlozeniu technickeho dennika.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="props.diary?.id"
                        variant="outline"
                        type="button"
                        @click="downloadAttachment(`/denniky/${props.diary.id}/pdf`)"
                    >
                        Stiahnut PDF
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/denniky">Spat na zoznam</Link>
                    </Button>
                </div>
            </div>

            <form class="mx-auto w-full max-w-6xl space-y-6" @submit.prevent="submit">
                <div class="sticky top-0 z-10 rounded-md border bg-background/90 p-2 shadow-sm backdrop-blur">
                    <button
                        v-for="tab in tabs"
                        :key="tab"
                        type="button"
                        class="relative rounded-md px-3 py-1.5 text-sm font-semibold transition"
                        :class="
                            activeTab === tab
                                ? 'bg-foreground text-background shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab = tab"
                    >
                        {{ tab }}
                    </button>
                </div>

                <div
                    v-show="activeTab === 'Zakladne informacie'"
                    class="rounded-md border border-sidebar-border bg-sidebar p-4 [&_input]:bg-background [&_select]:bg-background [&_textarea]:bg-background"
                >
                    <h2 class="text-lg font-semibold">Zakladne informacie</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="report-number">Cislo dennika</Label>
                            <Input
                                id="report-number"
                                v-model="form.report_number"
                                placeholder="Napr. 2025-01"
                            />
                            <InputError :message="form.errors.report_number" />
                        </div>
                        <div class="lg:col-span-2 grid gap-2">
                            <Label for="locality-name">Lokalita</Label>
                            <Input
                                id="locality-name"
                                v-model="form.locality_name"
                            />
                            <InputError :message="form.errors.locality_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="locality-position">Poloha lokality</Label>
                            <Input
                                id="locality-position"
                                v-model="form.locality_position"
                            />
                            <InputError
                                :message="form.errors.locality_position"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="karst-area">Krasove uzemie</Label>
                            <Input
                                id="karst-area"
                                v-model="form.karst_area"
                            />
                            <InputError :message="form.errors.karst_area" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="orographic-unit">Orograficky celok</Label>
                            <Input
                                id="orographic-unit"
                                v-model="form.orographic_unit"
                            />
                            <InputError
                                :message="form.errors.orographic_unit"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="leader-person">Veduci akcie</Label>
                            <PersonSelect
                                v-model="form.leader_person_id"
                                :options="props.persons"
                                placeholder="Vyberte veduceho"
                            />
                            <InputError
                                :message="form.errors.leader_person_id"
                            />
                        </div>
                    </div>
                </div>

                <div
                    v-show="activeTab === 'Ucastnici akcie'"
                    class="rounded-md border border-sidebar-border bg-sidebar p-4 [&_input]:bg-background [&_select]:bg-background [&_textarea]:bg-background"
                >
                    <h2 class="text-lg font-semibold">Ucastnici akcie</h2>
                    <div class="mt-4 grid gap-6 lg:grid-cols-2 lg:items-start">
                        <div class="grid gap-4">
                            <div class="grid gap-3">
                                <Label for="member-search">Clenovia SSS</Label>
                                <PersonMultiSelect
                                    v-model="form.member_person_ids"
                                    :options="props.persons"
                                    :placeholder="membersPlaceholder"
                                />
                                <InputError :message="form.errors.member_person_ids" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="sss-participants-note">
                                    Doplnujuca poznamka k clenov SSS
                                </Label>
                                <textarea
                                    id="sss-participants-note"
                                    rows="3"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                    v-model="form.sss_participants_note"
                                ></textarea>
                                <InputError
                                    :message="form.errors.sss_participants_note"
                                />
                            </div>
                        </div>
                        <div class="grid gap-4 lg:self-start">
                            <div class="grid gap-3">
                                <Label for="other-persons">Ini ucastnici</Label>
                                <PersonMultiSelect
                                    v-model="form.other_person_ids"
                                    :options="props.persons"
                                    placeholder="Zacnite pisat meno"
                                />
                                <InputError :message="form.errors.other_person_ids" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="other-participants">
                                    Doplnujuca poznamka k inym ucastnikom
                                </Label>
                                <textarea
                                    id="other-participants"
                                    rows="3"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                    v-model="form.other_participants"
                                ></textarea>
                                <InputError
                                    :message="form.errors.other_participants"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-show="activeTab === 'Pracovna cinnost'"
                    class="rounded-md border border-sidebar-border bg-sidebar p-4 [&_input]:bg-background [&_select]:bg-background [&_textarea]:bg-background"
                >
                    <h2 class="text-lg font-semibold">Pracovna cinnost</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-[1fr_2fr] md:items-start">
                        <div class="grid gap-4">
                            <div class="grid gap-2">
                                <Label for="action-date">Datum</Label>
                                <Input
                                    id="action-date"
                                    type="date"
                                    v-model="form.action_date"
                                />
                                <InputError :message="form.errors.action_date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="work-time">Pracovny cas</Label>
                                <Input
                                    id="work-time"
                                    v-model="form.work_time"
                                    placeholder="Napr. 09:00 - 17:30"
                                />
                                <InputError :message="form.errors.work_time" />
                            </div>
                        </div>
                        <div class="grid gap-2 md:self-start">
                            <Label for="weather">Pocasie pocas akcie</Label>
                            <textarea
                                id="weather"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                v-model="form.weather"
                            ></textarea>
                            <InputError :message="form.errors.weather" />
                        </div>
                        <div class="md:col-span-2 grid gap-2">
                            <Label for="work-description">Popis</Label>
                            <RichTextEditor v-model="form.work_description" />
                            <InputError :message="form.errors.work_description" />
                        </div>
                    </div>
                </div>

                <div
                    v-show="activeTab === 'Zaverecna cast'"
                    class="rounded-md border border-sidebar-border bg-sidebar p-4 [&_input]:bg-background [&_select]:bg-background [&_textarea]:bg-background"
                >
                    <h2 class="text-lg font-semibold">Metriky</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="excavated-length">Vyhlbene (dlzka) [m]</Label>
                            <Input
                                id="excavated-length"
                                type="number"
                                step="0.1"
                                min="0"
                                v-model="form.excavated_length_m"
                            />
                            <InputError
                                :message="form.errors.excavated_length_m"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="discovered-length">Objavene (dlzka) [m]</Label>
                            <Input
                                id="discovered-length"
                                type="number"
                                step="0.1"
                                min="0"
                                v-model="form.discovered_length_m"
                            />
                            <InputError
                                :message="form.errors.discovered_length_m"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="surveyed-length">Zamerane (dlzka) [m]</Label>
                            <Input
                                id="surveyed-length"
                                type="number"
                                step="0.1"
                                min="0"
                                v-model="form.surveyed_length_m"
                            />
                            <InputError
                                :message="form.errors.surveyed_length_m"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="surveyed-depth">Zamerane (hlbka) [m]</Label>
                            <Input
                                id="surveyed-depth"
                                type="number"
                                step="0.1"
                                min="0"
                                v-model="form.surveyed_depth_m"
                            />
                            <InputError
                                :message="form.errors.surveyed_depth_m"
                            />
                        </div>
                    </div>

                    <div class="mt-8">
                        <h2 class="text-lg font-semibold">Podpisy</h2>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="leader-signed-person">
                                    Datum a podpis veduceho akcie
                                </Label>
                                <PersonSelect
                                    v-model="form.leader_signed_person_id"
                                    :options="props.persons"
                                    placeholder="Vyberte osobu"
                                />
                                <InputError
                                    :message="form.errors.leader_signed_person_id"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="leader-signed-at">Datum podpisu</Label>
                                <Input
                                    id="leader-signed-at"
                                    type="date"
                                    v-model="form.leader_signed_at"
                                />
                                <InputError
                                    :message="form.errors.leader_signed_at"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="club-signed-person">
                                    Datum a podpis veduceho klubu
                                </Label>
                                <PersonSelect
                                    v-model="form.club_signed_person_id"
                                    :options="props.persons"
                                    placeholder="Vyberte osobu"
                                />
                                <InputError
                                    :message="form.errors.club_signed_person_id"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="club-signed-at">Datum podpisu</Label>
                                <Input
                                    id="club-signed-at"
                                    type="date"
                                    v-model="form.club_signed_at"
                                />
                                <InputError
                                    :message="form.errors.club_signed_at"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-show="activeTab === 'Prilohy'"
                    class="rounded-md border border-sidebar-border bg-sidebar p-4 [&_input]:bg-background [&_select]:bg-background [&_textarea]:bg-background"
                >
                    <h2 class="text-lg font-semibold">Prilohy</h2>

                    <div v-if="!props.diary?.id" class="mt-4 text-sm text-muted-foreground">
                        Prilohy je mozne pridat az po ulozeni dennika.
                    </div>

                    <div v-else class="mt-4 space-y-6">
                        <input
                            ref="uploadInputRef"
                            type="file"
                            class="hidden"
                            accept="image/*"
                            multiple
                            @change="handleFileInput"
                        />
                        <div
                            class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-md border border-dashed bg-background px-3 py-6 text-center"
                            @click="uploadInputRef?.click()"
                            @dragenter.prevent="dropActive = true"
                            @dragover.prevent="dropActive = true"
                            @dragleave.prevent="dropActive = false"
                            @drop="handleDrop"
                            :class="dropActive ? 'border-primary bg-primary/5' : 'border-border'"
                        >
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-muted-foreground/15 text-foreground"
                            >
                                <Upload class="h-6 w-6" />
                            </div>
                            <div class="text-sm font-medium text-foreground">
                                Pretiahnite obrazky sem alebo kliknite.
                            </div>
                        </div>

                        <div v-if="combinedAttachments.length > 0" class="space-y-2">
                            <h3 class="text-sm font-semibold text-muted-foreground">
                                Prilohy
                            </h3>
                            <div class="rounded-md border bg-background">
                                <div class="divide-y">
                                    <div
                                        v-for="item in combinedAttachments"
                                        :key="item.id"
                                        class="grid gap-4 px-3 py-3 text-sm md:grid-cols-[6rem_1fr_auto] md:items-start"
                                    >
                                        <div class="flex items-start">
                                            <img
                                                v-if="item.download_url"
                                                :src="item.download_url"
                                                alt=""
                                                class="h-16 w-20 rounded-md object-cover"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <textarea
                                                :id="`caption-${item.id}`"
                                                rows="4"
                                                class="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                                v-model="item.captionDraft"
                                                placeholder="Popis alebo poznamka"
                                                @blur="saveAttachmentCaption(item)"
                                            ></textarea>
                                            <div
                                                v-if="item.isPending"
                                                class="text-xs text-muted-foreground"
                                            >
                                                Bude ulozene po ulozeni dennika.
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                aria-label="Posunut vyssie"
                                                title="Posunut vyssie"
                                                @click="moveAttachment(item.id, 'up')"
                                            >
                                                <ChevronUp class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                aria-label="Posunut nizsie"
                                                title="Posunut nizsie"
                                                @click="moveAttachment(item.id, 'down')"
                                            >
                                                <ChevronDown class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                v-if="item.isPending"
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                aria-label="Zmazat"
                                                title="Zmazat"
                                                @click="requestDeleteAttachment(item.id, true)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                v-if="!item.isPending"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                aria-label="Stiahnut"
                                                title="Stiahnut"
                                                @click="downloadAttachment(item.download_url)"
                                            >
                                                <Download class="h-4 w-4" />
                                                Stiahnut
                                            </Button>
                                            <Button
                                                v-if="!item.isPending"
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                aria-label="Zmazat"
                                                title="Zmazat"
                                                @click="requestDeleteAttachment(item.id, false)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                            <Loader2
                                                v-if="item.saving"
                                                class="h-4 w-4 animate-spin text-muted-foreground"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ isEdit ? 'Ulozit zmeny' : 'Ulozit dennik' }}
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/denniky">Zrusit</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>

    <Dialog :open="showDeleteModal" @update:open="showDeleteModal = $event">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Vymazat prilohu?</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Naozaj chcete vymazat tuto prilohu? Tato akcia je nevratna.
            </p>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="outline" @click="closeDeleteModal">
                        Zrusit
                    </Button>
                </DialogClose>
                <Button type="button" variant="destructive" @click="confirmDeleteAttachment">
                    Vymazat
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
