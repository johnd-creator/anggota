<template>
  <div class="tiptap-editor" :class="{ 'tiptap-disabled': disabled }">
    <!-- Toolbar -->
    <div class="tiptap-toolbar" v-if="editor">
      <!-- Text formatting -->
      <button
        type="button"
        @click="editor.chain().focus().toggleBold().run()"
        :class="{ 'is-active': editor.isActive('bold') }"
        :disabled="disabled"
        title="Bold"
      >
        <strong>B</strong>
      </button>
      <button
        type="button"
        @click="editor.chain().focus().toggleItalic().run()"
        :class="{ 'is-active': editor.isActive('italic') }"
        :disabled="disabled"
        title="Italic"
      >
        <em>I</em>
      </button>
      <button
        type="button"
        @click="editor.chain().focus().toggleUnderline().run()"
        :class="{ 'is-active': editor.isActive('underline') }"
        :disabled="disabled"
        title="Underline"
      >
        <u>U</u>
      </button>

      <span class="tiptap-divider"></span>

      <!-- Lists -->
      <button
        type="button"
        @click="editor.chain().focus().toggleBulletList().run()"
        :class="{ 'is-active': editor.isActive('bulletList') }"
        :disabled="disabled"
        title="Bullet List"
      >
        •≡
      </button>
      <button
        type="button"
        @click="editor.chain().focus().toggleOrderedList().run()"
        :class="{ 'is-active': editor.isActive('orderedList') }"
        :disabled="disabled"
        title="Numbered List"
      >
        1.
      </button>

      <span class="tiptap-divider"></span>

      <!-- Headings -->
      <button
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
        :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }"
        :disabled="disabled"
        title="Heading 2"
      >
        H2
      </button>
      <button
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
        :class="{ 'is-active': editor.isActive('heading', { level: 3 }) }"
        :disabled="disabled"
        title="Heading 3"
      >
        H3
      </button>

      <span class="tiptap-divider"></span>

      <!-- Text Alignment -->
      <button
        type="button"
        @click="editor.chain().focus().setTextAlign('left').run()"
        :class="{ 'is-active': editor.isActive({ textAlign: 'left' }) }"
        :disabled="disabled"
        title="Align Left"
      >
        ⬐
      </button>
      <button
        type="button"
        @click="editor.chain().focus().setTextAlign('center').run()"
        :class="{ 'is-active': editor.isActive({ textAlign: 'center' }) }"
        :disabled="disabled"
        title="Align Center"
      >
        ≡
      </button>
      <button
        type="button"
        @click="editor.chain().focus().setTextAlign('right').run()"
        :class="{ 'is-active': editor.isActive({ textAlign: 'right' }) }"
        :disabled="disabled"
        title="Align Right"
      >
        ⬎
      </button>
      <button
        type="button"
        @click="editor.chain().focus().setTextAlign('justify').run()"
        :class="{ 'is-active': editor.isActive({ textAlign: 'justify' }) }"
        :disabled="disabled"
        title="Justify"
      >
        ☰
      </button>

      <span class="tiptap-divider"></span>

      <button
        type="button"
        @click="changeIndent(1)"
        :disabled="disabled"
        title="Geser ke kanan"
      >
        ⇥
      </button>
      <button
        type="button"
        @click="changeIndent(-1)"
        :disabled="disabled"
        title="Geser ke kiri"
      >
        ⇤
      </button>

      <span class="tiptap-divider"></span>

      <!-- Link -->
      <button
        type="button"
        @click="setLink"
        :class="{ 'is-active': editor.isActive('link') }"
        :disabled="disabled"
        title="Add Link"
      >
        🔗
      </button>
      <button
        type="button"
        v-if="editor.isActive('link')"
        @click="editor.chain().focus().unsetLink().run()"
        :disabled="disabled"
        title="Remove Link"
      >
        ✕
      </button>

      <span class="tiptap-divider"></span>

      <!-- Table -->
      <div class="tiptap-dropdown-wrapper">
        <button
          type="button"
          @click="showTableMenu = !showTableMenu"
          :class="{ 'is-active': editor.isActive('table') }"
          :disabled="disabled"
          title="Table"
        >
          ⊞
        </button>
        <div v-if="showTableMenu" class="tiptap-dropdown" @mouseleave="showTableMenu = false">
          <button type="button" @click="insertTable(); showTableMenu = false" :disabled="disabled">
            Insert Table (3×3)
          </button>
          <button type="button" @click="editor.chain().focus().addColumnBefore().run(); showTableMenu = false" :disabled="disabled || !editor.can().addColumnBefore()">
            Add Column Before
          </button>
          <button type="button" @click="editor.chain().focus().addColumnAfter().run(); showTableMenu = false" :disabled="disabled || !editor.can().addColumnAfter()">
            Add Column After
          </button>
          <button type="button" @click="editor.chain().focus().deleteColumn().run(); showTableMenu = false" :disabled="disabled || !editor.can().deleteColumn()">
            Delete Column
          </button>
          <button type="button" @click="editor.chain().focus().addRowBefore().run(); showTableMenu = false" :disabled="disabled || !editor.can().addRowBefore()">
            Add Row Before
          </button>
          <button type="button" @click="editor.chain().focus().addRowAfter().run(); showTableMenu = false" :disabled="disabled || !editor.can().addRowAfter()">
            Add Row After
          </button>
          <button type="button" @click="editor.chain().focus().deleteRow().run(); showTableMenu = false" :disabled="disabled || !editor.can().deleteRow()">
            Delete Row
          </button>
          <button type="button" @click="editor.chain().focus().mergeCells().run(); showTableMenu = false" :disabled="disabled || !editor.can().mergeCells()">
            Merge Cells
          </button>
          <button type="button" @click="editor.chain().focus().splitCell().run(); showTableMenu = false" :disabled="disabled || !editor.can().splitCell()">
            Split Cell
          </button>
          <button type="button" @click="editor.chain().focus().deleteTable().run(); showTableMenu = false" :disabled="disabled || !editor.can().deleteTable()" class="text-red-600">
            Delete Table
          </button>
        </div>
      </div>

      <span class="tiptap-divider"></span>

      <button
        type="button"
        @click="insertPageBreak"
        :disabled="disabled"
        title="Pindah ke halaman baru"
      >
        ⤓
      </button>

      <span class="tiptap-divider"></span>

      <!-- Undo/Redo -->
      <button
        type="button"
        @click="editor.chain().focus().undo().run()"
        :disabled="disabled || !editor.can().undo()"
        title="Undo"
      >
        ↩
      </button>
      <button
        type="button"
        @click="editor.chain().focus().redo().run()"
        :disabled="disabled || !editor.can().redo()"
        title="Redo"
      >
        ↪
      </button>
    </div>

    <!-- Editor content -->
    <EditorContent :editor="editor" class="tiptap-content" />
  </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import { Extension } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import TextAlign from '@tiptap/extension-text-align'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table-row'
