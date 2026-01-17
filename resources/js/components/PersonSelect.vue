<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { Input } from '@/components/ui/input';

interface PersonOption {
    id: number;
    first_name: string;
    last_name: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: PersonOption[];
        placeholder?: string;
        emptyText?: string;
    }>(),
    {
        placeholder: 'Vyberte osobu',
        emptyText: 'Ziadne osoby.',
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const open = ref(false);
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

function filterByQuery(query: string) {
    const normalized = query.trim().toLowerCase();
    if (!normalized) {
        return props.options;
    }
    return props.options.filter((person) => {
        const fullName = `${person.first_name} ${person.last_name}`.toLowerCase();
        return fullName.includes(normalized);
    });
}

const filteredOptions = computed(() => {
    const selectedLabel = getPersonLabel(props.modelValue);
    const query = search.value === selectedLabel ? '' : search.value;
    return filterByQuery(query);
});

watch(
    () => props.modelValue,
    (value) => {
        if (!open.value) {
            search.value = getPersonLabel(value);
        }
    },
    { immediate: true }
);

function selectPerson(id: string) {
    emit('update:modelValue', id);
    search.value = getPersonLabel(id);
    open.value = false;
}

function clearSelection() {
    emit('update:modelValue', '');
    search.value = '';
    open.value = false;
}

function closeWithDelay() {
    setTimeout(() => {
        open.value = false;
    }, 100);
}
</script>

<template>
    <div class="relative">
        <Input
            v-model="search"
            type="search"
            :placeholder="placeholder"
            @focus="open = true"
            @input="open = true"
            @blur="closeWithDelay"
        />
        <div
            v-if="open && search.trim() !== ''"
            class="absolute z-10 mt-1 w-full rounded-md border bg-background shadow"
        >
            <button
                type="button"
                class="w-full px-3 py-2 text-left text-sm hover:bg-muted"
                @mousedown.prevent="clearSelection"
            >
                Zrusit vyber
            </button>
            <button
                v-for="person in filteredOptions"
                :key="person.id"
                type="button"
                class="w-full px-3 py-2 text-left text-sm hover:bg-muted"
                @mousedown.prevent="selectPerson(String(person.id))"
            >
                {{ person.first_name }} {{ person.last_name }}
            </button>
            <div
                v-if="filteredOptions.length === 0"
                class="px-3 py-2 text-sm text-muted-foreground"
            >
                {{ emptyText }}
            </div>
        </div>
    </div>
</template>
