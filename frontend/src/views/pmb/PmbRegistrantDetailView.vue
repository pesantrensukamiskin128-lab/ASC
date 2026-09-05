<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ArrowLeftIcon, CheckCircleIcon, XCircleIcon, AcademicCapIcon,
  DocumentTextIcon, LinkIcon, PhotoIcon, CurrencyDollarIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route   = useRoute()
const router  = useRouter()
const toast   = useToast()
const loading = ref(true)
const data    = ref<any>(null)
const scoring = ref(false)
const nimInput = ref('')

// Exam types & score input
interface ScoreInput { score: number; note: string }
interface ExamType {
  id: number; code: string; name: string; weight: number; passing_grade: number
  input: ScoreInput
}
const examTypes = ref<ExamType[]>([])
const savingScores = ref(false)

onMounted(async () => {
  try {
    const [regRes, examRes] = await Promise.all([
      api.get(`/pmb-registrants/${route.params.id}`),
      api.get('/pmb-exam-types'),
    ])
    data.value = regRes.data
    examTypes.value = examRes.data.map((examType: Omit<ExamType, 'input'>) => ({
      ...examType,
      input: { score: 0, note: '' },
    }))

    // Pre-fill score inputs dari data existing
    initScoreInputs()
  } finally { loading.value = false }
})

function initScoreInputs() {
  examTypes.value.forEach(et => {
    const existing = data.value?.exam_scores?.find((s: any) => s.exam_type_id === et.id)
    et.input = {
      score: existing?.score ?? 0,
      note:  existing?.note ?? '',
    }
  })
}

const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600', SUBMITTED: 'bg-blue-100 text-blue-700',
  MENUNGGU_VERIFIKASI: 'bg-yellow-100 text-yellow-700', TERVERIFIKASI: 'bg-indigo-100 text-indigo-700',
  MENGIKUTI_SELEKSI: 'bg-purple-100 text-purple-700', LULUS: 'bg-green-100 text-green-700',
  TIDAK_LULUS: 'bg-red-100 text-red-600', DAFTAR_ULANG: 'bg-teal-100 text-teal-700',
  MAHASISWA_BARU: 'bg-emerald-100 text-emerald-700',
}

async function verify(status: string) {
  await api.post(`/pmb-registrants/${data.value.id}/verify`, { status })
  toast.success('Status verifikasi diupdate.')
  data.value.status = status
}

async function setSelection() {
  await api.post(`/pmb-registrants/${data.value.id}/set-selection`)
  toast.success('Status diubah ke MENGIKUTI SELEKSI.')
  data.value.status = 'MENGIKUTI_SELEKSI'
}

async function saveScores() {
  savingScores.value = true
  try {
    const scores = examTypes.value.map(examType => ({
      exam_type_id: examType.id,
      score: examType.input.score,
      note: examType.input.note || null,
    }))
    await api.post(`/pmb-registrants/${data.value.id}/scores`, { scores })
    toast.success('Nilai berhasil disimpan.')
    // Reload data
    const fresh = await api.get(`/pmb-registrants/${route.params.id}`)
    data.value = fresh.data
    initScoreInputs()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal menyimpan nilai.')
  } finally {
    savingScores.value = false
  }
}

async function calculateResult() {
  const { data: res } = await api.post(`/pmb-registrants/${data.value.id}/calculate`)
  toast.success(`Nilai akhir: ${res.final_score} — Rekomendasi: ${res.recommendation}`)
  // Reload
  const fresh = await api.get(`/pmb-registrants/${route.params.id}`)
  data.value = fresh.data
}

async function setFinal(status: 'LULUS' | 'TIDAK_LULUS') {
  await api.post(`/pmb-registrants/${data.value.id}/final-status`, {
    final_status: status, accepted_program_id: data.value.choice_1,
  })
  toast.success(`Pendaftar dinyatakan ${status.replace('_', ' ')}.`)
  const fresh = await api.get(`/pmb-registrants/${route.params.id}`)
  data.value = fresh.data
}

