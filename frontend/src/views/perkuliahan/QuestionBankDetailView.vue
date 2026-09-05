<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ArrowLeftIcon, PlusIcon, PencilIcon, TrashIcon, ArrowUpTrayIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const loading = ref(true)
const bank = ref<any>(null)

const qModal = ref(false)
const editingQId = ref<number | null>(null)
const savingQ = ref(false)
const qForm = reactive({
  type: 'PILIHAN_GANDA', question: '', options: ['', '', '', ''],
  correct_answer: '', default_score: 1, explanation: '', difficulty: 'SEDANG', tags: [] as string[],
})
const tagInput = ref('')

const questionTypes = [
  { value: 'PILIHAN_GANDA', label: 'Pilihan Ganda' },
  { value: 'BENAR_SALAH', label: 'Benar / Salah' },
  { value: 'ESAI', label: 'Esai' },
  { value: 'STUDI_KASUS', label: 'Studi Kasus' },
  { value: 'UPLOAD_FILE', label: 'Upload File' },
]
const difficulties = ['MUDAH', 'SEDANG', 'SULIT']
const diffColor: Record<string, string> = { MUDAH: 'bg-green-100 text-green-700', SEDANG: 'bg-yellow-100 text-yellow-700', SULIT: 'bg-red-100 text-red-700' }

onMounted(async () => {
  try { const { data } = await api.get(`/question-banks/${route.params.id}`); bank.value = data }
  finally { loading.value = false }
})

async function reload() { const { data } = await api.get(`/question-banks/${route.params.id}`); bank.value = data }

const totalItems = computed(() => bank.value?.items?.length ?? 0)
const byDiff = computed(() => {
  if (!bank.value?.items) return {}
  return bank.value.items.reduce((acc: any, i: any) => { acc[i.difficulty ?? 'SEDANG'] = (acc[i.difficulty ?? 'SEDANG'] || 0) + 1; return acc }, {})
})

function openAdd() {
  editingQId.value = null
  Object.assign(qForm, { type: 'PILIHAN_GANDA', question: '', options: ['', '', '', ''], correct_answer: '', default_score: 1, explanation: '', difficulty: 'SEDANG', tags: [] })
  qModal.value = true
}

function openEdit(item: any) {
  editingQId.value = item.id
  Object.assign(qForm, {
    type: item.type, question: item.question,
    options: item.options?.length ? [...item.options] : ['', '', '', ''],
    correct_answer: item.correct_answer ?? '', default_score: item.default_score ?? 1,
    explanation: item.explanation ?? '', difficulty: item.difficulty ?? 'SEDANG',
    tags: item.tags ?? [],
  })
  qModal.value = true
}

function addOption() { qForm.options.push('') }
function removeOption(i: number) { if (qForm.options.length > 2) qForm.options.splice(i, 1) }
function addTag() { if (tagInput.value.trim() && !qForm.tags.includes(tagInput.value.trim())) { qForm.tags.push(tagInput.value.trim()); tagInput.value = '' } }
function removeTag(i: number) { qForm.tags.splice(i, 1) }

