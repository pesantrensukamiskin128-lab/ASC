<script setup lang="ts">
import { ref } from 'vue'
import {
  ArrowUpTrayIcon, ArrowDownTrayIcon,
  DocumentArrowDownIcon,
  ExclamationTriangleIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { useToast } from 'vue-toastification'

const props = defineProps<{
  exporting:    boolean
  importing:    boolean
  importErrors?: string[]
  exportParams?: Record<string, any>
  /** Tipe template: 'courses' | 'lecturers' | 'students' | 'staff' */
  templateType?: string
}>()

const emit = defineEmits<{
  export: [params: Record<string, any>]
  import: [file: File]
}>()

const toast         = useToast()
const fileInput     = ref<HTMLInputElement | null>(null)
const showErrors    = ref(false)
const downloadingTpl = ref(false)

function triggerImport() {
  fileInput.value?.click()
}

function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  emit('import', file)
  ;(e.target as HTMLInputElement).value = ''
}

async function downloadTemplate() {
  if (!props.templateType) return
  downloadingTpl.value = true
  try {
    const res = await api.get(`/templates/${props.templateType}`, { responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href  = url
    link.download = `template-${props.templateType}.xlsx`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
    toast.success('Template berhasil diunduh.')
  } catch {
    toast.error('Gagal mengunduh template.')
  } finally {
    downloadingTpl.value = false
  }
}
</script>

<template>
  <div class="contents">
    <div class="flex items-center gap-2">
      <!-- Download Template -->
      <button
        v-if="templateType"
        :disabled="downloadingTpl"
        title="Download template Excel"
        class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 disabled:opacity-50 border border-gray-200 rounded-lg transition-colors"
        @click="downloadTemplate"
      >
        <DocumentArrowDownIcon class="w-4 h-4" />
        <span class="hidden sm:inline">{{ downloadingTpl ? 'Mengunduh...' : 'Template' }}</span>
      </button>

      <!-- Export -->
      <button
        :disabled="exporting"
        class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-green-700 bg-green-50 hover:bg-green-100 disabled:opacity-50 border border-green-200 rounded-lg transition-colors"
        @click="$emit('export', exportParams ?? {})"
      >
        <ArrowDownTrayIcon class="w-4 h-4" />
        <span class="hidden sm:inline">{{ exporting ? 'Mengunduh...' : 'Export' }}</span>
      </button>

      <!-- Import -->
      <button
        :disabled="importing"
        class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 disabled:opacity-50 border border-blue-200 rounded-lg transition-colors"
        @click="triggerImport"
      >
        <ArrowUpTrayIcon class="w-4 h-4" />
        <span class="hidden sm:inline">{{ importing ? 'Mengimpor...' : 'Import' }}</span>
      </button>

      <input
        ref="fileInput"
        type="file"
        accept=".xlsx,.xls,.csv"
        class="hidden"
        @change="onFileChange"
      />
    </div>

    <!-- Error panel import -->
    <template v-if="importErrors?.length">
      <div
        v-if="!showErrors"
        class="mt-2 flex items-center gap-2 px-3 py-2 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800"
      >
        <ExclamationTriangleIcon class="w-4 h-4 shrink-0" />
        <span>{{ importErrors.length }} baris dilewati saat import.</span>
        <button class="underline ml-1 text-yellow-700 hover:text-yellow-900" @click="showErrors = true">
          Lihat detail
        </button>
      </div>

      <div v-else class="mt-2 bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm">
        <div class="flex items-center justify-between mb-2">
          <p class="font-medium text-yellow-800">
            Detail error import ({{ importErrors.length }} baris):
          </p>
          <button class="text-yellow-600 hover:text-yellow-800" @click="showErrors = false">
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
        <ul class="space-y-1 max-h-40 overflow-y-auto">
          <li v-for="(err, i) in importErrors" :key="i" class="text-yellow-700 text-xs">
            • {{ err }}
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>
