<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Bold,
    Italic,
    List,
    ListOrdered,
    Underline as UnderlineIcon,
} from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        modelValue: string;
    }>(),
    {
        modelValue: '',
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = ref<Editor | null>(null);

const editorClass =
    'min-h-[14rem] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] prose max-w-none';

const toolbarButton =
    'inline-flex items-center justify-center rounded-md border border-input bg-background p-1 text-muted-foreground transition hover:text-foreground hover:bg-muted/60';

onMounted(() => {
    editor.value = new Editor({
        content: props.modelValue || '',
        extensions: [
            StarterKit,
            Underline,
            TextAlign.configure({
                types: ['heading', 'paragraph'],
            }),
        ],
        editorProps: {
            attributes: {
                class: editorClass,
            },
        },
        onUpdate: ({ editor: tiptap }) => {
            emit('update:modelValue', tiptap.getHTML());
        },
    });
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value) {
            return;
        }
        const current = editor.value.getHTML();
        if (value !== current) {
            editor.value.commands.setContent(value || '', false);
        }
    }
);

onBeforeUnmount(() => {
    editor.value?.destroy();
    editor.value = null;
});
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-2 rounded-md border bg-muted/40 px-2 py-1.5">
            <button
                type="button"
                :class="[
                    toolbarButton,
                    editor?.isActive('bold') ? 'bg-muted text-foreground' : '',
                ]"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Bold class="h-4 w-4" />
            </button>
            <button
                type="button"
                :class="[
                    toolbarButton,
                    editor?.isActive('italic') ? 'bg-muted text-foreground' : '',
                ]"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <Italic class="h-4 w-4" />
            </button>
            <button
                type="button"
                :class="[
                    toolbarButton,
                    editor?.isActive('underline') ? 'bg-muted text-foreground' : '',
                ]"
                @click="editor?.chain().focus().toggleUnderline().run()"
            >
                <UnderlineIcon class="h-4 w-4" />
            </button>
            <div class="h-6 w-px bg-border"></div>
            <button
                type="button"
                :class="[
                    toolbarButton,
                    editor?.isActive('bulletList') ? 'bg-muted text-foreground' : '',
                ]"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <List class="h-4 w-4" />
            </button>
            <button
                type="button"
                :class="[
                    toolbarButton,
                    editor?.isActive('orderedList') ? 'bg-muted text-foreground' : '',
                ]"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="h-4 w-4" />
            </button>
            <div class="h-6 w-px bg-border"></div>
            <button
                type="button"
                :class="[
                    toolbarButton,
                    editor?.isActive({ textAlign: 'left' })
                        ? 'bg-muted text-foreground'
                        : '',
                ]"
                @click="editor?.chain().focus().setTextAlign('left').run()"
            >
                <AlignLeft class="h-4 w-4" />
            </button>
            <button
                type="button"
                :class="[
                    toolbarButton,
                    editor?.isActive({ textAlign: 'center' })
                        ? 'bg-muted text-foreground'
                        : '',
                ]"
                @click="editor?.chain().focus().setTextAlign('center').run()"
            >
                <AlignCenter class="h-4 w-4" />
            </button>
            <button
                type="button"
                :class="[
                    toolbarButton,
                    editor?.isActive({ textAlign: 'right' })
                        ? 'bg-muted text-foreground'
                        : '',
                ]"
                @click="editor?.chain().focus().setTextAlign('right').run()"
            >
                <AlignRight class="h-4 w-4" />
            </button>
            <button
                type="button"
                :class="[
                    toolbarButton,
                    editor?.isActive({ textAlign: 'justify' })
                        ? 'bg-muted text-foreground'
                        : '',
                ]"
                @click="editor?.chain().focus().setTextAlign('justify').run()"
            >
                <AlignJustify class="h-4 w-4" />
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>
