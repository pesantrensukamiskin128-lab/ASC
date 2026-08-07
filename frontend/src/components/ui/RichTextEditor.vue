<script setup lang="ts">
import { watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import Link from '@tiptap/extension-link'
import Table from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableCell from '@tiptap/extension-table-cell'
import TableHeader from '@tiptap/extension-table-header'

const props = defineProps<{
  modelValue: string
  placeholder?: string
  minHeight?: string
  readonly?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const editor = useEditor({
  content: props.modelValue || '',
  editable: !props.readonly,
  extensions: [
    StarterKit,
    Underline,
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Link.configure({ openOnClick: false }),
    Table.configure({ resizable: true }),
    TableRow,
    TableCell,
    TableHeader,
  ],
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  },
  editorProps: {
    attributes: {
      class: 'focus:outline-none prose prose-sm max-w-none',
      style: `min-height: ${props.minHeight || '200px'}; padding: 12px 16px;`,
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
</script>

<template>
  <div class="rich-text-editor border border-gray-300 rounded-lg overflow-hidden">
    <!-- Toolbar -->
    <div v-if="!readonly" class="flex flex-wrap items-center gap-0.5 px-2 py-1.5 border-b border-gray-200 bg-gray-50">
      <!-- Format -->
      <button type="button" :class="['p-1.5 rounded text-sm font-bold', editor?.isActive('bold') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100']" @click="editor?.chain().focus().toggleBold().run()" title="Bold">B</button>
      <button type="button" :class="['p-1.5 rounded text-sm italic', editor?.isActive('italic') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100']" @click="editor?.chain().focus().toggleItalic().run()" title="Italic">I</button>
      <button type="button" :class="['p-1.5 rounded text-sm underline', editor?.isActive('underline') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100']" @click="editor?.chain().focus().toggleUnderline().run()" title="Underline">U</button>
      <button type="button" :class="['p-1.5 rounded text-sm line-through', editor?.isActive('strike') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100']" @click="editor?.chain().focus().toggleStrike().run()" title="Strikethrough">S</button>

      <div class="w-px h-5 bg-gray-200 mx-1" />

      <!-- Alignment -->
      <button type="button" :class="['p-1.5 rounded text-xs', editor?.isActive({ textAlign: 'left' }) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100']" @click="editor?.chain().focus().setTextAlign('left').run()" title="Rata Kiri">⫷</button>
      <button type="button" :class="['p-1.5 rounded text-xs', editor?.isActive({ textAlign: 'center' }) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100']" @click="editor?.chain().focus().setTextAlign('center').run()" title="Tengah">☰</button>
      <button type="button" :class="['p-1.5 rounded text-xs', editor?.isActive({ textAlign: 'right' }) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100']" @click="editor?.chain().focus().setTextAlign('right').run()" title="Rata Kanan">⫸</button>
      <button type="button" :class="['p-1.5 rounded text-xs', editor?.isActive({ textAlign: 'justify' }) ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100']" @click="editor?.chain().focus().setTextAlign('justify').run()" title="Justify">☰</button>

      <div class="w-px h-5 bg-gray-200 mx-1" />

      <!-- Lists -->
      <button type="button" :class="['p-1.5 rounded text-xs', editor?.isActive('bulletList') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100']" @click="editor?.chain().focus().toggleBulletList().run()" title="Bullet List">• ≡</button>
      <button type="button" :class="['p-1.5 rounded text-xs', editor?.isActive('orderedList') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100']" @click="editor?.chain().focus().toggleOrderedList().run()" title="Numbered List">1. ≡</button>

      <div class="w-px h-5 bg-gray-200 mx-1" />

      <!-- Link & Table -->
      <button type="button" class="p-1.5 rounded text-xs text-gray-600 hover:bg-gray-100" @click="setLink" title="Tambah Link">🔗</button>
      <button type="button" class="p-1.5 rounded text-xs text-gray-600 hover:bg-gray-100" @click="addTable" title="Sisipkan Tabel">⊞</button>
      <button type="button" class="p-1.5 rounded text-xs text-gray-600 hover:bg-gray-100" :disabled="!editor?.can().deleteTable()" @click="editor?.chain().focus().deleteTable().run()" title="Hapus Tabel">✕⊞</button>

      <div class="w-px h-5 bg-gray-200 mx-1" />

      <!-- Table row/col (hanya aktif saat di dalam tabel) -->
      <button type="button" class="p-1.5 rounded text-[10px] text-gray-600 hover:bg-gray-100" :disabled="!editor?.can().addColumnAfter()" @click="editor?.chain().focus().addColumnAfter().run()" title="Tambah Kolom">+↕</button>
      <button type="button" class="p-1.5 rounded text-[10px] text-gray-600 hover:bg-gray-100" :disabled="!editor?.can().addRowAfter()" @click="editor?.chain().focus().addRowAfter().run()" title="Tambah Baris">+↔</button>
      <button type="button" class="p-1.5 rounded text-[10px] text-gray-600 hover:bg-gray-100" :disabled="!editor?.can().deleteColumn()" @click="editor?.chain().focus().deleteColumn().run()" title="Hapus Kolom">✕↕</button>
      <button type="button" class="p-1.5 rounded text-[10px] text-gray-600 hover:bg-gray-100" :disabled="!editor?.can().deleteRow()" @click="editor?.chain().focus().deleteRow().run()" title="Hapus Baris">✕↔</button>

      <div class="w-px h-5 bg-gray-200 mx-1" />

      <!-- Undo/Redo -->
      <button type="button" class="p-1.5 rounded text-xs text-gray-600 hover:bg-gray-100" :disabled="!editor?.can().undo()" @click="editor?.chain().focus().undo().run()" title="Undo">↩</button>
      <button type="button" class="p-1.5 rounded text-xs text-gray-600 hover:bg-gray-100" :disabled="!editor?.can().redo()" @click="editor?.chain().focus().redo().run()" title="Redo">↪</button>
    </div>

    <!-- Editor Content -->
    <EditorContent :editor="editor" />
  </div>
</template>

<style>
.rich-text-editor .tiptap {
  outline: none;
}
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
.rich-text-editor .tiptap ul { list-style-type: disc; padding-left: 1.5em; }
.rich-text-editor .tiptap ol { list-style-type: decimal; padding-left: 1.5em; }
.rich-text-editor .tiptap blockquote {
  border-left: 3px solid #d1d5db;
  padding-left: 12px;
  color: #6b7280;
  font-style: italic;
}
.rich-text-editor .tiptap a { color: #2563eb; text-decoration: underline; }
.rich-text-editor .tiptap p { margin: 4px 0; }
</style>
