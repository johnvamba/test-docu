<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3';
import { Bold, Italic, List, ListOrdered, Minus, Quote, Redo2, Strikethrough, Underline, Undo2 } from '@lucide/vue';
import type { Component } from 'vue';

const props = defineProps<{ editor: Editor | undefined; disabled?: boolean }>();

type Level = 1 | 2 | 3;
const blocks: { label: string; level: Level | null; title: string }[] = [
  { label: 'Text', level: null, title: 'Normal text (Ctrl+Alt+0)' },
  { label: 'H1', level: 1, title: 'Heading 1 (Ctrl+Alt+1)' },
  { label: 'H2', level: 2, title: 'Heading 2 (Ctrl+Alt+2)' },
  { label: 'H3', level: 3, title: 'Heading 3 (Ctrl+Alt+3)' },
];

interface Mark { icon: Component; title: string; active: string; run: (e: Editor) => void }
const marks: Mark[] = [
  { icon: Bold, title: 'Bold (Ctrl+B)', active: 'bold', run: (e) => e.chain().focus().toggleBold().run() },
  { icon: Italic, title: 'Italic (Ctrl+I)', active: 'italic', run: (e) => e.chain().focus().toggleItalic().run() },
  { icon: Underline, title: 'Underline (Ctrl+U)', active: 'underline', run: (e) => e.chain().focus().toggleUnderline().run() },
  { icon: Strikethrough, title: 'Strikethrough (Ctrl+Shift+S)', active: 'strike', run: (e) => e.chain().focus().toggleStrike().run() },
];
const lists: Mark[] = [
  { icon: List, title: 'Bulleted list (Ctrl+Shift+8)', active: 'bulletList', run: (e) => e.chain().focus().toggleBulletList().run() },
  { icon: ListOrdered, title: 'Numbered list (Ctrl+Shift+7)', active: 'orderedList', run: (e) => e.chain().focus().toggleOrderedList().run() },
  { icon: Quote, title: 'Quote (Ctrl+Shift+B)', active: 'blockquote', run: (e) => e.chain().focus().toggleBlockquote().run() },
];

function setBlock(level: Level | null): void {
  const e = props.editor;
  if (!e) return;
  if (level === null) e.chain().focus().setParagraph().run();
  else e.chain().focus().toggleHeading({ level }).run();
}

function isBlock(level: Level | null): boolean {
  const e = props.editor;
  if (!e) return false;
  return level === null ? e.isActive('paragraph') : e.isActive('heading', { level });
}
</script>

<template>
  <div class="toolbar" role="toolbar" aria-label="Formatting">
    <div class="tb-group">
      <button class="tb-btn" type="button" title="Undo (Ctrl+Z)" aria-label="Undo" :disabled="disabled || !editor?.can().undo()" @click="editor?.chain().focus().undo().run()">
        <Undo2 :size="18" :stroke-width="1.5" />
      </button>
      <button class="tb-btn" type="button" title="Redo (Ctrl+Shift+Z)" aria-label="Redo" :disabled="disabled || !editor?.can().redo()" @click="editor?.chain().focus().redo().run()">
        <Redo2 :size="18" :stroke-width="1.5" />
      </button>
    </div>

    <div class="tb-group" role="group" aria-label="Text style">
      <button
        v-for="b in blocks"
        :key="b.label"
        class="tb-btn tb-text"
        type="button"
        :title="b.title"
        :aria-pressed="isBlock(b.level)"
        :disabled="disabled"
        @click="setBlock(b.level)"
      >
        {{ b.label }}
      </button>
    </div>

    <div class="tb-group" role="group" aria-label="Emphasis">
      <button
        v-for="m in marks"
        :key="m.active"
        class="tb-btn"
        type="button"
        :title="m.title"
        :aria-label="m.title.split(' (')[0]"
        :aria-pressed="!!editor?.isActive(m.active)"
        :disabled="disabled"
        @click="editor && m.run(editor)"
      >
        <component :is="m.icon" :size="18" :stroke-width="1.5" />
      </button>
    </div>

    <div class="tb-group" role="group" aria-label="Lists and blocks">
      <button
        v-for="m in lists"
        :key="m.active"
        class="tb-btn"
        type="button"
        :title="m.title"
        :aria-label="m.title.split(' (')[0]"
        :aria-pressed="!!editor?.isActive(m.active)"
        :disabled="disabled"
        @click="editor && m.run(editor)"
      >
        <component :is="m.icon" :size="18" :stroke-width="1.5" />
      </button>
      <button class="tb-btn" type="button" title="Divider" aria-label="Insert divider" :disabled="disabled" @click="editor?.chain().focus().setHorizontalRule().run()">
        <Minus :size="18" :stroke-width="1.5" />
      </button>
    </div>

    <slot />
  </div>
</template>