async function processReRegistration() {
  if (!nimInput.value) { toast.error('NIM wajib diisi.'); return }
  await api.post(`/pmb-registrants/${data.value.id}/re-registration`, { nim: nimInput.value })
  toast.success('Daftar ulang berhasil. Mahasiswa baru telah dibuat.')
  const fresh = await api.get(`/pmb-registrants/${route.params.id}`)
  data.value = fresh.data
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-' }

const photoUrl = computed(() => {
  if (!data.value?.photo_path) return null
  const base = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/api\/?$/, '')
  return `${base}/storage/${data.value.photo_path}`
})
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64">
    <p class="text-gray-400">Memuat...</p>
  </div>

  <div v-else-if="data" class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()">
        <ArrowLeftIcon class="w-5 h-5 text-gray-500" />
      </button>
      <div>
        <h1 class="text-xl font-bold text-gray-900">{{ data.full_name }}</h1>
        <p class="text-sm text-gray-500">{{ data.registration_number }}</p>
      </div>
      <span :class="['ml-auto inline-flex px-3 py-1 rounded-full text-sm font-medium', statusColor[data.status]]">
        {{ data.status.replace(/_/g, ' ') }}
      </span>
    </div>

    <!-- Data Pribadi -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Data Pribadi</h2>
      <div class="flex items-start gap-4">
        <!-- Pas Foto -->
        <div class="w-24 h-32 rounded-lg border border-gray-200 overflow-hidden bg-gray-50 shrink-0 flex items-center justify-center">
          <img v-if="photoUrl" :src="photoUrl" class="w-full h-full object-cover" alt="Pas Foto" />
          <PhotoIcon v-else class="w-8 h-8 text-gray-300" />
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm flex-1">
          <div><span class="text-gray-400 text-xs">Jenis Kelamin</span><p class="text-gray-800">{{ data.gender === 'L' ? 'Laki-laki' : (data.gender === 'P' ? 'Perempuan' : '-') }}</p></div>
          <div><span class="text-gray-400 text-xs">TTL</span><p class="text-gray-800">{{ data.birth_place ?? '-' }}, {{ formatDate(data.birth_date) }}</p></div>
          <div><span class="text-gray-400 text-xs">Agama</span><p class="text-gray-800">{{ data.religion ?? '-' }}</p></div>
          <div><span class="text-gray-400 text-xs">NIK</span><p class="text-gray-800 font-mono text-xs">{{ data.nik ?? '-' }}</p></div>
          <div><span class="text-gray-400 text-xs">Email</span><p class="text-gray-800">{{ data.email ?? '-' }}</p></div>
          <div><span class="text-gray-400 text-xs">Telepon</span><p class="text-gray-800">{{ data.phone ?? '-' }}</p></div>
          <div class="col-span-2 md:col-span-3"><span class="text-gray-400 text-xs">Alamat</span><p class="text-gray-800">{{ [data.address, data.village, data.district, data.city, data.province, data.postal_code].filter(Boolean).join(', ') || '-' }}</p></div>
        </div>
      </div>
    </div>

    <!-- Data Orang Tua / Wali -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Data Orang Tua / Wali</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <!-- Ayah -->
        <div class="space-y-1">
          <p class="text-xs font-semibold text-gray-400 uppercase">Ayah</p>
          <p class="text-gray-800 font-medium">{{ data.father_name ?? '-' }}</p>
          <p class="text-gray-500 text-xs">{{ data.father_occupation ?? '-' }}</p>
          <p class="text-gray-500 text-xs">{{ data.father_phone ?? '-' }}</p>
        </div>
        <!-- Ibu -->
        <div class="space-y-1">
          <p class="text-xs font-semibold text-gray-400 uppercase">Ibu</p>
          <p class="text-gray-800 font-medium">{{ data.mother_name ?? '-' }}</p>
          <p class="text-gray-500 text-xs">{{ data.mother_occupation ?? '-' }}</p>
          <p class="text-gray-500 text-xs">{{ data.mother_phone ?? '-' }}</p>
        </div>
        <!-- Wali -->
        <div v-if="data.guardian_name" class="space-y-1">
          <p class="text-xs font-semibold text-gray-400 uppercase">Wali</p>
          <p class="text-gray-800 font-medium">{{ data.guardian_name }}</p>
          <p class="text-gray-500 text-xs">{{ data.guardian_occupation ?? '-' }}</p>
          <p class="text-gray-500 text-xs">{{ data.guardian_phone ?? '-' }}</p>
        </div>
      </div>
    </div>

    <!-- Riwayat Pendidikan -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Riwayat Pendidikan</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
        <div class="col-span-2"><span class="text-gray-400 text-xs">Nama Sekolah</span><p class="text-gray-800 font-medium">{{ data.school_name ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Tahun Lulus</span><p class="text-gray-800">{{ data.graduation_year ?? '-' }}</p></div>
        <div class="col-span-2"><span class="text-gray-400 text-xs">Alamat Sekolah</span><p class="text-gray-800">{{ data.school_address ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">No. Ijazah</span><p class="text-gray-800 font-mono text-xs">{{ data.diploma_number ?? '-' }}</p></div>
      </div>
    </div>

    <!-- Pilihan Prodi & Jalur -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Pilihan Program Studi & Jalur</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
        <div><span class="text-gray-400 text-xs">Jalur</span><p class="text-gray-800 font-medium">{{ data.path?.name ?? 'Reguler' }}</p></div>
        <div><span class="text-gray-400 text-xs">Pilihan 1</span><p class="text-gray-800 font-medium">{{ data.study_program_choice1?.name ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Pilihan 2</span><p class="text-gray-800">{{ data.study_program_choice2?.name ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Pilihan 3</span><p class="text-gray-800">{{ data.study_program_choice3?.name ?? '-' }}</p></div>
      </div>
      <div v-if="data.achievement_description" class="pt-2 border-t border-gray-100">
        <span class="text-gray-400 text-xs">Deskripsi Prestasi / Khusus</span>
        <p class="text-gray-800 text-sm mt-1">{{ data.achievement_description }}</p>
      </div>
    </div>

    <!-- Dokumen -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide flex items-center gap-2">
        <DocumentTextIcon class="w-4 h-4" /> Dokumen
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
        <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
          <PhotoIcon class="w-5 h-5 text-gray-400 shrink-0" />
          <div class="min-w-0 flex-1">
            <p class="text-xs text-gray-500">Pas Foto</p>
            <p v-if="photoUrl" class="text-green-600 text-xs font-medium">✓ Terupload</p>
            <p v-else class="text-red-500 text-xs">✗ Belum diupload</p>
          </div>
          <a v-if="photoUrl" :href="photoUrl" target="_blank" class="text-blue-600 hover:text-blue-700 text-xs underline">Lihat</a>
        </div>

        <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
          <LinkIcon class="w-5 h-5 text-gray-400 shrink-0" />
          <div class="min-w-0 flex-1">
            <p class="text-xs text-gray-500">Ijazah</p>
            <p v-if="data.diploma_link" class="text-green-600 text-xs font-medium truncate">✓ Link tersedia</p>
            <p v-else class="text-red-500 text-xs">✗ Belum dilampirkan</p>
          </div>
          <a v-if="data.diploma_link" :href="data.diploma_link" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-700 text-xs underline shrink-0">Buka</a>
        </div>

        <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
          <LinkIcon class="w-5 h-5 text-gray-400 shrink-0" />
          <div class="min-w-0 flex-1">
            <p class="text-xs text-gray-500">Kartu Keluarga</p>
            <p v-if="data.family_card_link" class="text-green-600 text-xs font-medium truncate">✓ Link tersedia</p>
            <p v-else class="text-red-500 text-xs">✗ Belum dilampirkan</p>
          </div>
          <a v-if="data.family_card_link" :href="data.family_card_link" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-700 text-xs underline shrink-0">Buka</a>
        </div>

        <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
          <LinkIcon class="w-5 h-5 text-gray-400 shrink-0" />
          <div class="min-w-0 flex-1">
            <p class="text-xs text-gray-500">KTP / Identitas</p>
            <p v-if="data.identity_link" class="text-green-600 text-xs font-medium truncate">✓ Link tersedia</p>
            <p v-else class="text-red-500 text-xs">✗ Belum dilampirkan</p>
          </div>
          <a v-if="data.identity_link" :href="data.identity_link" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-700 text-xs underline shrink-0">Buka</a>
        </div>
      </div>
    </div>

    <!-- Pembayaran -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide flex items-center gap-2">
        <CurrencyDollarIcon class="w-4 h-4" /> Pembayaran
      </h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
        <div>
          <span class="text-gray-400 text-xs">Status Bayar</span>
          <p :class="data.is_paid ? 'text-green-600 font-medium' : 'text-red-500'">
            {{ data.is_paid ? 'Lunas' : 'Belum Bayar' }}
          </p>
        </div>
        <div v-if="data.paid_at">
          <span class="text-gray-400 text-xs">Tanggal Bayar</span>
          <p class="text-gray-800">{{ formatDate(data.paid_at) }}</p>
        </div>
        <div v-if="data.payment_proof" class="col-span-2 md:col-span-1">
          <span class="text-gray-400 text-xs">Bukti Pembayaran</span>
          <p v-if="data.payment_proof.startsWith('http')" class="mt-0.5">
            <a :href="data.payment_proof" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-700 text-xs underline break-all">
              {{ data.payment_proof }}
            </a>
          </p>
          <p v-else class="text-gray-800 text-xs break-all">{{ data.payment_proof }}</p>
        </div>
      </div>
    </div>

    <!-- Input & Tampilan Nilai Seleksi -->
    <div v-if="data.status === 'MENGIKUTI_SELEKSI' || data.exam_scores?.length" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Nilai Seleksi</h2>

      <!-- Form input nilai (hanya saat MENGIKUTI_SELEKSI) -->
      <div v-if="data.status === 'MENGIKUTI_SELEKSI'">
        <p class="text-sm text-gray-500 mb-3">Input nilai per jenis ujian. Klik "Simpan Nilai" setelah mengisi semua.</p>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-400 border-b">
              <th class="pb-2">Jenis Ujian</th>
              <th class="pb-2 text-center w-20">Bobot</th>
              <th class="pb-2 text-center w-16">KKM</th>
              <th class="pb-2 text-center w-28">Nilai</th>
              <th class="pb-2 w-40">Catatan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="et in examTypes" :key="et.id" class="border-b border-gray-50">
              <td class="py-2.5 text-gray-700">{{ et.name }}</td>
              <td class="py-2.5 text-center text-gray-500">{{ et.weight }}%</td>
              <td class="py-2.5 text-center text-gray-500">{{ et.passing_grade }}</td>
              <td class="py-2.5 text-center">
                <input
                  v-model.number="et.input.score"
                  type="number" min="0" max="100" step="0.5"
                  class="w-20 px-2 py-1 text-center border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="et.input.score < et.passing_grade && et.input.score > 0 ? 'border-red-300 bg-red-50' : ''"
                />
              </td>
              <td class="py-2.5">
                <input
                  v-model="et.input.note"
                  type="text" placeholder="Opsional"
                  class="w-full px-2 py-1 border border-gray-200 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </td>
            </tr>
          </tbody>
        </table>
        <div class="flex items-center gap-3 pt-3">
          <button
            :disabled="savingScores"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg"
            @click="saveScores"
          >
            {{ savingScores ? 'Menyimpan...' : 'Simpan Nilai' }}
          </button>
          <button
            v-if="data.exam_scores?.length"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg"
            @click="calculateResult"
          >
            Hitung Nilai Akhir
          </button>
        </div>
      </div>

      <!-- Tabel nilai readonly (setelah sudah diinput) -->
      <div v-else-if="data.exam_scores?.length">
        <table class="w-full text-sm">
          <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2">Jenis Ujian</th><th class="pb-2 text-center">Bobot</th><th class="pb-2 text-center">Nilai</th><th class="pb-2 text-center">KKM</th><th class="pb-2 text-center">Status</th></tr></thead>
          <tbody>
            <tr v-for="s in data.exam_scores" :key="s.id" class="border-b border-gray-50">
              <td class="py-2">{{ s.exam_type.name }}</td>
              <td class="py-2 text-center text-gray-500">{{ s.exam_type.weight }}%</td>
              <td class="py-2 text-center font-medium">{{ s.score }}</td>
              <td class="py-2 text-center text-gray-400">{{ s.exam_type.passing_grade }}</td>
              <td class="py-2 text-center">
                <span :class="['text-xs font-medium', s.score >= s.exam_type.passing_grade ? 'text-green-600' : 'text-red-500']">
                  {{ s.score >= s.exam_type.passing_grade ? '✓ Lulus' : '✗ Tidak Lulus' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Hasil akhir -->
      <div v-if="data.selection_result" class="pt-3 border-t border-gray-100 flex items-center justify-between">
        <span class="text-sm text-gray-500">Nilai Akhir: <strong class="text-lg text-gray-900">{{ data.selection_result.final_score }}</strong></span>
        <span :class="['px-3 py-1 rounded-full text-xs font-semibold', data.selection_result.recommendation === 'LULUS' ? 'bg-green-100 text-green-700' : data.selection_result.recommendation === 'CADANGAN' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-600']">
          Rekomendasi: {{ data.selection_result.recommendation ?? '-' }}
        </span>
      </div>
    </div>

    <!-- Actions -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Aksi</h2>
      <div class="flex flex-wrap gap-2">
        <button v-if="data.status === 'MENUNGGU_VERIFIKASI'" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg" @click="verify('TERVERIFIKASI')">
          <CheckCircleIcon class="w-4 h-4 inline mr-1" /> Verifikasi
        </button>
        <button v-if="data.status === 'TERVERIFIKASI'" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm rounded-lg" @click="setSelection">
          Set Mengikuti Seleksi
        </button>

        <!-- Kelulusan (hanya muncul setelah ada hasil perhitungan) -->
        <button v-if="data.status === 'MENGIKUTI_SELEKSI' && data.selection_result" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg" @click="setFinal('LULUS')">
          <CheckCircleIcon class="w-4 h-4 inline mr-1" /> Nyatakan Lulus
        </button>
        <button v-if="data.status === 'MENGIKUTI_SELEKSI' && data.selection_result" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg" @click="setFinal('TIDAK_LULUS')">
          <XCircleIcon class="w-4 h-4 inline mr-1" /> Nyatakan Tidak Lulus
        </button>

        <!-- Daftar ulang -->
        <div v-if="data.status === 'LULUS'" class="flex items-center gap-2 w-full pt-2 border-t border-gray-100">
          <input v-model="nimInput" placeholder="NIM Mahasiswa Baru" class="px-3 py-2 border border-gray-300 rounded-lg text-sm flex-1 max-w-xs" />
          <button class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm rounded-lg flex items-center gap-1" @click="processReRegistration">
            <AcademicCapIcon class="w-4 h-4" /> Proses Daftar Ulang
          </button>
        </div>

        <!-- Info jika tidak ada aksi yang tersedia -->
        <p v-if="['DRAFT','SUBMITTED','TIDAK_LULUS','MAHASISWA_BARU'].includes(data.status)" class="text-sm text-gray-400 italic">
          Tidak ada aksi yang tersedia untuk status ini.
        </p>
      </div>
    </div>
  </div>
</template>
