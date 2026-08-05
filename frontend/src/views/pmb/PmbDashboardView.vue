<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  UsersIcon, CheckCircleIcon, XCircleIcon, ClockIcon,
  AcademicCapIcon, CurrencyDollarIcon, DocumentCheckIcon,
  ArrowTrendingUpIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const loading = ref(true)
const stats = ref<any>(null)
const periods = ref<any[]>([])
const filterPeriod = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/pmb-periods-all')
    periods.value = data
    // Default ke periode aktif
    const active = data.find((p: any) => p.is_active)
    if (active) filterPeriod.value = active.id
    await loadStats()
  } finally { loading.value = false }
})

async function loadStats() {
  loading.value = true
  try {
    const { data } = await api.get('/pmb-registrants/statistics', { params: { period_id: filterPeriod.value } })
    stats.value = data
  } finally { loading.value = false }
}

const cards = [
  { key: 'total', label: 'Total Pendaftar', icon: UsersIcon, color: 'blue' },
  { key: 'submitted', label: 'Disubmit', icon: DocumentCheckIcon, color: 'indigo' },
  { key: 'menunggu_verifikasi', label: 'Menunggu Verifikasi', icon: ClockIcon, color: 'yellow' },
  { key: 'terverifikasi', label: 'Terverifikasi', icon: CheckCircleIcon, color: 'cyan' },
  { key: 'mengikuti_seleksi', label: 'Ikut Seleksi', icon: ArrowTrendingUpIcon, color: 'purple' },
  { key: 'lulus', label: 'Lulus', icon: AcademicCapIcon, color: 'green' },
  { key: 'tidak_lulus', label: 'Tidak Lulus', icon: XCircleIcon, color: 'red' },
  { key: 'mahasiswa_baru', label: 'Mahasiswa Baru', icon: AcademicCapIcon, color: 'emerald' },
]

const colorMap: Record<string, string> = {
  blue: 'bg-blue-50 text-blue-700 border-blue-200',
  indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  yellow: 'bg-yellow-50 text-yellow-700 border-yellow-200',
  cyan: 'bg-cyan-50 text-cyan-700 border-cyan-200',
  purple: 'bg-purple-50 text-purple-700 border-purple-200',
  green: 'bg-green-50 text-green-700 border-green-200',
  red: 'bg-red-50 text-red-700 border-red-200',
  emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
}

const iconColorMap: Record<string, string> = {
  blue: 'text-blue-500', indigo: 'text-indigo-500', yellow: 'text-yellow-500',
  cyan: 'text-cyan-500', purple: 'text-purple-500', green: 'text-green-500',
  red: 'text-red-500', emerald: 'text-emerald-500',
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Dashboard PMB</h1>
        <p class="text-sm text-gray-500 mt-0.5">Ringkasan data penerimaan mahasiswa baru</p>
      </div>
      <select
        v-model="filterPeriod"
        class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        @change="loadStats"
      >
        <option value="">Semua Periode</option>
        <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>

    <!-- Stats Grid -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div
        v-for="card in cards"
        :key="card.key"
        :class="['rounded-xl border p-4 transition-shadow hover:shadow-md cursor-pointer', colorMap[card.color]]"
        @click="router.push('/pmb/registrants')"
      >
        <div class="flex items-center justify-between mb-2">
          <component :is="card.icon" :class="['w-5 h-5', iconColorMap[card.color]]" />
        </div>
        <p class="text-2xl font-bold">{{ stats[card.key] ?? 0 }}</p>
        <p class="text-xs mt-1 opacity-75">{{ card.label }}</p>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-4">Aksi Cepat</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <button
          class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition-colors"
          @click="router.push('/pmb/registrants')"
        >
          <UsersIcon class="w-6 h-6 text-blue-600" />
          <span class="text-xs font-medium text-gray-700">Kelola Pendaftar</span>
        </button>
        <button
          class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-purple-50 hover:border-purple-300 transition-colors"
          @click="router.push('/pmb/periods')"
        >
          <ClockIcon class="w-6 h-6 text-purple-600" />
          <span class="text-xs font-medium text-gray-700">Kelola Periode</span>
        </button>
        <button
          class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-green-50 hover:border-green-300 transition-colors"
          @click="router.push('/pmb/paths')"
        >
          <ArrowTrendingUpIcon class="w-6 h-6 text-green-600" />
          <span class="text-xs font-medium text-gray-700">Jalur Seleksi</span>
        </button>
        <button
          class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-orange-50 hover:border-orange-300 transition-colors"
          @click="router.push('/pmb/exam-types')"
        >
          <DocumentCheckIcon class="w-6 h-6 text-orange-600" />
          <span class="text-xs font-medium text-gray-700">Jenis Ujian</span>
        </button>
      </div>
    </div>

    <!-- Funnel Visualization -->
    <div v-if="stats" class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-4">Funnel Pendaftaran</h2>
      <div class="space-y-2">
        <div v-for="stage in [
          { label: 'Pendaftar', value: stats.total, color: 'bg-blue-500' },
          { label: 'Submitted', value: (stats.submitted || 0) + (stats.menunggu_verifikasi || 0) + (stats.terverifikasi || 0) + (stats.mengikuti_seleksi || 0) + (stats.lulus || 0) + (stats.mahasiswa_baru || 0), color: 'bg-indigo-500' },
          { label: 'Terverifikasi', value: (stats.terverifikasi || 0) + (stats.mengikuti_seleksi || 0) + (stats.lulus || 0) + (stats.mahasiswa_baru || 0), color: 'bg-cyan-500' },
          { label: 'Ikut Seleksi', value: (stats.mengikuti_seleksi || 0) + (stats.lulus || 0) + (stats.mahasiswa_baru || 0), color: 'bg-purple-500' },
          { label: 'Lulus', value: (stats.lulus || 0) + (stats.mahasiswa_baru || 0), color: 'bg-green-500' },
          { label: 'Mahasiswa Baru', value: stats.mahasiswa_baru || 0, color: 'bg-emerald-500' },
        ]" :key="stage.label" class="flex items-center gap-3">
          <span class="text-xs text-gray-500 w-28 text-right">{{ stage.label }}</span>
          <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
            <div
              :class="[stage.color, 'h-full rounded-full flex items-center justify-end pr-2 transition-all duration-500']"
              :style="{ width: stats.total > 0 ? `${Math.max((stage.value / stats.total) * 100, 5)}%` : '5%' }"
            >
              <span class="text-xs text-white font-medium">{{ stage.value }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
