<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ArrowLeftIcon, EyeIcon, PencilIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()

const loading     = ref(true)
const data        = ref<any>(null)
const search      = ref('')

// Detail modal per mahasiswa
const detailModal  = ref(false)
const detailData   = ref<any>(null)
const loadingDetail = ref(false)

// Grade modal
const gradeModal   = ref(false)
const gradingAnswer = ref<any>(null)
const gradeScore   = ref(0)
const savingGrade  = ref(false)

const examId = route.params.id

onMounted(async () => {
  try {
    const { data: d } = await api.get(`/exams/${examId}/results`)
    data.value = d
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal memuat hasil ujian.')
  } finally { loading.value = false }
})

const filteredSessions = computed(() => {
  if (!data.value?.sessions) return []
  const q = search.value.toLowerCase()
  if (!q) return data.value.sessions
  return data.value.sessions.filter((s: any) =>
    s.name?.toLowerCase().includes(q) || s.nim?.toLowerCase().includes(q)
  )
})

function fmtDuration(sec: number | null): string {
  if (!sec) return '-'
  const m = Math.floor(sec / 60), s = sec % 60
  return `${m}m ${s}s`
}

function fmtDatetime(dt: string | null): string {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function scoreColor(score: number, max: number): string {
  if (!max) return 'text-gray-500'
  const pct = (score / max) * 100
  if (pct >= 75) return 'text-green-600'
  if (pct >= 50) return 'text-yellow-600'
  return 'text-red-600'
}

async function openDetail(session: any) {
  detailModal.value = true
  loadingDetail.value = true
  try {
    const { data: d } = await api.get(`/exams/${examId}/results/${session.student_id}`)
    detailData.value = d
  } catch (e: any) {
    toast.error('Gagal memuat detail jawaban.')
  } finally { loadingDetail.value = false }
}

function openGrade(answer: any) {
  gradingAnswer.value = answer
  gradeScore.value = answer.score ?? 0
  gradeModal.value = true
}

async function saveGrade() {
  if (!gradingAnswer.value || !detailData.value) return
  savingGrade.value = true
  try {
    const { data: d } = await api.post(
      `/exams/${examId}/results/${detailData.value.student.id}/grade`,
      { question_id: gradingAnswer.value.question_id, score: gradeScore.value }
    )
    toast.success('Nilai berhasil disimpan.')
    gradeModal.value = false
    // Update lokal
    gradingAnswer.value.score = gradeScore.value
    gradingAnswer.value.manual_score = gradeScore.value
    detailData.value.total_score = d.total_score
    // Update di list
    const s = data.value?.sessions?.find((s: any) => s.student_id === detailData.value.student.id)
    if (s) s.total_score = d.total_score
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal menyimpan nilai.')
  } finally { savingGrade.value = false }
}

const needsManualGrading = (answers: any[]) =>
  answers?.some((a: any) => ['ESAI', 'STUDI_KASUS', 'UPLOAD_FILE'].includes(a.question_type) && a.score === null)
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="data" class="space-y-5 max-w-5xl mx-auto">

    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" @click="router.back()">
        <ArrowLeftIcon class="w-5 h-5" />
      </button>
      <div class="flex-1">
        <h1 class="text-lg font-bold text-gray-900">Hasil Ujian</h1>
        <p class="text-sm text-gray-500">{{ data.exam?.title }} · {{ data.exam?.type }}</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-xs text-gray-400 mb-1">Peserta</p>
        <p class="text-2xl font-bold text-gray-800">{{ data.stats.total }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-xs text-gray-400 mb-1">Sudah Submit</p>
        <p class="text-2xl font-bold text-green-600">{{ data.stats.submitted }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-xs text-gray-400 mb-1">Rata-rata Skor</p>
        <p class="text-2xl font-bold text-blue-600">{{ data.stats.avg_score }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-xs text-gray-400 mb-1">Skor Maksimal</p>
        <p class="text-2xl font-bold text-gray-700">{{ data.max_score }}</p>
      </div>
    </div>

    <!-- Search -->
    <div class="flex items-center gap-3">
      <input v-model="search" placeholder="Cari nama atau NIM..."
        class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      <span class="text-xs text-gray-400">{{ filteredSessions.length }} peserta</span>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">#</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Nama / NIM</th>
            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Status</th>
            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Skor</th>
            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Durasi</th>
            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Tab Switch</th>
            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Selesai</th>
            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="!filteredSessions.length">
            <td colspan="8" class="text-center py-10 text-gray-400 text-sm">Belum ada peserta yang mengerjakan.</td>
          </tr>
          <tr v-for="(s, i) in filteredSessions" :key="s.session_id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-400 text-xs">{{ Number(i) + 1 }}</td>
            <td class="px-4 py-3">
              <p class="font-medium text-gray-900">{{ s.name }}</p>
              <p class="text-xs text-gray-400">{{ s.nim }}</p>
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="['px-2 py-0.5 rounded-full text-xs font-medium',
                s.status === 'SUBMITTED'   ? 'bg-green-100 text-green-700' :
                s.status === 'IN_PROGRESS' ? 'bg-blue-100 text-blue-700' :
                'bg-gray-100 text-gray-600']">{{ s.status }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <span v-if="s.status === 'SUBMITTED'" :class="['font-bold text-base', scoreColor(s.total_score ?? 0, data.max_score)]">
                {{ s.total_score ?? 0 }}
              </span>
              <span v-else class="text-gray-300">-</span>
            </td>
            <td class="px-4 py-3 text-center text-xs text-gray-500">{{ fmtDuration(s.duration_sec) }}</td>
            <td class="px-4 py-3 text-center">
              <span v-if="s.tab_switches > 0" class="text-xs font-medium text-red-500">{{ s.tab_switches }}x ⚠</span>
              <span v-else class="text-xs text-gray-300">0</span>
            </td>
            <td class="px-4 py-3 text-center text-xs text-gray-500">{{ fmtDatetime(s.finished_at) }}</td>
            <td class="px-4 py-3 text-center">
              <button v-if="s.status === 'SUBMITTED'"
                class="px-2.5 py-1.5 text-xs bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg font-medium inline-flex items-center gap-1"
                @click="openDetail(s)">
                <EyeIcon class="w-3.5 h-3.5" /> Lihat
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal Detail Jawaban -->
  <BaseModal :open="detailModal" title="Detail Jawaban Mahasiswa" size="xl" @close="detailModal = false">
    <div v-if="loadingDetail" class="py-10 text-center text-gray-400 text-sm">Memuat...</div>
    <div v-else-if="detailData" class="space-y-4">
      <!-- Info mahasiswa -->
      <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
        <div>
          <p class="font-semibold text-gray-900">{{ detailData.student?.name }}</p>
          <p class="text-xs text-gray-500">{{ detailData.student?.nim }}</p>
        </div>
        <div class="text-right">
          <p class="text-xs text-gray-400">Total Skor</p>
          <p :class="['text-2xl font-bold', scoreColor(detailData.total_score ?? 0, data?.max_score)]">
            {{ detailData.total_score ?? 0 }} <span class="text-sm text-gray-400">/ {{ data?.max_score }}</span>
          </p>
        </div>
      </div>
      <!-- Perlu koreksi manual -->
      <div v-if="needsManualGrading(detailData.answers)" class="p-2.5 bg-yellow-50 border border-yellow-200 rounded-lg text-xs text-yellow-700">
        ⚠ Ada soal esai/studi kasus yang belum dinilai. Klik ikon pensil untuk memberi nilai.
      </div>
      <!-- Daftar jawaban -->
      <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
        <div v-for="(a, i) in detailData.answers" :key="a.question_id"
          class="p-4 border border-gray-200 rounded-xl">
          <div class="flex items-start justify-between gap-2 mb-2">
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-gray-400">#{{ Number(i) + 1 }}</span>
              <span class="text-xs px-1.5 py-0.5 bg-gray-100 rounded text-gray-600">{{ a.question_type?.replace(/_/g, ' ') }}</span>
              <!-- Ikon benar/salah untuk PG/BS -->
              <CheckCircleIcon v-if="a.is_correct === true" class="w-4 h-4 text-green-500" />
              <XCircleIcon v-else-if="a.is_correct === false" class="w-4 h-4 text-red-500" />
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              <span :class="['text-xs font-bold', a.score != null ? 'text-blue-600' : 'text-gray-300']">
                {{ a.score ?? '?' }} / {{ a.max_score }} poin
              </span>
              <!-- Tombol koreksi manual untuk esai -->
              <button v-if="['ESAI','STUDI_KASUS','UPLOAD_FILE'].includes(a.question_type)"
                class="p-1 rounded text-orange-500 hover:bg-orange-50" title="Beri nilai manual"
                @click="openGrade(a)">
                <PencilIcon class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
          <p class="text-sm text-gray-700 mb-2 font-medium">{{ a.question_text }}</p>
          <!-- Jawaban mahasiswa -->
          <div class="p-2.5 bg-blue-50 rounded-lg text-sm text-blue-800">
            <span class="text-xs text-blue-400 block mb-0.5">Jawaban:</span>
            {{ a.student_answer ?? '(tidak dijawab)' }}
          </div>
          <!-- Jawaban benar (jika PG/BS) -->
          <div v-if="a.correct_answer" class="mt-1.5 p-2 bg-green-50 rounded-lg text-xs text-green-700">
            ✓ Jawaban benar: <strong>{{ a.correct_answer }}</strong>
          </div>
        </div>
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="detailModal = false">Tutup</button>
    </template>
  </BaseModal>

  <!-- Modal Koreksi Manual -->
  <BaseModal :open="gradeModal" title="Koreksi Nilai Manual" @close="gradeModal = false">
    <div v-if="gradingAnswer" class="space-y-3">
      <div class="p-3 bg-gray-50 rounded-lg text-sm text-gray-700">
        <p class="text-xs text-gray-400 mb-1">Soal:</p>
        <p>{{ gradingAnswer.question_text }}</p>
      </div>
      <div class="p-3 bg-blue-50 rounded-lg text-sm text-blue-800">
        <p class="text-xs text-blue-400 mb-1">Jawaban mahasiswa:</p>
        <p>{{ gradingAnswer.student_answer ?? '(tidak dijawab)' }}</p>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-700">
          Nilai (maks: {{ gradingAnswer.max_score }} poin)
        </label>
        <input v-model.number="gradeScore" type="number" :min="0" :max="gradingAnswer.max_score"
          class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="gradeModal = false">Batal</button>
      <button :disabled="savingGrade"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg"
        @click="saveGrade">
        {{ savingGrade ? 'Menyimpan...' : 'Simpan Nilai' }}
      </button>
    </template>
  </BaseModal>
</template>
