<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import PersonMultiSelect from '@/components/PersonMultiSelect.vue';
import PersonSelect from '@/components/PersonSelect.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
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

const props = defineProps<{
    diary: DiaryFormData | null;
    persons: PersonOption[];
}>();

const isEdit = computed(() => Boolean(props.diary?.id));

const tabs = [
    'Zakladne informacie',
    'Osoby',
    'Popis pracovnej cinnosti',
    'Metriky',
    'Podpisova cast',
] as const;

const activeTab = ref<(typeof tabs)[number]>(tabs[0]);

const membersPlaceholder = 'Zacnite pisat meno';


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
                <Button variant="outline" as-child>
                    <Link href="/denniky">Spat na zoznam</Link>
                </Button>
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
                        <div class="md:col-span-2 lg:col-span-3 grid gap-2">
                            <Label for="work-time">Pracovny cas</Label>
                            <Input
                                id="work-time"
                                v-model="form.work_time"
                                placeholder="Napr. 09:00 - 17:30"
                            />
                            <InputError :message="form.errors.work_time" />
                        </div>
                        <div class="md:col-span-2 lg:col-span-3 grid gap-2">
                            <Label for="weather">Pocasie pocas akcie</Label>
                            <textarea
                                id="weather"
                                rows="3"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                v-model="form.weather"
                            ></textarea>
                            <InputError :message="form.errors.weather" />
                        </div>
                    </div>
                </div>

                <div
                    v-show="activeTab === 'Osoby'"
                    class="rounded-md border border-sidebar-border bg-sidebar p-4 [&_input]:bg-background [&_select]:bg-background [&_textarea]:bg-background"
                >
                    <h2 class="text-lg font-semibold">Osoby</h2>
                    <div class="mt-4 grid gap-6 lg:grid-cols-3">
                        <div class="grid gap-3">
                            <Label for="member-search">Ostatni clenovia SSS</Label>
                            <PersonMultiSelect
                                v-model="form.member_person_ids"
                                :options="props.persons"
                                :placeholder="membersPlaceholder"
                            />
                            <InputError :message="form.errors.member_person_ids" />
                        </div>
                        <div class="grid gap-4 lg:col-span-2">
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
                            <div class="grid gap-2">
                                <Label for="other-participants">Ini ucastnici</Label>
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
                    v-show="activeTab === 'Popis pracovnej cinnosti'"
                    class="rounded-md border border-sidebar-border bg-sidebar p-4 [&_input]:bg-background [&_select]:bg-background [&_textarea]:bg-background"
                >
                    <h2 class="text-lg font-semibold">Popis pracovnej cinnosti</h2>
                    <div class="mt-4 grid gap-2">
                        <Label for="work-description">Popis</Label>
                        <RichTextEditor v-model="form.work_description" />
                        <InputError :message="form.errors.work_description" />
                    </div>
                </div>

                <div
                    v-show="activeTab === 'Metriky'"
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
                </div>

                <div
                    v-show="activeTab === 'Podpisova cast'"
                    class="rounded-md border border-sidebar-border bg-sidebar p-4 [&_input]:bg-background [&_select]:bg-background [&_textarea]:bg-background"
                >
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
</template>
