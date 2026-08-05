<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import {
  CheckCircleIcon, ClockIcon, ExclamationTriangleIcon,
  XCircleIcon, AcademicCapIcon, CameraIcon, CurrencyDollarIcon,
  PrinterIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router  = useRouter()
const auth    = useAuthStore()
const toast   = useToast()
const loading = ref(true)
const data    = ref<any>(null)
const photoInput = ref<HTMLInputElement | null>(null)
const uploadingPhoto = ref(false)
const paymentProof = ref('')
const paying = ref(false)

onMounted(async () => {
  if (!auth.token) { await router.push('/pmb/login'); return }
  try {
    const res = await api.get('/pmb/my/registration')
    data.value = res.data
    // Jika belum ada data → redirect ke formulir
    if (!res.data) {
      router.replace('/pmb/form')
      return
    }
    // Jika masih DRAFT → redirect ke formulir untuk dilengkapi
    if (res.data.status === 'DRAFT') {
      router.replace('/pmb/form')
      return
    }
  } finally { loading.value = false }
})

const statusSteps = [
  { key: 'DRAFT', label: 'Draft', icon: ClockIcon },
  { key: 'SUBMITTED', label: 'Disubmit', icon: CheckCircleIcon },
  { key: 'MENUNGGU_VERIFIKASI', label: 'Menunggu Verifikasi', icon: ClockIcon },
  { key: 'TERVERIFIKASI', label: 'Terverifikasi', icon: CheckCircleIcon },
  { key: 'MENGIKUTI_SELEKSI', label: 'Seleksi', icon: AcademicCapIcon },
  { key: 'LULUS', label: 'Lulus', icon: CheckCircleIcon },
  { key: 'MAHASISWA_BARU', label: 'Mahasiswa Baru', icon: AcademicCapIcon },
]

const currentStepIndex = computed(() => {
  if (!data.value) return 0
  const idx = statusSteps.findIndex(s => s.key === data.value.status)
  return idx >= 0 ? idx : 0
})

const photoUrl = computed(() => {
  if (!data.value?.photo_path) return null
  const base = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/api\/?$/, '')
  return `${base}/storage/${data.value.photo_path}`
})

