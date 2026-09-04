<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const toast = useToast()
const auth = useAuthStore()
const isMahasiswa = auth.user?.roles?.includes('MAHASISWA')
const isAcademicAdmin = computed(() => auth.hasRole(['SUPER_ADMIN', 'ADMIN_AKADEMIK']))

const activeTab = ref<'khs' | 'transkrip'>('khs')
const loading = ref(false)

// Filters
const semesters = ref<any[]>([])
const filterSemester = ref('')
const searchStudent = ref('')
const studentResults = ref<any[]>([])
const selectedStudent = ref<any>(null)
const studentId = ref<number | null>(null)

// Data
const khsData = ref<any>(null)
const transcriptData = ref<any>(null)
const downloading = ref<string | null>(null)

onMounted(async () => {
  const { data } = await api.get('/semesters', { params: { per_page: 50 } })
  semesters.value = data.data ?? data
  const active = semesters.value.find((s: any) => s.is_active)
  if (active) filterSemester.value = active.id

  // Mahasiswa: otomatis pilih diri sendiri
  if (isMahasiswa && (auth.user as any)?.student_id) {
    studentId.value = (auth.user as any).student_id
    loadKhs()
    loadTranscript()
  }
})

let sTimeout: any
function onSearchStudent() {
  clearTimeout(sTimeout)
  if (searchStudent.value.length < 2) { studentResults.value = []; return }
  sTimeout = setTimeout(async () => {
    const { data } = await api.get('/students', { params: { search: searchStudent.value, per_page: 10 } })
    studentResults.value = data.data ?? data
  }, 300)
}

function selectStudent(s: any) {
  selectedStudent.value = s; studentId.value = s.id
  searchStudent.value = `${s.nim} - ${s.name}`; studentResults.value = []
  loadKhs(); loadTranscript()
}

async function loadKhs() {
  if (!studentId.value || !filterSemester.value) return
  loading.value = true
  try {
    const { data } = await api.get('/grades/khs', { params: { student_id: studentId.value, semester_id: filterSemester.value } })
    khsData.value = data
  } catch { khsData.value = null }
  finally { loading.value = false }
}

async function loadTranscript() {
  if (!studentId.value) return
  loading.value = true
  try {
    const { data } = await api.get('/grades/transcript', { params: { student_id: studentId.value } })
    transcriptData.value = data
  } catch { transcriptData.value = null }
  finally { loading.value = false }
}

