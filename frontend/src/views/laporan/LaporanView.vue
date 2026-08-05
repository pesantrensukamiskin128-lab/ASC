<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  UsersIcon, AcademicCapIcon, CurrencyDollarIcon, BriefcaseIcon,
  ChartBarIcon, ArrowTrendingUpIcon, BuildingLibraryIcon, UserGroupIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const loading = ref(true)
const stats = ref<any>(null)
const error = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/reports/summary')
    stats.value = data
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Gagal memuat data laporan.'
    console.error('Report summary error:', e?.response?.data ?? e)
  } finally { loading.value = false }
})

function formatCurrency(n: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0)
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Laporan & Statistik</h1>
      <p class="text-sm text-gray-500 mt-0.5">Ringkasan data institusi dan analitik</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-5 text-center">
      <p class="text-sm text-red-700 font-medium">{{ error }}</p>
      <p class="text-xs text-red-500 mt-1">Pastikan Anda login dengan role yang memiliki akses ke laporan.</p>
    </div>

    <template v-else-if="stats">
      <!-- Summary Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center gap-3">
            <div class="p-2.5 bg-blue-100 rounded-lg"><UsersIcon class="w-5 h-5 text-blue-600" /></div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ stats.active_students }}</p>
              <p class="text-xs text-gray-500">Mahasiswa Aktif</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center gap-3">
            <div class="p-2.5 bg-green-100 rounded-lg"><AcademicCapIcon class="w-5 h-5 text-green-600" /></div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ stats.lecturers }}</p>
              <p class="text-xs text-gray-500">Dosen Aktif</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center gap-3">
            <div class="p-2.5 bg-purple-100 rounded-lg"><UserGroupIcon class="w-5 h-5 text-purple-600" /></div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ stats.alumni }}</p>
              <p class="text-xs text-gray-500">Total Alumni</p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center gap-3">
            <div class="p-2.5 bg-orange-100 rounded-lg"><CurrencyDollarIcon class="w-5 h-5 text-orange-600" /></div>
            <div>
              <p class="text-lg font-bold text-gray-900">{{ formatCurrency(stats.total_revenue) }}</p>
              <p class="text-xs text-gray-500">Total Pendapatan</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation to Sub Reports -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <button class="bg-white rounded-xl border border-gray-200 p-6 text-left hover:shadow-md hover:border-blue-300 transition-all group" @click="router.push('/laporan/mahasiswa')">
          <div class="flex items-center gap-3 mb-3">
            <div class="p-2 bg-blue-50 rounded-lg group-hover:bg-blue-100 transition-colors"><UsersIcon class="w-6 h-6 text-blue-600" /></div>
            <h3 class="font-semibold text-gray-900">Statistik Mahasiswa</h3>
          </div>
          <p class="text-xs text-gray-500">Distribusi mahasiswa per status, prodi, angkatan, gender, dan rasio dosen:mahasiswa</p>
          <div class="mt-3 flex items-center gap-1 text-xs text-blue-600 font-medium">
            <span>Lihat Detail</span>
            <ArrowTrendingUpIcon class="w-3.5 h-3.5" />
          </div>
        </button>

        <button class="bg-white rounded-xl border border-gray-200 p-6 text-left hover:shadow-md hover:border-green-300 transition-all group" @click="router.push('/laporan/akademik')">
          <div class="flex items-center gap-3 mb-3">
            <div class="p-2 bg-green-50 rounded-lg group-hover:bg-green-100 transition-colors"><AcademicCapIcon class="w-6 h-6 text-green-600" /></div>
            <h3 class="font-semibold text-gray-900">Statistik Akademik</h3>
          </div>
          <p class="text-xs text-gray-500">Distribusi nilai, IPK rata-rata per prodi, status KRS, dan tingkat kelulusan</p>
          <div class="mt-3 flex items-center gap-1 text-xs text-green-600 font-medium">
            <span>Lihat Detail</span>
            <ArrowTrendingUpIcon class="w-3.5 h-3.5" />
          </div>
        </button>

        <button class="bg-white rounded-xl border border-gray-200 p-6 text-left hover:shadow-md hover:border-orange-300 transition-all group" @click="router.push('/laporan/keuangan')">
          <div class="flex items-center gap-3 mb-3">
            <div class="p-2 bg-orange-50 rounded-lg group-hover:bg-orange-100 transition-colors"><CurrencyDollarIcon class="w-6 h-6 text-orange-600" /></div>
            <h3 class="font-semibold text-gray-900">Statistik Keuangan</h3>
          </div>
          <p class="text-xs text-gray-500">Pendapatan per bulan, status tagihan, tunggakan per prodi, dan metode pembayaran</p>
          <div class="mt-3 flex items-center gap-1 text-xs text-orange-600 font-medium">
            <span>Lihat Detail</span>
            <ArrowTrendingUpIcon class="w-3.5 h-3.5" />
          </div>
        </button>

        <button class="bg-white rounded-xl border border-gray-200 p-6 text-left hover:shadow-md hover:border-purple-300 transition-all group" @click="router.push('/laporan/sdm')">
          <div class="flex items-center gap-3 mb-3">
            <div class="p-2 bg-purple-50 rounded-lg group-hover:bg-purple-100 transition-colors"><BriefcaseIcon class="w-6 h-6 text-purple-600" /></div>
            <h3 class="font-semibold text-gray-900">Statistik SDM</h3>
          </div>
          <p class="text-xs text-gray-500">Distribusi dosen per jabatan, status kepegawaian, prodi, dan gender</p>
          <div class="mt-3 flex items-center gap-1 text-xs text-purple-600 font-medium">
            <span>Lihat Detail</span>
            <ArrowTrendingUpIcon class="w-3.5 h-3.5" />
          </div>
        </button>
      </div>

      <!-- Additional Info -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border border-blue-200 p-4 text-center">
          <p class="text-xs text-blue-600 font-medium">Total Mahasiswa</p>
          <p class="text-2xl font-bold text-blue-800 mt-1">{{ stats.students }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl border border-green-200 p-4 text-center">
          <p class="text-xs text-green-600 font-medium">Program Studi Aktif</p>
          <p class="text-2xl font-bold text-green-800 mt-1">{{ stats.study_programs }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl border border-purple-200 p-4 text-center">
          <p class="text-xs text-purple-600 font-medium">Kelas Aktif</p>
          <p class="text-2xl font-bold text-purple-800 mt-1">{{ stats.total_classes }}</p>
        </div>
        <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl border border-red-200 p-4 text-center">
          <p class="text-xs text-red-600 font-medium">Tunggakan</p>
          <p class="text-lg font-bold text-red-800 mt-1">{{ formatCurrency(stats.total_outstanding) }}</p>
        </div>
      </div>
    </template>
  </div>
</template>
