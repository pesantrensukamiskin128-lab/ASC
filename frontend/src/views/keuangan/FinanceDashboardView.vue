<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  BanknotesIcon, CurrencyDollarIcon, ExclamationTriangleIcon,
  ClockIcon, CheckCircleIcon, DocumentTextIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const loading = ref(true)
const stats = ref<any>(null)
const semesters = ref<any[]>([])
const filterSemester = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/semesters', { params: { per_page: 50 } })
    semesters.value = data.data ?? data
    const active = semesters.value.find((s: any) => s.is_active)
    if (active) filterSemester.value = active.id
    await loadStats()
  } finally { loading.value = false }
})

async function loadStats() {
  loading.value = true
  try {
    const { data } = await api.get('/finance/dashboard', { params: { semester_id: filterSemester.value } })
    stats.value = data
  } finally { loading.value = false }
}

function formatCurrency(n: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0)
}

const cards = [
  { key: 'total_invoiced', label: 'Total Tagihan', icon: DocumentTextIcon, color: 'blue', isCurrency: true },
  { key: 'total_paid', label: 'Total Terbayar', icon: CheckCircleIcon, color: 'green', isCurrency: true },
  { key: 'total_outstanding', label: 'Sisa Piutang', icon: CurrencyDollarIcon, color: 'red', isCurrency: true },
  { key: 'pending_payments', label: 'Menunggu Verifikasi', icon: ClockIcon, color: 'yellow', isCurrency: false },
  { key: 'unpaid_invoices', label: 'Tagihan Belum Lunas', icon: ExclamationTriangleIcon, color: 'orange', isCurrency: false },
  { key: 'overdue_invoices', label: 'Jatuh Tempo', icon: ExclamationTriangleIcon, color: 'red', isCurrency: false },
]

const colorMap: Record<string, string> = {
  blue: 'bg-blue-50 text-blue-700 border-blue-200',
  green: 'bg-green-50 text-green-700 border-green-200',
  red: 'bg-red-50 text-red-700 border-red-200',
  yellow: 'bg-yellow-50 text-yellow-700 border-yellow-200',
  orange: 'bg-orange-50 text-orange-700 border-orange-200',
}
const iconColorMap: Record<string, string> = {
  blue: 'text-blue-500', green: 'text-green-500', red: 'text-red-500',
  yellow: 'text-yellow-500', orange: 'text-orange-500',
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Dashboard Keuangan</h1>
        <p class="text-sm text-gray-500 mt-0.5">Ringkasan keuangan mahasiswa per semester</p>
      </div>
      <select
        v-model="filterSemester"
        class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        @change="loadStats"
      >
        <option value="">Semua Semester</option>
        <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
    </div>

    <!-- Stats -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-3 gap-4">
      <div
        v-for="card in cards"
        :key="card.key"
        :class="['rounded-xl border p-4 cursor-pointer hover:shadow-md transition-shadow', colorMap[card.color]]"
        @click="card.key === 'pending_payments' ? router.push('/keuangan/pembayaran') : router.push('/keuangan/tagihan')"
      >
        <component :is="card.icon" :class="['w-5 h-5 mb-2', iconColorMap[card.color]]" />
        <p class="text-2xl font-bold">{{ card.isCurrency ? formatCurrency(stats[card.key]) : stats[card.key] }}</p>
        <p class="text-xs mt-1 opacity-75">{{ card.label }}</p>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-4">Aksi Cepat</h2>
      <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <button
          class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition-colors"
          @click="router.push('/keuangan/tagihan/create')"
        >
          <DocumentTextIcon class="w-6 h-6 text-blue-600" />
          <span class="text-xs font-medium text-gray-700 text-center">Buat Tagihan</span>
        </button>
        <button
          class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-green-50 hover:border-green-300 transition-colors"
          @click="router.push('/keuangan/tagihan/generate')"
        >
          <BanknotesIcon class="w-6 h-6 text-green-600" />
          <span class="text-xs font-medium text-gray-700 text-center">Generate Batch</span>
        </button>
        <button
          class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-yellow-50 hover:border-yellow-300 transition-colors"
          @click="router.push('/keuangan/pembayaran')"
        >
          <ClockIcon class="w-6 h-6 text-yellow-600" />
          <span class="text-xs font-medium text-gray-700 text-center">Verifikasi Bayar</span>
        </button>
        <button
          class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-purple-50 hover:border-purple-300 transition-colors"
          @click="router.push('/keuangan/jenis-tagihan')"
        >
          <CurrencyDollarIcon class="w-6 h-6 text-purple-600" />
          <span class="text-xs font-medium text-gray-700 text-center">Jenis Tagihan</span>
        </button>
        <button
          class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-teal-50 hover:border-teal-300 transition-colors"
          @click="router.push('/keuangan/beasiswa')"
        >
          <CheckCircleIcon class="w-6 h-6 text-teal-600" />
          <span class="text-xs font-medium text-gray-700 text-center">Beasiswa</span>
        </button>
      </div>
    </div>
  </div>
</template>
