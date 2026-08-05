<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ClockIcon, ExclamationTriangleIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const step = ref<'token' | 'exam' | 'result'>('token')
const tokenInput = ref('')
const loading = ref(false)
const examInfo = ref<any>(null)

// Exam data
const session = ref<any>(null)
const questions = ref<any[]>([])
const durationMinutes = ref(0)
const answers = ref<Record<number, string>>({})
const currentQ = ref(0)
const submitting = ref(false)
const result = ref<any>(null)

// Timer
const timeLeft = ref(0)
let timerInterval: any = null

const formattedTime = computed(() => {
  const m = Math.floor(timeLeft.value / 60)
  const s = timeLeft.value % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

const timeWarning = computed(() => timeLeft.value > 0 && timeLeft.value <= 300)
let tabSwitchCount = ref(0)

function onVisibilityChange() {
  if (document.hidden && step.value === 'exam') {
    tabSwitchCount.value++
    api.post(`/exams/${route.params.id}/tab-switch`).catch(() => {})
    toast.warning(`Perpindahan tab terdeteksi! (${tabSwitchCount.value}x)`)
  }
}

onMounted(async () => {
  document.addEventListener('visibilitychange', onVisibilityChange)
  try {
    // Load info ujian (judul, durasi, dll)
    const { data: info } = await api.get(`/exams/${route.params.id}`)
    examInfo.value = info
  } catch { /* fallback */ }

  // Cek apakah mahasiswa sudah pernah mengerjakan
  try {
    const { data: myRes } = await api.get(`/exams/${route.params.id}/my-result`)

    if (myRes.status === 'SUBMITTED') {
      // Sudah submit — langsung tampilkan hasil
      result.value = {
        total_score: myRes.total_score,
        max_score:   myRes.max_score,
        show_score:  myRes.show_score,
        answers:     myRes.answers,
        tab_switches: myRes.tab_switches,
      }
      step.value = 'result'
    } else if (myRes.status === 'IN_PROGRESS' && myRes.session) {
      // Sedang mengerjakan — tampilkan halaman token dulu, beri tahu
      toast.info('Anda memiliki sesi ujian yang belum selesai. Masukkan token untuk melanjutkan.')
    }
    // status NOT_STARTED → tampilkan halaman token normal
  } catch { /* bukan mahasiswa atau belum ada sesi */ }
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisibilityChange)
  if (timerInterval) clearInterval(timerInterval)
})

function fmtDatetime(dt: string | null): string {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function startExam() {
  if (!tokenInput.value.trim()) { toast.error('Masukkan token ujian.'); return }
  loading.value = true
  try {
    const { data } = await api.post(`/exams/${route.params.id}/start`, { token: tokenInput.value.trim().toUpperCase() })
    session.value = data.session
    questions.value = data.questions
    durationMinutes.value = data.duration_minutes
    step.value = 'exam'
    questions.value.forEach((q: any) => { answers.value[q.id] = '' })
    timeLeft.value = durationMinutes.value * 60
    timerInterval = setInterval(() => {
      timeLeft.value--
      if (timeLeft.value <= 0) { clearInterval(timerInterval); autoSubmit() }
    }, 1000)
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal memulai ujian.')
  } finally { loading.value = false }
}

async function autoSubmit() {
  submitting.value = true
  const payload = Object.entries(answers.value).map(([qId, answer]) => ({ question_id: Number(qId), answer: answer || null }))
  try {
    const { data } = await api.post(`/exams/${route.params.id}/submit-answers`, { answers: payload })
    // Setelah submit, ambil hasil lengkap dari my-result
    const { data: myRes } = await api.get(`/exams/${route.params.id}/my-result`)
    result.value = { total_score: myRes.total_score, max_score: myRes.max_score, show_score: myRes.show_score, answers: myRes.answers, tab_switches: myRes.tab_switches }
    step.value = 'result'
    toast.info('Waktu habis. Jawaban otomatis dikumpulkan.')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal auto-submit.') }
  finally { submitting.value = false }
}

async function submitExam() {
  if (!confirm('Yakin submit jawaban? Jawaban tidak dapat diubah setelah submit.')) return
  submitting.value = true
  if (timerInterval) clearInterval(timerInterval)
  const payload = Object.entries(answers.value).map(([qId, answer]) => ({ question_id: Number(qId), answer: answer || null }))
  try {
    await api.post(`/exams/${route.params.id}/submit-answers`, { answers: payload })
    // Ambil hasil lengkap dari my-result
    const { data: myRes } = await api.get(`/exams/${route.params.id}/my-result`)
    result.value = { total_score: myRes.total_score, max_score: myRes.max_score, show_score: myRes.show_score, answers: myRes.answers, tab_switches: myRes.tab_switches }
    step.value = 'result'
    toast.success('Jawaban berhasil disubmit.')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal submit.') }
  finally { submitting.value = false }
}

const answeredCount = computed(() => Object.values(answers.value).filter(a => a && a.trim()).length)
const progress = computed(() => questions.value.length > 0 ? Math.round((answeredCount.value / questions.value.length) * 100) : 0)
</script>

<template>
  <div class="max-w-4xl mx-auto">

    <!-- Step: Token Input -->
    <div v-if="step === 'token'" class="max-w-lg mx-auto mt-8 space-y-4">
      <!-- Info Ujian -->
      <div v-if="examInfo" class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-center gap-2 mb-3">
          <span class="text-xs font-semibold px-2 py-0.5 rounded bg-indigo-100 text-indigo-700">{{ examInfo.type }}</span>
          <span :class="['px-2 py-0.5 rounded-full text-xs font-medium',
            examInfo.status === 'PUBLISHED' ? 'bg-green-100 text-green-700' :
            examInfo.status === 'ONGOING'   ? 'bg-blue-100 text-blue-700' :
            examInfo.status === 'FINISHED'  ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600']">
            {{ examInfo.status }}
          </span>
          <span v-if="examInfo.is_online" class="text-xs text-green-600">● Online</span>
        </div>
        <h2 class="text-lg font-bold text-gray-900">{{ examInfo.title }}</h2>
        <p v-if="examInfo.description" class="text-sm text-gray-500 mt-1">{{ examInfo.description }}</p>
        <div class="mt-4 grid grid-cols-2 gap-3">
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-400">Durasi</p>
            <p class="font-bold text-gray-800">{{ examInfo.duration_minutes }} menit</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-400">Jumlah Soal</p>
            <p class="font-bold text-gray-800">{{ examInfo.questions_count ?? examInfo.questions?.length ?? '-' }}</p>
          </div>
          <div v-if="examInfo.start_time" class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-400">Waktu Mulai</p>
            <p class="font-semibold text-gray-800 text-sm">{{ fmtDatetime(examInfo.start_time) }}</p>
          </div>
          <div v-if="examInfo.end_time" class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-400">Waktu Selesai</p>
            <p class="font-semibold text-gray-800 text-sm">{{ fmtDatetime(examInfo.end_time) }}</p>
          </div>
        </div>
      </div>
      <!-- Token Input Card -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center">
        <div class="w-14 h-14 mx-auto rounded-full bg-blue-100 flex items-center justify-center mb-4">
          <ClockIcon class="w-7 h-7 text-blue-600" />
        </div>
        <h1 class="text-lg font-bold text-gray-900">Masukkan Token Ujian</h1>
        <p class="text-sm text-gray-500 mt-1">Minta token dari dosen pengampu untuk memulai.</p>
        <div class="mt-5">
          <input v-model="tokenInput" placeholder="Contoh: ABCD12" maxlength="10"
            class="w-full px-4 py-3 text-center text-xl font-mono tracking-widest border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase"
            @keyup.enter="startExam" />
        </div>
        <button :disabled="loading || !tokenInput.trim()"
          class="w-full mt-4 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white font-semibold rounded-lg transition-colors"
          @click="startExam">
          {{ loading ? 'Memuat soal...' : 'Mulai Mengerjakan' }}
        </button>
        <button class="w-full mt-2 py-2 text-sm text-gray-500 hover:text-gray-700" @click="router.back()">Kembali</button>
      </div>
    </div>

    <!-- Step: Exam -->
    <div v-if="step === 'exam'" class="space-y-4">
      <!-- Top bar -->
      <div class="sticky top-0 z-10 bg-white border-b border-gray-200 -mx-4 px-4 py-3 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-3">
          <span :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-bold',
            timeWarning ? 'bg-red-100 text-red-700 animate-pulse' : 'bg-gray-100 text-gray-700']">
            <ClockIcon class="w-4 h-4" />{{ formattedTime }}
          </span>
          <span class="text-xs text-gray-500">{{ answeredCount }}/{{ questions.length }} dijawab</span>
        </div>
        <button :disabled="submitting"
          class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-sm font-medium rounded-lg"
          @click="submitExam">
          {{ submitting ? 'Mengirim...' : 'Submit Jawaban' }}
        </button>
      </div>
      <!-- Progress bar -->
      <div class="w-full bg-gray-200 rounded-full h-1.5">
        <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" :style="{ width: progress + '%' }" />
      </div>
      <!-- Tab switch warning -->
      <div v-if="tabSwitchCount > 0" class="p-3 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2">
        <ExclamationTriangleIcon class="w-5 h-5 text-red-500 shrink-0" />
        <p class="text-sm text-red-700">Perpindahan tab terdeteksi: <strong>{{ tabSwitchCount }}x</strong>. Aktivitas ini tercatat.</p>
      </div>
      <!-- Question navigation -->
      <div class="flex flex-wrap gap-1.5">
        <button v-for="(q, i) in questions" :key="q.id"
          :class="['w-8 h-8 rounded-lg text-xs font-bold transition-colors',
            currentQ === i ? 'bg-blue-600 text-white' :
            answers[q.id]?.trim() ? 'bg-green-100 text-green-700 border border-green-300' :
            'bg-gray-100 text-gray-500 hover:bg-gray-200']"
          @click="currentQ = i">{{ i + 1 }}
        </button>
      </div>
      <!-- Current Question -->
      <div v-if="questions[currentQ]" class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center gap-2 mb-3">
          <span class="text-xs font-bold text-gray-400">Soal {{ currentQ + 1 }} / {{ questions.length }}</span>
          <span class="text-xs px-1.5 py-0.5 bg-gray-100 rounded text-gray-600">{{ questions[currentQ].type.replace(/_/g, ' ') }}</span>
          <span class="text-xs text-blue-600 font-medium ml-auto">{{ questions[currentQ].score }} poin</span>
        </div>
        <p class="text-gray-800 text-sm whitespace-pre-line mb-4 leading-relaxed">{{ questions[currentQ].question }}</p>
        <!-- Pilihan Ganda / Benar Salah -->
        <div v-if="questions[currentQ].options?.length" class="space-y-2">
          <label v-for="(opt, oi) in questions[currentQ].options" :key="oi"
            :class="['flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors select-none',
              answers[questions[currentQ].id] === opt ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300 hover:bg-gray-50']">
            <input type="radio" :name="`q_${questions[currentQ].id}`" :value="opt"
              v-model="answers[questions[currentQ].id]" class="text-blue-600 shrink-0" />
            <span class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-200 text-gray-600 text-xs font-bold shrink-0">{{ String.fromCharCode(65 + oi) }}</span>
            <span class="text-sm text-gray-700">{{ opt }}</span>
          </label>
        </div>
        <!-- Esai / Studi Kasus -->
        <div v-else>
          <textarea v-model="answers[questions[currentQ].id]" rows="6" placeholder="Tulis jawaban Anda di sini..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
        </div>
        <!-- Nav buttons -->
        <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-100">
          <button v-if="currentQ > 0" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="currentQ--">← Sebelumnya</button>
          <div v-else />
          <button v-if="currentQ < questions.length - 1" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg" @click="currentQ++">Selanjutnya →</button>
          <button v-else :disabled="submitting" class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-sm font-medium rounded-lg" @click="submitExam">Submit Jawaban ✓</button>
        </div>
      </div>
    </div>

    <!-- Step: Result -->
    <div v-if="step === 'result'" class="max-w-2xl mx-auto mt-8 space-y-4">
      <!-- Kartu skor utama -->
      <div class="bg-white rounded-2xl border border-gray-200 p-8 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-4">
          <CheckCircleIcon class="w-8 h-8 text-green-600" />
        </div>
        <h1 class="text-xl font-bold text-gray-900">Ujian Selesai!</h1>
        <p class="text-sm text-gray-500 mt-2">Jawaban Anda telah berhasil disimpan.</p>

        <!-- Skor -->
        <div v-if="result?.total_score !== undefined" class="mt-5 p-5 bg-blue-50 rounded-xl">
          <p class="text-sm text-blue-500 mb-1">Skor Anda</p>
          <p class="text-5xl font-bold text-blue-700">{{ result.total_score }}</p>
          <p v-if="result.max_score" class="text-sm text-blue-400 mt-1">dari {{ result.max_score }} poin</p>
          <!-- Progress bar skor -->
          <div v-if="result.max_score" class="mt-3 w-full bg-blue-200 rounded-full h-2">
            <div class="bg-blue-600 h-2 rounded-full transition-all"
              :style="{ width: Math.min(100, Math.round((result.total_score / result.max_score) * 100)) + '%' }" />
          </div>
          <p v-if="result.max_score" class="text-xs text-blue-400 mt-1">
            {{ Math.round((result.total_score / result.max_score) * 100) }}%
          </p>
        </div>
        <div v-if="!result?.show_score && result?.total_score === undefined" class="mt-5 p-4 bg-gray-50 rounded-xl">
          <p class="text-sm text-gray-500">Skor akan ditampilkan setelah dosen selesai mengoreksi.</p>
        </div>

        <div v-if="result?.tab_switches > 0" class="mt-3 p-3 bg-yellow-50 rounded-lg text-sm text-yellow-700">
          ⚠ Perpindahan tab tercatat: {{ result.tab_switches }}x
        </div>
        <button class="w-full mt-6 py-2.5 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg text-sm font-medium" @click="router.back()">
          Kembali ke Kelas
        </button>
      </div>

      <!-- Detail jawaban (jika show_score aktif) -->
      <div v-if="result?.show_score && result?.answers?.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Pembahasan Jawaban</h2>
        <div class="space-y-3">
          <div v-for="(a, i) in result.answers" :key="a.question_id"
            :class="['p-4 rounded-xl border',
              a.is_correct === true  ? 'border-green-200 bg-green-50' :
              a.is_correct === false ? 'border-red-200 bg-red-50' :
              'border-gray-200']">
            <div class="flex items-start justify-between gap-2 mb-2">
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-gray-400">#{{ i + 1 }}</span>
                <span class="text-xs px-1.5 py-0.5 bg-white rounded border text-gray-600">{{ a.question_type?.replace(/_/g, ' ') }}</span>
                <CheckCircleIcon v-if="a.is_correct === true" class="w-4 h-4 text-green-500" />
                <XCircleIcon v-else-if="a.is_correct === false" class="w-4 h-4 text-red-500" />
              </div>
              <span class="text-xs font-bold text-gray-600 shrink-0">{{ a.score ?? '?' }} / {{ a.max_score }} poin</span>
            </div>
            <p class="text-sm text-gray-800 font-medium mb-2">{{ a.question_text }}</p>
            <!-- Jawaban mahasiswa -->
            <div :class="['p-2 rounded-lg text-sm mb-1',
              a.is_correct === true  ? 'bg-green-100 text-green-800' :
              a.is_correct === false ? 'bg-red-100 text-red-800' : 'bg-blue-50 text-blue-800']">
              <span class="text-xs opacity-70">Jawaban Anda: </span>{{ a.student_answer ?? '(tidak dijawab)' }}
            </div>
            <!-- Jawaban benar -->
            <div v-if="a.correct_answer && a.is_correct !== true" class="p-2 bg-green-100 rounded-lg text-sm text-green-800">
              <span class="text-xs text-green-600">✓ Jawaban benar: </span><strong>{{ a.correct_answer }}</strong>
            </div>
            <!-- Penjelasan -->
            <div v-if="a.explanation" class="mt-1 p-2 bg-white rounded-lg text-xs text-gray-600 border border-gray-200">
              💡 {{ a.explanation }}
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
