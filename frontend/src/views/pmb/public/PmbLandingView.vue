<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { AcademicCapIcon, CalendarDaysIcon, CurrencyDollarIcon, ClipboardDocumentListIcon, CheckBadgeIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

interface Period {
  id: number; name: string; registration_start: string; registration_end: string
  selection_date: string; announcement_date: string; quota: number; registration_fee: number
  academic_year?: { name: string }
}
interface Path { id: number; code: string; name: string; description: string }

const period = ref<Period | null>(null)
const paths  = ref<Path[]>([])

onMounted(async () => {
  const [pRes, pathRes] = await Promise.all([
    api.get('/pmb/active-period'),
    api.get('/pmb/paths'),
  ])
  period.value = pRes.data
  paths.value  = pathRes.data
})

function formatDate(d: string) {
  return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-'
}
function formatCurrency(n: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n)
}

const steps = [
  { icon: ClipboardDocumentListIcon, title: 'Buat Akun & Isi Formulir', desc: 'Daftarkan akun, lengkapi data pribadi dan pilih program studi.' },
  { icon: CurrencyDollarIcon, title: 'Bayar Pendaftaran', desc: 'Lakukan pembayaran biaya pendaftaran dan konfirmasi.' },
  { icon: CheckBadgeIcon, title: 'Verifikasi & Seleksi', desc: 'Berkas diverifikasi panitia lalu ikuti ujian seleksi.' },
  { icon: AcademicCapIcon, title: 'Pengumuman & Daftar Ulang', desc: 'Cek hasil seleksi dan lakukan daftar ulang jika diterima.' },
]
</script>

<template>
  <div class="space-y-12">
    <!-- Hero -->
    <section class="text-center py-8">
      <h1 class="text-3xl md:text-4xl font-bold text-gray-900">
        Penerimaan Mahasiswa Baru
      </h1>
      <p class="text-gray-500 mt-3 text-lg max-w-xl mx-auto">
        Bergabunglah bersama kami. Daftarkan diri Anda untuk menjadi bagian dari generasi terbaik.
      </p>
      <div class="mt-6 flex items-center justify-center gap-3">
        <RouterLink
          to="/pmb/register"
          class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-md transition-all"
        >
          Daftar Sekarang
        </RouterLink>
        <RouterLink
          to="/pmb/login"
          class="px-6 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium rounded-xl transition-all"
        >
          Sudah Punya Akun
        </RouterLink>
      </div>
    </section>

    <!-- Info Gelombang -->
    <section v-if="period" class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
      <div class="flex items-center gap-3 mb-4">
        <CalendarDaysIcon class="w-6 h-6 text-blue-600" />
        <h2 class="text-lg font-bold text-gray-900">{{ period.name }} — {{ period.academic_year?.name ?? '' }}</h2>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 rounded-lg p-3">
          <p class="text-xs text-blue-600 font-medium">Pendaftaran</p>
          <p class="text-sm text-gray-800 mt-1">{{ formatDate(period.registration_start) }}</p>
          <p class="text-xs text-gray-500">s/d {{ formatDate(period.registration_end) }}</p>
        </div>
        <div class="bg-purple-50 rounded-lg p-3">
          <p class="text-xs text-purple-600 font-medium">Seleksi</p>
          <p class="text-sm text-gray-800 mt-1">{{ formatDate(period.selection_date) }}</p>
        </div>
        <div class="bg-green-50 rounded-lg p-3">
          <p class="text-xs text-green-600 font-medium">Pengumuman</p>
          <p class="text-sm text-gray-800 mt-1">{{ formatDate(period.announcement_date) }}</p>
        </div>
        <div class="bg-orange-50 rounded-lg p-3">
          <p class="text-xs text-orange-600 font-medium">Biaya Pendaftaran</p>
          <p class="text-sm text-gray-800 mt-1 font-semibold">{{ formatCurrency(period.registration_fee) }}</p>
          <p class="text-xs text-gray-500">Kuota: {{ period.quota }} mahasiswa</p>
        </div>
      </div>
    </section>

    <!-- Alur Pendaftaran -->
    <section>
      <h2 class="text-lg font-bold text-gray-900 mb-6 text-center">Alur Pendaftaran</h2>
      <div class="grid md:grid-cols-4 gap-4">
        <div v-for="(step, i) in steps" :key="i" class="text-center bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
          <div class="w-12 h-12 mx-auto rounded-full bg-blue-100 flex items-center justify-center mb-3">
            <component :is="step.icon" class="w-6 h-6 text-blue-600" />
          </div>
          <p class="text-xs text-blue-600 font-bold mb-1">Langkah {{ i + 1 }}</p>
          <h3 class="text-sm font-semibold text-gray-900">{{ step.title }}</h3>
          <p class="text-xs text-gray-500 mt-1">{{ step.desc }}</p>
        </div>
      </div>
    </section>

    <!-- Jalur Pendaftaran -->
    <section v-if="paths.length">
      <h2 class="text-lg font-bold text-gray-900 mb-4 text-center">Jalur Pendaftaran</h2>
      <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="p in paths" :key="p.id" class="bg-white rounded-xl border border-gray-200 p-5 hover:border-blue-300 transition-colors">
          <h3 class="font-semibold text-gray-900 text-sm">{{ p.name }}</h3>
          <p class="text-xs text-gray-500 mt-1">{{ p.description }}</p>
        </div>
      </div>
    </section>
  </div>
</template>
