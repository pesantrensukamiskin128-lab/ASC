<script setup lang="ts">
import { ref, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import Link from '@tiptap/extension-link'
import Table from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableCell from '@tiptap/extension-table-cell'
import TableHeader from '@tiptap/extension-table-header'
import Image from '@tiptap/extension-image'

// Custom Table dengan atribut borderless
const CustomTable = Table.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      borderless: {
        default: false,
        parseHTML: (el: HTMLElement) => el.getAttribute('data-borderless') === 'true',
        renderHTML: (attrs: Record<string, any>) => attrs.borderless
          ? { 'data-borderless': 'true', class: 'borderless-table' }
          : {},
      },
    }
  },
})

const props = defineProps<{
  modelValue: string
  placeholder?: string
  minHeight?: string
  readonly?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const imgInput = ref<HTMLInputElement | null>(null)

const editor = useEditor({
  content: props.modelValue || '',
  editable: !props.readonly,
  extensions: [
    StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
    Underline,
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Link.configure({ openOnClick: false }),
    CustomTable.configure({ resizable: true }),
    TableRow,
    TableCell,
    TableHeader,
    Image.configure({ inline: false, allowBase64: true }),
  ],
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  },
  editorProps: {
    attributes: {
      class: 'focus:outline-none',
      style: `min-height: ${props.minHeight || '200px'}; padding: 12px 16px; font-family: 'Arial Narrow', Arial, sans-serif; font-size: 12pt; line-height: 1.6;`,
    },
  },
})

watch(() => props.modelValue, (val) => {
  if (!editor.value) return
  const current = editor.value.getHTML()
  if (val !== current) {
    editor.value.commands.setContent(val || '', false)
  }
})

function setLink() {
  const url = window.prompt('Masukkan URL:')
  if (url) editor.value?.chain().focus().setLink({ href: url }).run()
}

function addTable() {
  editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}

function toggleTableBorder() {
  if (!editor.value) return
  const current = editor.value.getAttributes('table').borderless || false
  editor.value.chain().focus().updateAttributes('table', { borderless: !current }).run()
}

function insertImageFromFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (ev) => {
    const src = ev.target?.result as string
    editor.value?.chain().focus().setImage({ src }).run()
  }
  reader.readAsDataURL(file)
  if (imgInput.value) imgInput.value.value = ''
}

function insertImageFromUrl() {
  const url = window.prompt('Masukkan URL gambar:')
  if (url) editor.value?.chain().focus().setImage({ src: url }).run()
}

// Helper class toolbar button
function tb(active: boolean | undefined): string {
  return `p-1.5 rounded text-sm transition-colors ${
    active ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
  } disabled:opacity-40 disabled:cursor-not-allowed`
}
</script>