async function uploadPhoto() {
  const file = photoInput.value?.files?.[0]
  if (!file) return
  uploadingPhoto.value = true
  try {
    const formData = new FormData()
    formData.append('photo', file)
    const { data: res } = await api.post('/pmb/my/photo', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
    data.value.photo_path = res.photo_url
    toast.success(res.message)
  } catch { toast.error('Gagal upload foto.') }
  finally { uploadingPhoto.value = false }
}

async function confirmPayment() {
  paying.value = true
  try {
    const { data: res } = await api.post('/pmb/my/payment', { payment_proof: paymentProof.value })
    data.value = res.data
    toast.success(res.message)
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { paying.value = false }
}

const downloadingCard = ref(false)

async function downloadCard() {
  downloadingCard.value = true
  try {
    const res = await api.get('/pmb/my/card-pdf', { responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href     = url
    link.download = `kartu-peserta-${data.value.registration_number}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (e: any) {
    if (e?.response?.status === 422) {
      toast.error('Kartu peserta belum tersedia.')
    } else {
      toast.error('Gagal mengunduh kartu peserta.')
    }
  } finally {
    downloadingCard.value = false
  }
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-' }
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

  <div v-else-if="data" class="max-w-3xl mx-auto space-y-6">

    <!-- Progress bar -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-4">Status Pendaftaran</h2>
      <div class="flex items-center gap-1 overflow-x-auto pb-2">
        <template v-for="(s, i) in statusSteps" :key="s.key">
          <div class="flex flex-col items-center min-w-[70px]">
            <div :class="[
              'w-8 h-8 rounded-full flex items-center justify-center transition-colors',
              i <= currentStepIndex ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400'
            ]">
              <component :is="s.icon" class="w-4 h-4" />
            </div>
            <span class="text-[10px] text-gray-500 mt-1 text-center">{{ s.label }}</span>
          </div>
          <div v-if="i < statusSteps.length - 1"
               :class="['flex-1 h-0.5 min-w-[16px]', i < currentStepIndex ? 'bg-green-400' : 'bg-gray-200']" />
        </template>
      </div>
    </div>

    <!-- Info utama -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
      <div><span class="text-xs text-gray-400">No. Pendaftaran</span><p class="font-mono font-medium text-gray-800">{{ data.registration_number }}</p></div>
      <div><span class="text-xs text-gray-400">Nama</span><p class="font-medium text-gray-800">{{ data.full_name }}</p></div>
      <div><span class="text-xs text-gray-400">Pilihan 1</span><p class="text-gray-800">{{ data.study_program_choice1?.name ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">Jalur</span><p class="text-gray-800">{{ data.path?.name ?? 'Reguler' }}</p></div>
      <div><span class="text-xs text-gray-400">Gelombang</span><p class="text-gray-800">{{ data.period?.name ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">Pembayaran</span>
        <p :class="data.is_paid ? 'text-green-600 font-medium' : 'text-red-500'">{{ data.is_paid ? 'Lunas' : 'Belum Bayar' }}</p>
      </div>
    </div>

    <!-- Upload foto -->
    <div v-if="data.status === 'DRAFT' || data.status === 'SUBMITTED'" class="bg-white rounded-xl border border-gray-200 p-5">
      <h3 class="text-sm font-semibold text-gray-800 mb-3">Pas Foto</h3>
      <div class="flex items-center gap-4">
        <div class="w-24 h-32 bg-gray-100 rounded-lg border border-gray-200 overflow-hidden flex items-center justify-center">
          <img v-if="photoUrl" :src="photoUrl" class="w-full h-full object-cover" />
          <CameraIcon v-else class="w-8 h-8 text-gray-300" />
        </div>
        <div>
          <input ref="photoInput" type="file" accept="image/jpeg,image/png" class="hidden" @change="uploadPhoto" />
          <button :disabled="uploadingPhoto" class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg" @click="photoInput?.click()">
            {{ uploadingPhoto ? 'Mengupload...' : (data.photo_path ? 'Ganti Foto' : 'Upload Foto') }}
          </button>
          <p class="text-xs text-gray-400 mt-1">JPG/PNG, ukuran 3x4, maks 2MB</p>
        </div>
      </div>
    </div>

    <!-- Pembayaran -->
    <div v-if="data.status === 'SUBMITTED' && !data.is_paid" class="bg-white rounded-xl border border-gray-200 p-5">
      <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
        <CurrencyDollarIcon class="w-5 h-5 text-orange-500" /> Konfirmasi Pembayaran
      </h3>
      <p class="text-sm text-gray-600 mb-3">
        Lakukan pembayaran biaya pendaftaran, lalu konfirmasi di bawah ini.
      </p>
      <div class="flex items-end gap-3">
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 mb-1">Bukti Pembayaran (link/keterangan)</label>
          <input v-model="paymentProof" placeholder="Contoh: Transfer BCA 20/07/2026 atau link bukti" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <button :disabled="paying" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg whitespace-nowrap" @click="confirmPayment">
          {{ paying ? 'Memproses...' : 'Konfirmasi Bayar' }}
        </button>
      </div>
    </div>

    <!-- Cetak Kartu Peserta PDF -->
    <div v-if="['TERVERIFIKASI','MENGIKUTI_SELEKSI','LULUS','DAFTAR_ULANG','MAHASISWA_BARU'].includes(data.status)"
         class="bg-white rounded-xl border border-gray-200 p-5">
      <h3 class="text-sm font-semibold text-gray-800 mb-3">Kartu Peserta</h3>
      <p class="text-sm text-gray-500 mb-3">
        Download kartu peserta dalam format PDF. Kartu ini wajib dibawa saat mengikuti seleksi.
      </p>
      <button
        :disabled="downloadingCard"
        class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg flex items-center gap-2"
        @click="downloadCard"
      >
        <PrinterIcon class="w-4 h-4" />
        {{ downloadingCard ? 'Menyiapkan PDF...' : 'Download Kartu Peserta (PDF)' }}
      </button>
    </div>

    <!-- Hasil Seleksi -->
    <div v-if="data.selection_result" class="bg-white rounded-xl border border-gray-200 p-5">
      <h3 class="text-sm font-semibold text-gray-800 mb-3">Hasil Seleksi</h3>
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div><span class="text-xs text-gray-400">Nilai Akhir</span><p class="text-xl font-bold text-gray-900">{{ data.selection_result.final_score }}</p></div>
        <div><span class="text-xs text-gray-400">Status</span>
          <p :class="['text-lg font-bold', data.selection_result.final_status === 'LULUS' ? 'text-green-600' : 'text-red-600']">
            {{ data.selection_result.final_status?.replace('_', ' ') ?? data.selection_result.recommendation?.replace('_', ' ') ?? '-' }}
          </p>
        </div>
        <div v-if="data.accepted_program"><span class="text-xs text-gray-400">Diterima di</span><p class="font-medium text-gray-800">{{ data.accepted_program.name }}</p></div>
      </div>
    </div>

    <!-- Daftar Ulang Info -->
    <div v-if="data.re_registration" class="bg-white rounded-xl border border-gray-200 p-5">
      <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
        <AcademicCapIcon class="w-5 h-5 text-teal-600" /> Daftar Ulang
      </h3>
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div><span class="text-xs text-gray-400">NIM</span><p class="font-mono font-bold text-gray-900 text-lg">{{ data.re_registration.nim }}</p></div>
        <div><span class="text-xs text-gray-400">Status</span><p class="font-medium text-green-600">{{ data.re_registration.is_completed ? 'Selesai' : 'Belum Selesai' }}</p></div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3">
      <RouterLink v-if="data.status === 'DRAFT'" to="/pmb/form" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg">
        Lanjutkan Formulir
      </RouterLink>
    </div>
  </div>
</template>