function downloadBlob(blobData: BlobPart, filename: string, mimeType: string) {
  const url = URL.createObjectURL(new Blob([blobData], { type: mimeType }))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

async function downloadKhsPdf() {
  if (!studentId.value || !filterSemester.value) return
  downloading.value = 'khs'
  try {
    const response = await api.get('/grades/khs/pdf', {
      params: { student_id: studentId.value, semester_id: filterSemester.value },
      responseType: 'blob',
    })
    downloadBlob(response.data, `KHS-${selectedStudent.value?.nim ?? studentId.value}.pdf`, 'application/pdf')
  } catch { toast.error('Gagal mengunduh KHS PDF.') }
  finally { downloading.value = null }
}

async function downloadTranscript(format: 'pdf' | 'excel') {
  if (!studentId.value) return
  downloading.value = `transcript-${format}`
  try {
    const response = await api.get(`/grades/transcript/${format === 'excel' ? 'excel' : 'pdf'}`, {
      params: { student_id: studentId.value },
      responseType: 'blob',
    })
    const extension = format === 'excel' ? 'xlsx' : 'pdf'
    const mimeType = format === 'excel'
      ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      : 'application/pdf'
    downloadBlob(response.data, `Transkrip-${selectedStudent.value?.nim ?? studentId.value}.${extension}`, mimeType)
  } catch { toast.error(`Gagal mengunduh Transkrip ${format === 'excel' ? 'Excel' : 'PDF'}.`) }
  finally { downloading.value = null }
}

function switchTab(tab: 'khs' | 'transkrip') {
  activeTab.value = tab
  if (tab === 'khs') loadKhs()
  else loadTranscript()
}

const gradeColor = (letter: string) => {
  if (['A', 'A-'].includes(letter)) return 'text-green-700'
  if (['B+', 'B', 'B-'].includes(letter)) return 'text-blue-700'
  if (['C+', 'C'].includes(letter)) return 'text-yellow-700'
  if (['D', 'E'].includes(letter)) return 'text-red-600'
  return 'text-gray-700'
}

// Group transcript by semester
const groupedTranscript = computed(() => {
  if (!transcriptData.value?.grades) return []
  const groups: Record<string, any[]> = {}
  transcriptData.value.grades.forEach((g: any) => {
    const sem = g.semester?.name ?? 'Lainnya'
    if (!groups[sem]) groups[sem] = []
    groups[sem].push(g)
  })
  return Object.entries(groups).map(([semester, grades]) => ({ semester, grades }))
})
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-xl font-bold text-gray-900">KHS & Transkrip</h1>
      <p class="text-sm text-gray-500 mt-0.5">Kartu Hasil Studi dan Transkrip Akademik mahasiswa</p>
    </div>

    <!-- Student selector (admin/dosen) -->
    <div v-if="!isMahasiswa" class="relative max-w-md">
      <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Mahasiswa</label>
      <input v-model="searchStudent" placeholder="Ketik NIM atau nama..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" @input="onSearchStudent" />
      <div v-if="studentResults.length" class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
        <button v-for="s in studentResults" :key="s.id" class="w-full text-left px-3 py-2 hover:bg-blue-50 text-sm" @click="selectStudent(s)">
          <span class="font-mono text-xs text-gray-500">{{ s.nim }}</span> — {{ s.name }} <span class="text-xs text-gray-400">({{ s.study_program?.code }})</span>
        </button>
      </div>
    </div>

    <!-- Student info -->
    <div v-if="selectedStudent" class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
      <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold">{{ selectedStudent.name?.charAt(0) }}</div>
      <div>
        <p class="font-semibold text-gray-900">{{ selectedStudent.name }}</p>
        <p class="text-xs text-gray-500">{{ selectedStudent.nim }} · {{ selectedStudent.study_program?.name }}</p>
      </div>
      <div v-if="transcriptData" class="ml-auto text-right">
        <p class="text-2xl font-bold text-blue-700">{{ transcriptData.ipk }}</p>
        <p class="text-xs text-gray-500">IPK · {{ transcriptData.total_credits }} SKS</p>
      </div>
    </div>

    <!-- Tabs -->
    <div v-if="studentId" class="flex gap-1 border-b border-gray-200">
      <button :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px', activeTab === 'khs' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500']" @click="switchTab('khs')">KHS (Per Semester)</button>
      <button :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px', activeTab === 'transkrip' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500']" @click="switchTab('transkrip')">Transkrip Lengkap</button>
    </div>

    <!-- TAB: KHS -->
    <div v-if="activeTab === 'khs' && studentId">
      <div class="mb-4 flex flex-wrap items-center gap-2">
        <select v-model="filterSemester" class="px-3 py-2 rounded-lg border border-gray-300 text-sm" @change="loadKhs">
          <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <button :disabled="downloading === 'khs'" class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-medium" @click="downloadKhsPdf">
          {{ downloading === 'khs' ? 'Menyiapkan...' : 'Cetak KHS PDF' }}
        </button>
      </div>

      <div v-if="loading" class="text-center py-8 text-gray-400">Memuat...</div>
      <div v-else-if="!khsData?.grades?.length" class="text-center py-8 text-gray-400 text-sm">Belum ada nilai untuk semester ini.</div>
      <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
              <th class="px-4 py-2">Kode</th><th class="px-4 py-2">Mata Kuliah</th>
              <th class="px-4 py-2 text-center">SKS</th><th class="px-4 py-2 text-center">Nilai</th>
              <th class="px-4 py-2 text-center">Huruf</th><th class="px-4 py-2 text-center">Bobot</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in khsData.grades" :key="g.id" class="border-t border-gray-100">
              <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ g.course?.code }}</td>
              <td class="px-4 py-2 text-gray-800">{{ g.course?.name }}</td>
              <td class="px-4 py-2 text-center">{{ g.course?.credits }}</td>
              <td class="px-4 py-2 text-center font-medium">{{ g.final_score }}</td>
              <td class="px-4 py-2 text-center font-bold" :class="gradeColor(g.letter_grade)">{{ g.letter_grade ?? '-' }}</td>
              <td class="px-4 py-2 text-center text-gray-600">{{ g.grade_point ?? '-' }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-blue-50 border-t-2 border-blue-200">
              <td colspan="2" class="px-4 py-3 font-semibold text-gray-800">Total</td>
              <td class="px-4 py-3 text-center font-bold text-blue-700">{{ khsData.total_credits }}</td>
              <td colspan="2" />
              <td class="px-4 py-3 text-center font-bold text-xl text-blue-700">{{ khsData.ips }}</td>
            </tr>
          </tfoot>
        </table>
        <div class="px-4 py-3 bg-blue-50 text-center">
          <span class="text-sm font-semibold text-blue-800">IPS: {{ khsData.ips }}</span>
        </div>
      </div>
    </div>

    <!-- TAB: Transkrip -->
    <div v-if="activeTab === 'transkrip' && studentId">
      <div class="mb-4 flex flex-wrap gap-2">
        <button :disabled="downloading === 'transcript-pdf'" class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-medium" @click="downloadTranscript('pdf')">
          {{ downloading === 'transcript-pdf' ? 'Menyiapkan...' : 'Cetak Transkrip PDF' }}
        </button>
        <button v-if="isAcademicAdmin" :disabled="downloading === 'transcript-excel'" class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-sm font-medium" @click="downloadTranscript('excel')">
          {{ downloading === 'transcript-excel' ? 'Menyiapkan...' : 'Download Transkrip Excel' }}
        </button>
      </div>
      <div v-if="loading" class="text-center py-8 text-gray-400">Memuat...</div>
      <div v-else-if="!transcriptData?.grades?.length" class="text-center py-8 text-gray-400 text-sm">Belum ada data transkrip.</div>
      <div v-else class="space-y-4">
        <div v-for="group in groupedTranscript" :key="group.semester" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
            <span class="text-sm font-semibold text-gray-700">{{ group.semester }}</span>
          </div>
          <table class="w-full text-sm">
            <tbody>
              <tr v-for="g in group.grades" :key="g.id" class="border-t border-gray-50">
                <td class="px-4 py-2 font-mono text-xs text-gray-500 w-24">{{ g.course?.code }}</td>
                <td class="px-4 py-2 text-gray-800">{{ g.course?.name }}</td>
                <td class="px-4 py-2 text-center w-14">{{ g.course?.credits }}</td>
                <td class="px-4 py-2 text-center w-14 font-bold" :class="gradeColor(g.letter_grade)">{{ g.letter_grade ?? '-' }}</td>
                <td class="px-4 py-2 text-center w-14 text-gray-600">{{ g.grade_point ?? '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Summary -->
        <div class="bg-blue-50 rounded-xl border border-blue-200 p-5 text-center">
          <p class="text-sm text-blue-600">Indeks Prestasi Kumulatif (IPK)</p>
          <p class="text-4xl font-bold text-blue-700 mt-1">{{ transcriptData.ipk }}</p>
          <p class="text-sm text-blue-500 mt-1">Total SKS: {{ transcriptData.total_credits }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