import { TableHeader } from '@tiptap/extension-table-header'
import { TableCell } from '@tiptap/extension-table-cell'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

// Debounce timer
let debounceTimer = null

// Table menu visibility
const showTableMenu = ref(false)
const MAX_INDENT = 5
const INDENT_STEP_PX = 32

const Indent = Extension.create({
  name: 'indent',

  addGlobalAttributes() {
    return [
      {
        types: ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote', 'table'],
        attributes: {
          indent: {
            default: 0,
            parseHTML: element => {
              const dataIndent = Number(element.getAttribute('data-indent') || 0)
              if (!Number.isNaN(dataIndent) && dataIndent > 0) {
                return Math.min(dataIndent, MAX_INDENT)
              }

              const style = element.getAttribute('style') || ''
              const match = style.match(/margin-left\s*:\s*(\d+)px/i)
              if (!match) return 0

              const parsed = Math.round(Number(match[1]) / INDENT_STEP_PX)
              if (Number.isNaN(parsed) || parsed < 1) return 0

              return Math.min(parsed, MAX_INDENT)
            },
            renderHTML: attributes => {
              const indent = Number(attributes.indent || 0)
              if (!indent) return {}

              return {
                'data-indent': String(indent),
                style: `margin-left: ${indent * INDENT_STEP_PX}px;`,
              }
            },
          },
        },
      },
    ]
  },
})

const editor = useEditor({
  content: props.modelValue || '',
  editable: !props.disabled,
  extensions: [
    StarterKit.configure({
      heading: {
        levels: [2, 3],
      },
    }),
    Underline,
    Link.configure({
      openOnClick: false,
      validate: href => /^https?:\/\//.test(href),
      HTMLAttributes: {
        rel: 'noopener noreferrer nofollow',
        target: '_blank',
      },
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
    Indent,
    Table.configure({
      resizable: true,
      HTMLAttributes: {
        class: 'formal-letter-table',
      },
    }),
    TableRow,
    TableHeader,
    TableCell,
  ],
  onUpdate: ({ editor }) => {
    // Debounce update to parent
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      emit('update:modelValue', editor.getHTML())
    }, 100)
  },
})

// Watch for external modelValue changes
watch(() => props.modelValue, (newValue) => {
  if (editor.value && newValue !== editor.value.getHTML()) {
    editor.value.commands.setContent(newValue || '', false)
  }
})

// Watch for disabled state changes
watch(() => props.disabled, (newValue) => {
  if (editor.value) {
    editor.value.setEditable(!newValue)
  }
})

// Link prompt helper
function setLink() {
  if (!editor.value) return

  const previousUrl = editor.value.getAttributes('link').href
  const url = window.prompt('Enter URL:', previousUrl || 'https://')

  if (url === null) return // cancelled

  if (url === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }

  editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

// Insert table helper
function insertTable() {
  if (!editor.value) return
  editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: false }).run()
}

function getIndentTarget() {
  if (!editor.value) return null
  if (editor.value.isActive('table')) return 'table'
  if (editor.value.isActive('bulletList')) return 'bulletList'
  if (editor.value.isActive('orderedList')) return 'orderedList'
  if (editor.value.isActive('blockquote')) return 'blockquote'
  if (editor.value.isActive('heading')) return 'heading'
  return 'paragraph'
}

function changeIndent(direction) {
  const target = getIndentTarget()
  if (!editor.value || !target) return

  const current = Number(editor.value.getAttributes(target).indent || 0)
  const next = Math.max(0, Math.min(MAX_INDENT, current + direction))
  editor.value.chain().focus().updateAttributes(target, { indent: next }).run()
}

function insertPageBreak() {
  if (!editor.value) return
  editor.value.chain().focus().setHorizontalRule().run()
}

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
  if (editor.value) editor.value.destroy()
})
</script>
