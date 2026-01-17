<script setup lang="ts">
import { computed, ref } from 'vue';

import { X } from 'lucide-vue-next';

import { Input } from '@/components/ui/input';

interface PersonOption {
    id: number;
    first_name: string;
    last_name: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string[];
        options: PersonOption[];
        placeholder?: string;
        emptyText?: string;
    }>(),
    {
        placeholder: 'Zacnite pisat meno',
        emptyText: 'Zoznam je prazdny.',
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string[]): void;
}>();

const search = ref('');

const labelMap = computed(() => {
    const map = new Map<number, string>();
    props.options.forEach((person) => {
        map.set(person.id, `${person.first_name} ${person.last_name}`.trim());
    });
    return map;
});

function getPersonLabel(id: string) {
    if (!id) {
        return '';
    }
    const numericId = Number(id);
    if (!Number.isFinite(numericId)) {
        return '';
    }
    return labelMap.value.get(numericId) ?? '';
}

const selectedMembers = computed(() =>
    props.modelValue
        .map((id) => ({ id, label: getPersonLabel(id) }))
        .filter((member) => member.label !== '')
);

function filterByQuery(query: string) {
    const normalized = query.trim().toLowerCase();
    if (!normalized) {
        return [] as PersonOption[];
    }
    const selected = new Set(props.modelValue);
    return props.options.filter((person) => {
        const fullName = `${person.first_name} ${person.last_name}`.toLowerCase();
        return fullName.includes(normalized) && !selected.has(String(person.id));
    });
}

const filteredOptions = computed(() => filterByQuery(search.value));

function addMember(id: string) {
    const value = String(id);
    if (!props.modelValue.includes(value)) {
        emit('update:modelValue', [...props.modelValue, value]);
    }
    search.value = '';
}

function removeMember(id: string) {
    const value = String(id);
    emit(
        'update:modelValue',
        props.modelValue.filter((memberId) => memberId !== value)
    );
}
</script>

<template>
    <div class="grid gap-3">
        <div
            class="min-h-[6rem] rounded-md border bg-background"
            :class="selectedMembers.length > 0 ? '' : 'flex items-center'"
        >
            <div
                v-if="selectedMembers.length === 0"
                class="w-full px-3 py-2 text-sm text-muted-foreground"
            >
                Ziadni vybrani clenovia.
            </div>
            <div class="divide-y">
                <div
                    v-for="member in selectedMembers"
                    :key="member.id"
                    class="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                >
                    <span>{{ member.label }}</span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        @click="removeMember(member.id)"
                    >
                        <X class="h-4 w-4" />
                        Zmazat
                    </button>
                </div>
            </div>
        </div>
        <div class="relative">
            <Input
                v-model="search"
                type="search"
                :placeholder="placeholder"
            />
            <div
                v-if="search.trim() !== ''"
                class="absolute z-10 mt-1 w-full rounded-md border bg-background p-2 shadow"
            >
                <div
                    v-if="filteredOptions.length > 0"
                    class="max-h-56 space-y-2 overflow-y-auto pr-1"
                >
                    <button
                        v-for="person in filteredOptions"
                        :key="person.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1 text-left text-sm hover:bg-muted/60"
                        @click="addMember(String(person.id))"
                    >
                        <span>{{ person.first_name }} {{ person.last_name }}</span>
                        <span class="text-xs text-muted-foreground">Pridat</span>
                    </button>
                </div>
                <div v-else class="px-2 py-3 text-sm text-muted-foreground">
                    {{ emptyText }}
                </div>
            </div>
        </div>
    </div>
</template>