<template>
  <div class="rich-text-editor border border-gray-300 rounded-lg overflow-hidden bg-white">
    <!-- Info -->
    <div v-if="!readonly" class="px-3 py-1.5 text-[11px] text-gray-400 border-b border-gray-100 bg-gray-50/50">
      Font default: Arial Narrow 12. Support teks Arab (gunakan rata kanan), tabel, dan format lengkap.
    </div>

    <!-- Toolbar -->
    <div v-if="!readonly" class="border-b border-gray-200 bg-gray-50 px-2 py-1.5 space-y-1">
      <!-- Row 1: Format teks, Heading, Alignment, Lists -->
      <div class="flex flex-wrap items-center gap-0.5">
        <!-- Bold, Italic, Underline, Strike -->
        <button type="button" :class="tb(editor?.isActive('bold'))" @click="editor?.chain().focus().toggleBold().run()" title="Bold"><strong>B</strong></button>
        <button type="button" :class="tb(editor?.isActive('italic'))" @click="editor?.chain().focus().toggleItalic().run()" title="Italic"><em>I</em></button>
        <button type="button" :class="tb(editor?.isActive('underline'))" @click="editor?.chain().focus().toggleUnderline().run()" title="Underline"><span class="underline">U</span></button>
        <button type="button" :class="tb(editor?.isActive('strike'))" @click="editor?.chain().focus().toggleStrike().run()" title="Coret"><span class="line-through">S</span></button>

        <span class="w-px h-5 bg-gray-200 mx-1" />

        <!-- Headings -->
        <button type="button" :class="tb(editor?.isActive('heading', { level: 1 }))" @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()" title="Heading 1"><span class="font-bold">H1</span></button>
        <button type="button" :class="tb(editor?.isActive('heading', { level: 2 }))" @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()" title="Heading 2"><span class="font-bold">H2</span></button>
        <button type="button" :class="tb(editor?.isActive('heading', { level: 3 }))" @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()" title="Heading 3"><span class="font-bold">H3</span></button>

        <span class="w-px h-5 bg-gray-200 mx-1" />

        <!-- Alignment -->
        <button type="button" :class="tb(editor?.isActive({ textAlign: 'left' }))" @click="editor?.chain().focus().setTextAlign('left').run()" title="Rata Kiri">≡</button>
        <button type="button" :class="tb(editor?.isActive({ textAlign: 'center' }))" @click="editor?.chain().focus().setTextAlign('center').run()" title="Tengah">≡</button>
        <button type="button" :class="tb(editor?.isActive({ textAlign: 'right' }))" @click="editor?.chain().focus().setTextAlign('right').run()" title="Rata Kanan (Arab)">≡</button>
        <button type="button" :class="tb(editor?.isActive({ textAlign: 'justify' }))" @click="editor?.chain().focus().setTextAlign('justify').run()" title="Rata Kanan-Kiri">≡</button>

        <span class="w-px h-5 bg-gray-200 mx-1" />

        <!-- Bullet Lists -->
        <button type="button" :class="tb(editor?.isActive('bulletList'))" @click="editor?.chain().focus().toggleBulletList().run()" title="Bullet List (•)">• ≡</button>
        <button type="button" :class="tb(false)" @click="editor?.chain().focus().toggleBulletList().run()" title="Dash List (-)">- ≡</button>

        <!-- Ordered Lists -->
        <button type="button" :class="tb(editor?.isActive('orderedList'))" @click="editor?.chain().focus().toggleOrderedList().run()" title="Angka (1.)"><span class="font-mono text-[10px]">1.</span></button>
        <button type="button" :class="tb(false)" @click="editor?.chain().focus().toggleOrderedList().run()" title="Huruf Besar (A.)"><span class="font-mono text-[10px]">A.</span></button>
        <button type="button" :class="tb(false)" @click="editor?.chain().focus().toggleOrderedList().run()" title="Huruf Kecil (a.)"><span class="font-mono text-[10px]">a.</span></button>
        <button type="button" :class="tb(false)" @click="editor?.chain().focus().toggleOrderedList().run()" title="Romawi Besar (I.)"><span class="font-mono text-[10px]">I.</span></button>
        <button type="button" :class="tb(false)" @click="editor?.chain().focus().toggleOrderedList().run()" title="Romawi Kecil (i.)"><span class="font-mono text-[10px]">i.</span></button>
      </div>

      <!-- Row 2: Blockquote, Link, Image, Table operations -->
      <div class="flex flex-wrap items-center gap-0.5">
        <!-- Blockquote & Link -->
        <button type="button" :class="tb(editor?.isActive('blockquote'))" @click="editor?.chain().focus().toggleBlockquote().run()" title="Kutipan">❝</button>
        <button type="button" :class="tb(editor?.isActive('link'))" @click="setLink" title="Link">🔗</button>
        <button type="button" :class="tb(false)" @click="imgInput?.click()" title="Sisipkan Gambar dari File">🖼</button>
        <button type="button" :class="tb(false)" @click="insertImageFromUrl" title="Sisipkan Gambar dari URL">🌐</button>
        <input ref="imgInput" type="file" accept="image/*" class="hidden" @change="insertImageFromFile" />

        <span class="w-px h-5 bg-gray-200 mx-1" />

        <!-- Table -->
        <button type="button" :class="tb(false)" @click="addTable" title="Sisipkan Tabel">⊞</button>
        <button type="button" :class="tb(false)" :disabled="!editor?.can().deleteTable()" @click="editor?.chain().focus().deleteTable().run()" title="Hapus Tabel">✕⊞</button>
        <button type="button" :class="tb(false)" :disabled="!editor?.can().addColumnBefore()" @click="editor?.chain().focus().addColumnBefore().run()" title="Tambah Kolom Kiri">◁+</button>
        <button type="button" :class="tb(false)" :disabled="!editor?.can().addColumnAfter()" @click="editor?.chain().focus().addColumnAfter().run()" title="Tambah Kolom Kanan">+▷</button>
        <button type="button" :class="tb(false)" :disabled="!editor?.can().deleteColumn()" @click="editor?.chain().focus().deleteColumn().run()" title="Hapus Kolom">✕↕</button>
        <button type="button" :class="tb(false)" :disabled="!editor?.can().addRowBefore()" @click="editor?.chain().focus().addRowBefore().run()" title="Tambah Baris Atas">↑+</button>
        <button type="button" :class="tb(false)" :disabled="!editor?.can().addRowAfter()" @click="editor?.chain().focus().addRowAfter().run()" title="Tambah Baris Bawah">+↓</button>
        <button type="button" :class="tb(false)" :disabled="!editor?.can().deleteRow()" @click="editor?.chain().focus().deleteRow().run()" title="Hapus Baris">✕↔</button>
        <button type="button" :class="tb(false)" :disabled="!editor?.can().mergeCells()" @click="editor?.chain().focus().mergeCells().run()" title="Gabung Sel">⊡→</button>
        <button type="button" :class="tb(false)" :disabled="!editor?.can().splitCell()" @click="editor?.chain().focus().splitCell().run()" title="Pisah Sel">→⊡</button>
        <button type="button" :class="tb(editor?.isActive('table') && editor?.getAttributes('table').borderless)" :disabled="!editor?.isActive('table')" @click="toggleTableBorder" title="Hapus/Tambah Garis Tabel">⊡</button>
      </div>

      <!-- Row 3: Undo/Redo -->
      <div class="flex flex-wrap items-center gap-0.5">
        <button type="button" :class="tb(false)" :disabled="!editor?.can().undo()" @click="editor?.chain().focus().undo().run()" title="Undo">↩</button>
        <button type="button" :class="tb(false)" :disabled="!editor?.can().redo()" @click="editor?.chain().focus().redo().run()" title="Redo">↪</button>
      </div>
    </div>

    <!-- Editor Content -->
    <EditorContent :editor="editor" />
  </div>
