<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';

import { Button } from '@/components/ui/button';

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
    'min-h-[14rem] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';

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
        <div class="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="editor && editor.isActive('bold') ? 'bg-muted' : ''"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                B
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="editor && editor.isActive('italic') ? 'bg-muted' : ''"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                I
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="editor && editor.isActive('underline') ? 'bg-muted' : ''"
                @click="editor?.chain().focus().toggleUnderline().run()"
            >
                U
            </Button>
            <div class="h-6 w-px bg-border"></div>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="editor && editor.isActive('bulletList') ? 'bg-muted' : ''"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                •
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="editor && editor.isActive('orderedList') ? 'bg-muted' : ''"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                1.
            </Button>
            <div class="h-6 w-px bg-border"></div>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="
                    editor && editor.isActive({ textAlign: 'left' })
                        ? 'bg-muted'
                        : ''
                "
                @click="editor?.chain().focus().setTextAlign('left').run()"
            >
                L
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="
                    editor && editor.isActive({ textAlign: 'center' })
                        ? 'bg-muted'
                        : ''
                "
                @click="editor?.chain().focus().setTextAlign('center').run()"
            >
                C
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="
                    editor && editor.isActive({ textAlign: 'right' })
                        ? 'bg-muted'
                        : ''
                "
                @click="editor?.chain().focus().setTextAlign('right').run()"
            >
                R
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="
                    editor && editor.isActive({ textAlign: 'justify' })
                        ? 'bg-muted'
                        : ''
                "
                @click="editor?.chain().focus().setTextAlign('justify').run()"
            >
                J
            </Button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>