async function saveItem() {
  savingQ.value = true
  try {
    const payload: any = { ...qForm }
    if (['PILIHAN_GANDA'].includes(qForm.type)) { payload.options = qForm.options.filter(o => o.trim()) }
    else if (qForm.type === 'BENAR_SALAH') { payload.options = ['Benar', 'Salah'] }
    else { payload.options = null }

    if (editingQId.value) {
      await api.put(`/question-banks/${bank.value.id}/items/${editingQId.value}`, payload)
      toast.success('Soal diupdate.')
    } else {
      await api.post(`/question-banks/${bank.value.id}/items`, payload)
      toast.success('Soal ditambahkan.')
    }
    qModal.value = false; reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { savingQ.value = false }
}

async function deleteItem(item: any) {
  if (!confirm('Hapus soal ini?')) return
  try { await api.delete(`/question-banks/${bank.value.id}/items/${item.id}`); toast.success('Dihapus.'); reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// === IMPORT EXCEL ===
const importModal    = ref(false)
const importFile     = ref<File | null>(null)
const importing      = ref(false)
const importResult   = ref<any>(null)

function openImport() {
  importFile.value = null
  importResult.value = null
  importModal.value = true
}

function onFileChange(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  importFile.value = f ?? null
  importResult.value = null
}

async function downloadTemplate() {
  try {
    const resp = await api.get(`/question-banks/${bank.value.id}/template`, { responseType: 'blob' })
    const url = URL.createObjectURL(resp.data)
    const a = document.createElement('a')
    a.href = url
    a.download = 'template-import-soal.xlsx'
    a.click()
    URL.revokeObjectURL(url)
  } catch { toast.error('Gagal download template.') }
}

async function doImport() {
  if (!importFile.value) { toast.warning('Pilih file Excel terlebih dahulu.'); return }
  importing.value = true
  importResult.value = null
  try {
    const fd = new FormData()
    fd.append('file', importFile.value)
    const { data } = await api.post(`/question-banks/${bank.value.id}/import-excel`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    importResult.value = data
    toast.success(data.message)
    reload()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal import.')
  } finally { importing.value = false }
}
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="bank" class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div class="flex-1">
        <h1 class="text-xl font-bold text-gray-900">{{ bank.title }}</h1>
        <p class="text-sm text-gray-500">{{ bank.course?.code }} — {{ bank.course?.name }}</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-3">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-blue-700">{{ totalItems }}</p><p class="text-xs text-blue-600">Total Soal</p>
      </div>
      <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-green-700">{{ byDiff['MUDAH'] ?? 0 }}</p><p class="text-xs text-green-600">Mudah</p>
      </div>
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-yellow-700">{{ byDiff['SEDANG'] ?? 0 }}</p><p class="text-xs text-yellow-600">Sedang</p>
      </div>
      <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-red-700">{{ byDiff['SULIT'] ?? 0 }}</p><p class="text-xs text-red-600">Sulit</p>
      </div>
    </div>

    <!-- Items -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-800">Daftar Soal</h2>
        <div class="flex items-center gap-2">
          <button class="flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg" @click="openImport">
            <ArrowUpTrayIcon class="w-3.5 h-3.5" /> Import Excel
          </button>
          <button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openAdd">
            <PlusIcon class="w-3.5 h-3.5" /> Tambah Soal
          </button>
        </div>
      </div>
      <div v-if="!bank.items?.length" class="text-center py-8 text-gray-400 text-sm">Belum ada soal.</div>
      <div v-else class="space-y-3">
        <div v-for="(item, i) in bank.items" :key="item.id" class="p-4 border border-gray-100 rounded-lg hover:border-blue-200">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-bold text-gray-400">#{{ Number(i) + 1 }}</span>
                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">{{ item.type.replace(/_/g, ' ') }}</span>
                <span :class="['text-xs px-1.5 py-0.5 rounded font-medium', diffColor[item.difficulty ?? 'SEDANG']]">{{ item.difficulty ?? 'SEDANG' }}</span>
                <span class="text-xs text-blue-600 font-medium ml-auto">{{ item.default_score }} poin</span>
              </div>
              <p class="text-sm text-gray-800 whitespace-pre-line">{{ item.question }}</p>
              <div v-if="item.options?.length" class="mt-2 space-y-1">
                <div v-for="(opt, oi) in item.options" :key="oi" class="flex items-center gap-2 text-xs">
                  <span :class="['w-5 h-5 flex items-center justify-center rounded-full text-xs font-bold', item.correct_answer === opt ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600']">{{ String.fromCharCode(65 + Number(oi)) }}</span>
                  <span :class="item.correct_answer === opt ? 'text-green-700 font-medium' : 'text-gray-600'">{{ opt }}</span>
                </div>
              </div>
              <div v-if="item.tags?.length" class="flex flex-wrap gap-1 mt-2">
                <span v-for="tag in item.tags" :key="tag" class="text-xs px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded">{{ tag }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <button class="p-1.5 rounded text-blue-600 hover:bg-blue-50" @click="openEdit(item)"><PencilIcon class="w-4 h-4" /></button>
              <button class="p-1.5 rounded text-red-500 hover:bg-red-50" @click="deleteItem(item)"><TrashIcon class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <BaseModal :open="qModal" :title="editingQId ? 'Edit Soal' : 'Tambah Soal'" size="xl" @close="qModal = false">
    <form class="space-y-4" @submit.prevent="saveItem">
      <div class="grid grid-cols-3 gap-4">
        <div><label class="text-xs font-medium text-gray-700">Tipe <span class="text-red-500">*</span></label><select v-model="qForm.type" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option v-for="qt in questionTypes" :key="qt.value" :value="qt.value">{{ qt.label }}</option></select></div>
        <div><label class="text-xs font-medium text-gray-700">Tingkat Kesulitan</label><select v-model="qForm.difficulty" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option v-for="d in difficulties" :key="d" :value="d">{{ d }}</option></select></div>
        <div><label class="text-xs font-medium text-gray-700">Skor</label><input v-model.number="qForm.default_score" type="number" min="0.5" step="0.5" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div><label class="text-xs font-medium text-gray-700">Pertanyaan <span class="text-red-500">*</span></label><textarea v-model="qForm.question" required rows="3" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div v-if="qForm.type === 'PILIHAN_GANDA'">
        <label class="text-xs font-medium text-gray-700 mb-2 block">Pilihan Jawaban</label>
        <div class="space-y-2">
          <div v-for="(opt, i) in qForm.options" :key="i" class="flex items-center gap-2">
            <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-200 text-gray-600 text-xs font-bold shrink-0">{{ String.fromCharCode(65 + i) }}</span>
            <input v-model="qForm.options[i]" class="flex-1 px-3 py-1.5 border rounded-lg text-sm" />
            <button v-if="qForm.options.length > 2" type="button" class="p-1 text-red-500" @click="removeOption(i)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </div>
        <button type="button" class="mt-2 text-xs text-blue-600 font-medium" @click="addOption">+ Tambah Pilihan</button>
      </div>
      <div v-if="['PILIHAN_GANDA'].includes(qForm.type)">
        <label class="text-xs font-medium text-gray-700">Jawaban Benar</label>
        <select v-model="qForm.correct_answer" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
          <option value="">-- Pilih --</option>
          <option v-for="(opt, i) in qForm.options.filter(o => o.trim())" :key="i" :value="opt">{{ String.fromCharCode(65 + i) }}. {{ opt }}</option>
        </select>
      </div>
      <div v-if="qForm.type === 'BENAR_SALAH'">
        <label class="text-xs font-medium text-gray-700">Jawaban Benar</label>
        <select v-model="qForm.correct_answer" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option value="Benar">Benar</option><option value="Salah">Salah</option></select>
      </div>
      <div><label class="text-xs font-medium text-gray-700">Penjelasan</label><textarea v-model="qForm.explanation" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div>
        <label class="text-xs font-medium text-gray-700">Tags</label>
        <div class="flex items-center gap-2 mt-1">
          <input v-model="tagInput" placeholder="Ketik tag lalu Enter" class="flex-1 px-3 py-1.5 border rounded-lg text-sm" @keydown.enter.prevent="addTag" />
          <button type="button" class="px-3 py-1.5 bg-gray-100 text-gray-700 text-xs rounded-lg" @click="addTag">+</button>
        </div>
        <div v-if="qForm.tags.length" class="flex flex-wrap gap-1 mt-2">
          <span v-for="(tag, i) in qForm.tags" :key="i" class="inline-flex items-center gap-1 text-xs px-2 py-0.5 bg-purple-50 text-purple-700 rounded">{{ tag }}<button type="button" class="text-purple-400 hover:text-purple-600" @click="removeTag(i)">×</button></span>
        </div>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="qModal = false">Batal</button>
      <button :disabled="savingQ" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveItem">{{ savingQ ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>

  <!-- Modal Import Excel -->
  <BaseModal :open="importModal" title="Import Soal dari Excel" @close="importModal = false">
    <div class="space-y-4">
      <!-- Panduan format -->
      <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700 space-y-1">
        <p class="font-semibold mb-1">Format kolom Excel (baris pertama = header):</p>
        <div class="grid grid-cols-2 gap-x-4 gap-y-0.5">
          <span><strong>tipe</strong> — Pilihan Ganda / Benar Salah / Esai / Studi Kasus</span>
          <span><strong>pertanyaan</strong> — Teks soal (wajib)</span>
          <span><strong>a, b, c, d, e</strong> — Pilihan jawaban (untuk PG)</span>
          <span><strong>jawaban</strong> — Jawaban benar (A/B/C atau teks)</span>
          <span><strong>skor</strong> — Angka skor default</span>
          <span><strong>kesulitan</strong> — Mudah / Sedang / Sulit</span>
          <span><strong>penjelasan</strong> — Pembahasan (opsional)</span>
          <span><strong>tags</strong> — Tag dipisah koma (opsional)</span>
        </div>
      </div>

      <!-- Download template -->
      <button
        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 border-2 border-dashed border-gray-300 hover:border-blue-400 hover:bg-blue-50 text-gray-600 hover:text-blue-600 text-sm rounded-lg transition-colors"
        @click="downloadTemplate">
        <ArrowDownTrayIcon class="w-4 h-4" />
        Download Template Excel
      </button>

      <!-- Upload file -->
      <div>
        <label class="text-xs font-medium text-gray-700 block mb-1">Pilih File Excel</label>
        <input
          type="file" accept=".xlsx,.xls,.csv"
          class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-200 rounded-lg cursor-pointer"
          @change="onFileChange" />
        <p class="text-xs text-gray-400 mt-1">Format: .xlsx, .xls, .csv — Maks 5MB</p>
      </div>

      <!-- Hasil import -->
      <div v-if="importResult" :class="['p-3 rounded-lg text-sm', importResult.skipped > 0 ? 'bg-yellow-50 border border-yellow-200' : 'bg-green-50 border border-green-200']">
        <p :class="importResult.skipped > 0 ? 'font-medium text-yellow-800' : 'font-medium text-green-800'">{{ importResult.message }}</p>
        <div v-if="importResult.errors?.length" class="mt-2 space-y-0.5">
          <p class="text-xs text-red-600 font-medium">Detail error:</p>
          <p v-for="(err, i) in importResult.errors" :key="i" class="text-xs text-red-500">{{ err }}</p>
        </div>
      </div>
    </div>

    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="importModal = false">Tutup</button>
      <button
        :disabled="importing || !importFile"
        class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-300 text-white text-sm font-medium rounded-lg flex items-center gap-1.5"
        @click="doImport">
        <ArrowUpTrayIcon class="w-4 h-4" />
        {{ importing ? 'Mengimport...' : 'Import Soal' }}
      </button>
    </template>
  </BaseModal>
</template>