</template>

<style>
.rich-text-editor .tiptap {
  outline: none;
  font-family: 'Arial Narrow', Arial, sans-serif;
  font-size: 12pt;
}
.rich-text-editor .tiptap p { margin: 4px 0; }
.rich-text-editor .tiptap h1 { font-size: 1.5em; font-weight: bold; margin: 12px 0 6px; }
.rich-text-editor .tiptap h2 { font-size: 1.3em; font-weight: bold; margin: 10px 0 5px; }
.rich-text-editor .tiptap h3 { font-size: 1.1em; font-weight: bold; margin: 8px 0 4px; }
.rich-text-editor .tiptap table {
  border-collapse: collapse;
  width: 100%;
  margin: 8px 0;
}
.rich-text-editor .tiptap table td,
.rich-text-editor .tiptap table th {
  border: 1px solid #d1d5db;
  padding: 6px 10px;
  min-width: 60px;
  vertical-align: top;
}
.rich-text-editor .tiptap table th {
  background: #f3f4f6;
  font-weight: 600;
}
.rich-text-editor .tiptap table .selectedCell {
  background: #dbeafe;
}
.rich-text-editor .tiptap table.borderless-table td,
.rich-text-editor .tiptap table.borderless-table th {
  border: 1px dashed #e5e7eb;
}
@media print {
  .rich-text-editor .tiptap table.borderless-table td,
  .rich-text-editor .tiptap table.borderless-table th {
    border: none;
  }
}
.rich-text-editor .tiptap ul { list-style-type: disc; padding-left: 1.5em; margin: 4px 0; }
.rich-text-editor .tiptap ol { list-style-type: decimal; padding-left: 1.5em; margin: 4px 0; }
.rich-text-editor .tiptap li { margin: 2px 0; }
.rich-text-editor .tiptap blockquote {
  border-left: 3px solid #d1d5db;
  padding-left: 12px;
  color: #6b7280;
  font-style: italic;
  margin: 8px 0;
}
.rich-text-editor .tiptap a { color: #2563eb; text-decoration: underline; }
.rich-text-editor .tiptap img {
  max-width: 100%;
  height: auto;
  margin: 8px 0;
  border-radius: 4px;
}
.rich-text-editor .tiptap .tableWrapper { overflow-x: auto; }
</style>
