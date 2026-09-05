<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ArrowLeftIcon, PlusIcon, PencilIcon, TrashIcon, PlayIcon, BookOpenIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const loading = ref(true)
const exam = ref<any>(null)

// Question modal
const qModal = ref(false)
const editingQId = ref<number | null>(null)
const savingQ = ref(false)
const qForm = reactive({
  type: 'PILIHAN_GANDA' as string,
  question: '',
  options: ['', '', '', ''] as string[],
  correct_answer: '',
  score: 10,
  explanation: '',
})

const questionTypes = [
  { value: 'PILIHAN_GANDA', label: 'Pilihan Ganda' },
  { value: 'BENAR_SALAH', label: 'Benar / Salah' },
  { value: 'ESAI', label: 'Esai' },
  { value: 'STUDI_KASUS', label: 'Studi Kasus' },
  { value: 'UPLOAD_FILE', label: 'Upload File' },
]

const totalScore = computed(() => exam.value?.total_score ?? exam.value?.questions?.reduce((s: number, q: any) => s + (q.score || 0), 0) ?? 0)

/** Konversi ISO datetime ke format datetime-local (lokal, bukan UTC) */
function toLocalDatetime(dt: string | null): string {
  if (!dt) return ''
  const d = new Date(dt)
  if (isNaN(d.getTime())) return ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** Format datetime untuk tampilan */
function fmtDatetime(dt: string | null): string {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/exams/${route.params.id}`)
    exam.value = data
  } finally { loading.value = false }
})

function openAddQuestion() {
  editingQId.value = null
  Object.assign(qForm, { type: 'PILIHAN_GANDA', question: '', options: ['', '', '', ''], correct_answer: '', score: 10, explanation: '' })
  qModal.value = true
}

function openEditQuestion(q: any) {
  editingQId.value = q.id
  Object.assign(qForm, {
    type: q.type,
    question: q.question,
    options: q.options?.length ? [...q.options] : ['', '', '', ''],
    correct_answer: q.correct_answer ?? '',
    score: q.score,
    explanation: q.explanation ?? '',
  })
  qModal.value = true
}

function addOption() { qForm.options.push('') }
function removeOption(i: number) { if (qForm.options.length > 2) qForm.options.splice(i, 1) }

async function saveQuestion() {
  savingQ.value = true
  try {
    const payload: any = {
      type: qForm.type,
      question: qForm.question,
      correct_answer: qForm.correct_answer || null,
      score: qForm.score,
      explanation: qForm.explanation || null,
    }
    if (['PILIHAN_GANDA', 'BENAR_SALAH'].includes(qForm.type)) {
      payload.options = qForm.type === 'BENAR_SALAH' ? ['Benar', 'Salah'] : qForm.options.filter(o => o.trim())
    }

    if (editingQId.value) {
      await api.put(`/exams/${exam.value.id}/questions/${editingQId.value}`, payload)
      toast.success('Soal berhasil diupdate.')
    } else {
      await api.post(`/exams/${exam.value.id}/questions`, payload)
      toast.success('Soal berhasil ditambahkan.')
    }
    qModal.value = false
    // Reload
    const { data } = await api.get(`/exams/${route.params.id}`)
    exam.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { savingQ.value = false }
}

async function deleteQuestion(q: any) {
  if (!confirm('Hapus soal ini?')) return
  try {
    await api.delete(`/exams/${exam.value.id}/questions/${q.id}`)
    toast.success('Soal dihapus.')
    exam.value.questions = exam.value.questions.filter((x: any) => x.id !== q.id)
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// === BANK SOAL ===
const bankModal       = ref(false)
const banks           = ref<any[]>([])
const loadingBanks    = ref(false)
const selectedBank    = ref<any>(null)
const bankItems       = ref<any[]>([])
const loadingItems    = ref(false)
const selectedItems   = ref<Set<number>>(new Set())
const bankSearch      = ref('')
const itemSearch      = ref('')
const importingBank   = ref(false)

const filteredBankItems = computed(() => {
  if (!itemSearch.value.trim()) return bankItems.value
  const q = itemSearch.value.toLowerCase()
  return bankItems.value.filter((item: any) =>
    item.question?.toLowerCase().includes(q) ||
    item.difficulty?.toLowerCase().includes(q) ||
    item.tags?.some((t: string) => t.toLowerCase().includes(q))
  )
})

async function openBankModal() {
  bankModal.value = true
  selectedBank.value = null
  bankItems.value = []
  selectedItems.value = new Set()
  bankSearch.value = ''
  itemSearch.value = ''
  loadingBanks.value = true
  try {
    // Filter bank soal berdasarkan course ujian ini
    const courseId = exam.value?.class_?.course?.id
    const params: any = {}
    if (courseId) params.course_id = courseId
    const { data } = await api.get('/question-banks', { params })
    banks.value = data.data ?? data
  } catch (e: any) {
    toast.error('Gagal memuat bank soal.')
  } finally { loadingBanks.value = false }
}

async function selectBank(bank: any) {
  selectedBank.value = bank
  selectedItems.value = new Set()
  itemSearch.value = ''
  loadingItems.value = true
  try {
    const { data } = await api.get(`/question-banks/${bank.id}`)
    bankItems.value = data.items ?? []
  } catch (e: any) {
    toast.error('Gagal memuat soal.')
  } finally { loadingItems.value = false }
}

function toggleItem(id: number) {
  if (selectedItems.value.has(id)) {
    selectedItems.value.delete(id)
  } else {
    selectedItems.value.add(id)
  }
}

function toggleAll() {
  if (selectedItems.value.size === filteredBankItems.value.length) {
    selectedItems.value = new Set()
  } else {
    selectedItems.value = new Set(filteredBankItems.value.map((i: any) => i.id))
  }
}

async function importFromBank() {
  if (selectedItems.value.size === 0) { toast.warning('Pilih minimal 1 soal.'); return }
  importingBank.value = true
  try {
    const { data } = await api.post(
      `/question-banks/${selectedBank.value.id}/import-to-exam`,
      { exam_id: exam.value.id, item_ids: Array.from(selectedItems.value) }
    )
    toast.success(data.message ?? 'Soal berhasil diimport.')
    bankModal.value = false
    // Reload exam
    const { data: fresh } = await api.get(`/exams/${route.params.id}`)
    exam.value = fresh
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal import.')
  } finally { importingBank.value = false }
}

const difficultyColor: Record<string, string> = {
  MUDAH: 'bg-green-100 text-green-700',
  SEDANG: 'bg-yellow-100 text-yellow-700',
  SULIT: 'bg-red-100 text-red-700',
}

async function publishExam() {
  if (!confirm('Publish ujian ini? Mahasiswa akan bisa mengakses.')) return
  try {
    await api.put(`/exams/${exam.value.id}`, { status: 'PUBLISHED' })
    exam.value.status = 'PUBLISHED'
    toast.success('Ujian berhasil dipublish.')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function finishExam() {
  if (!confirm('Selesaikan ujian? Mahasiswa tidak bisa lagi mengerjakan.')) return
  try {
    await api.put(`/exams/${exam.value.id}`, { status: 'FINISHED' })
    exam.value.status = 'FINISHED'
    toast.success('Ujian selesai.')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

const statusColor: Record<string, string> = { DRAFT: 'bg-gray-100 text-gray-600', PUBLISHED: 'bg-green-100 text-green-700', ONGOING: 'bg-blue-100 text-blue-700', FINISHED: 'bg-purple-100 text-purple-700' }
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="exam" class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div class="flex-1">
        <div class="flex items-center gap-2">
          <span class="text-xs font-semibold px-2 py-0.5 rounded bg-indigo-100 text-indigo-700">{{ exam.type }}</span>
          <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusColor[exam.status]]">{{ exam.status }}</span>
          <span v-if="exam.is_online" class="text-xs text-green-600">● Online</span>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mt-1">{{ exam.title }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ exam.class_?.course?.name }} · {{ exam.class_?.course?.code }}</p>
      </div>
    </div>

    <!-- Info Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="bg-white rounded-lg border border-gray-200 p-3">
        <p class="text-xs text-gray-400">Durasi</p>
        <p class="font-bold text-gray-800">{{ exam.duration_minutes }} menit</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-200 p-3">
        <p class="text-xs text-gray-400">Jumlah Soal</p>
        <p class="font-bold text-gray-800">{{ exam.questions?.length ?? 0 }}</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-200 p-3">
        <p class="text-xs text-gray-400">Total Skor</p>
        <p class="font-bold text-gray-800">{{ totalScore }}</p>
      </div>
      <div class="bg-white rounded-lg border border-gray-200 p-3">
        <p class="text-xs text-gray-400">Token</p>
        <p class="font-mono font-bold text-lg text-blue-700">{{ exam.token }}</p>
      </div>
    </div>

    <!-- Settings -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-4 text-xs text-gray-600">
      <span>Acak Soal: <strong>{{ exam.shuffle_questions ? 'Ya' : 'Tidak' }}</strong></span>
      <span>Acak Pilihan: <strong>{{ exam.shuffle_options ? 'Ya' : 'Tidak' }}</strong></span>
      <span>Tampil Nilai: <strong>{{ exam.show_score ? 'Ya' : 'Tidak' }}</strong></span>
      <span v-if="exam.start_time">Mulai: <strong>{{ fmtDatetime(exam.start_time) }}</strong></span>
      <span v-if="exam.end_time">Selesai: <strong>{{ fmtDatetime(exam.end_time) }}</strong></span>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2">
      <button v-if="exam.status === 'DRAFT'" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg flex items-center gap-1.5" @click="publishExam">
        <PlayIcon class="w-4 h-4" /> Publish Ujian
      </button>
      <button v-if="exam.status === 'PUBLISHED' || exam.status === 'ONGOING'" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg" @click="finishExam">
        Selesaikan Ujian
      </button>
      <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg flex items-center gap-1.5"
        @click="router.push(`/ujian/${exam.id}/results`)">
        Lihat Hasil Ujian →
      </button>
    </div>

    <!-- Questions List -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-800">Daftar Soal</h2>
        <div class="flex items-center gap-2">
          <button class="flex items-center gap-1.5 px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded-lg" @click="openBankModal">
            <BookOpenIcon class="w-3.5 h-3.5" /> Ambil dari Bank Soal
          </button>
          <button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openAddQuestion">
            <PlusIcon class="w-3.5 h-3.5" /> Tambah Soal
          </button>
        </div>
      </div>

      <div v-if="!exam.questions?.length" class="text-center py-8 text-gray-400 text-sm">Belum ada soal. Klik "Tambah Soal" untuk mulai.</div>
      <div v-else class="space-y-3">
        <div v-for="(q, i) in exam.questions" :key="q.id" class="p-4 border border-gray-100 rounded-lg hover:border-blue-200 transition-colors">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-bold text-gray-400">#{{ Number(i) + 1 }}</span>
                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">{{ q.type.replace('_', ' ') }}</span>
                <span class="text-xs text-blue-600 font-medium">{{ q.score }} poin</span>
              </div>
              <p class="text-sm text-gray-800 whitespace-pre-line">{{ q.question }}</p>
              <!-- Options -->
              <div v-if="q.options?.length" class="mt-2 space-y-1">
                <div v-for="(opt, oi) in q.options" :key="oi" class="flex items-center gap-2 text-xs">
                  <span :class="['w-5 h-5 flex items-center justify-center rounded-full text-xs font-bold shrink-0',
                    q.correct_answer === opt ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600']">
                    {{ String.fromCharCode(65 + Number(oi)) }}
                  </span>
                  <span :class="q.correct_answer === opt ? 'text-green-700 font-medium' : 'text-gray-600'">{{ opt }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <button class="p-1.5 rounded text-blue-600 hover:bg-blue-50" @click="openEditQuestion(q)"><PencilIcon class="w-4 h-4" /></button>
              <button class="p-1.5 rounded text-red-500 hover:bg-red-50" @click="deleteQuestion(q)"><TrashIcon class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Tambah/Edit Soal -->
  <BaseModal :open="qModal" :title="editingQId ? 'Edit Soal' : 'Tambah Soal'" size="xl" @close="qModal = false">
    <form class="space-y-4" @submit.prevent="saveQuestion">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-xs font-medium text-gray-700">Tipe Soal <span class="text-red-500">*</span></label>
          <select v-model="qForm.type" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
            <option v-for="qt in questionTypes" :key="qt.value" :value="qt.value">{{ qt.label }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Skor <span class="text-red-500">*</span></label>
          <input v-model.number="qForm.score" type="number" min="1" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
      </div>

      <div>
        <label class="text-xs font-medium text-gray-700">Pertanyaan <span class="text-red-500">*</span></label>
        <textarea v-model="qForm.question" required rows="3" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" placeholder="Tuliskan soal di sini..." />
      </div>

      <!-- Pilihan (PG only) -->
      <div v-if="qForm.type === 'PILIHAN_GANDA'">
        <label class="text-xs font-medium text-gray-700 mb-2 block">Pilihan Jawaban</label>
        <div class="space-y-2">
          <div v-for="(opt, i) in qForm.options" :key="i" class="flex items-center gap-2">
            <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-200 text-gray-600 text-xs font-bold shrink-0">{{ String.fromCharCode(65 + i) }}</span>
            <input v-model="qForm.options[i]" class="flex-1 px-3 py-1.5 border rounded-lg text-sm" :placeholder="`Pilihan ${String.fromCharCode(65 + i)}`" />
            <button v-if="qForm.options.length > 2" type="button" class="p-1 text-red-500 hover:bg-red-50 rounded" @click="removeOption(i)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </div>
        <button type="button" class="mt-2 text-xs text-blue-600 hover:text-blue-700 font-medium" @click="addOption">+ Tambah Pilihan</button>
      </div>

      <!-- Benar/Salah -->
      <div v-if="qForm.type === 'BENAR_SALAH'">
        <label class="text-xs font-medium text-gray-700">Jawaban Benar</label>
        <select v-model="qForm.correct_answer" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
          <option value="Benar">Benar</option>
          <option value="Salah">Salah</option>
        </select>
      </div>

      <!-- Jawaban benar PG -->
      <div v-if="qForm.type === 'PILIHAN_GANDA'">
        <label class="text-xs font-medium text-gray-700">Jawaban Benar</label>
        <select v-model="qForm.correct_answer" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
          <option value="">-- Pilih jawaban benar --</option>
          <option v-for="(opt, i) in qForm.options.filter(o => o.trim())" :key="i" :value="opt">{{ String.fromCharCode(65 + i) }}. {{ opt }}</option>
        </select>
      </div>

      <div>
        <label class="text-xs font-medium text-gray-700">Penjelasan (opsional)</label>
        <textarea v-model="qForm.explanation" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" placeholder="Penjelasan jawaban..." />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="qModal = false">Batal</button>
      <button :disabled="savingQ" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveQuestion">{{ savingQ ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>

  <!-- Modal Bank Soal -->
  <BaseModal :open="bankModal" title="Ambil Soal dari Bank Soal" size="xl" @close="bankModal = false">
    <div class="flex gap-4 min-h-96">

      <!-- Panel Kiri: Daftar Bank -->
      <div class="w-48 shrink-0 space-y-1 border-r border-gray-200 pr-3">
        <p class="text-xs font-semibold text-gray-500 mb-2">BANK SOAL</p>
        <div v-if="loadingBanks" class="text-xs text-gray-400 py-4 text-center">Memuat...</div>
        <div v-else-if="!banks.length" class="text-xs text-gray-400 py-4 text-center">Tidak ada bank soal.</div>
        <button
          v-for="b in banks" :key="b.id"
          :class="['w-full text-left px-2.5 py-2 rounded-lg text-xs transition-colors',
            selectedBank?.id === b.id ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-700']"
          @click="selectBank(b)">
          <p class="font-medium truncate">{{ b.title }}</p>
          <p :class="selectedBank?.id === b.id ? 'text-blue-200' : 'text-gray-400'" class="text-[10px]">{{ b.items_count ?? 0 }} soal · {{ b.course?.code }}</p>
        </button>
      </div>

      <!-- Panel Kanan: Daftar Soal -->
      <div class="flex-1 min-w-0 space-y-2">
        <div v-if="!selectedBank" class="flex items-center justify-center h-full text-sm text-gray-400">
          ← Pilih bank soal di kiri
        </div>
        <template v-else>
          <!-- Search + select all -->
          <div class="flex items-center gap-2">
            <div class="relative flex-1">
              <MagnifyingGlassIcon class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
              <input v-model="itemSearch" placeholder="Cari soal..." class="w-full pl-8 pr-3 py-1.5 text-xs border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <button class="text-xs text-blue-600 hover:text-blue-700 whitespace-nowrap" @click="toggleAll">
              {{ selectedItems.size === filteredBankItems.length && filteredBankItems.length > 0 ? 'Batal Semua' : 'Pilih Semua' }}
            </button>
            <span class="text-xs text-gray-400 whitespace-nowrap">{{ selectedItems.size }} dipilih</span>
          </div>

          <div v-if="loadingItems" class="text-xs text-gray-400 py-6 text-center">Memuat soal...</div>
          <div v-else-if="!filteredBankItems.length" class="text-xs text-gray-400 py-6 text-center">Tidak ada soal.</div>
          <div v-else class="space-y-2 max-h-80 overflow-y-auto pr-1">
            <div v-for="item in filteredBankItems" :key="item.id"
              :class="['p-3 border rounded-xl cursor-pointer transition-colors',
                selectedItems.has(item.id) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300']"
              @click="toggleItem(item.id)">
              <div class="flex items-start gap-2">
                <!-- Checkbox -->
                <div :class="['w-4 h-4 rounded border-2 shrink-0 mt-0.5 flex items-center justify-center',
                  selectedItems.has(item.id) ? 'border-blue-600 bg-blue-600' : 'border-gray-300']">
                  <svg v-if="selectedItems.has(item.id)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                    <span class="text-[10px] px-1.5 py-0.5 bg-gray-100 rounded text-gray-600">{{ item.type?.replace(/_/g, ' ') }}</span>
                    <span v-if="item.difficulty" :class="['text-[10px] px-1.5 py-0.5 rounded font-medium', difficultyColor[item.difficulty]]">{{ item.difficulty }}</span>
                    <span class="text-[10px] text-blue-600 font-medium ml-auto">{{ item.default_score ?? 0 }} poin</span>
                  </div>
                  <p class="text-xs text-gray-800 line-clamp-2">{{ item.question }}</p>
                  <div v-if="item.tags?.length" class="flex flex-wrap gap-1 mt-1">
                    <span v-for="tag in item.tags" :key="tag" class="text-[10px] px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded">{{ tag }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="bankModal = false">Batal</button>
      <button
        :disabled="importingBank || selectedItems.size === 0"
        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-300 text-white text-sm font-medium rounded-lg"
        @click="importFromBank">
        {{ importingBank ? 'Mengimport...' : `Import ${selectedItems.size} Soal` }}
      </button>
    </template>
  </BaseModal>
</template>
