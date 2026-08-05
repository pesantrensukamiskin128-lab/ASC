<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const loading = ref(true)
const data = ref<any>(null)

onMounted(async () => {
  try {
    const { data: res } = await api.get('/reports/finance')
    data.value = res
  } finally { loading.value = false }
})

function formatCurrency(n: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0)
}

const statusColors: Record<string, string> = {
  PAID: 'bg-green-100 text-green-700', UNPAID: 'bg-red-100 text-red-600',
  PARTIAL: 'bg-yellow-100 text-yellow-700', OVERDUE: 'bg-red-200 text-red-800',
  WAIVED: 'bg-gray-100 text-gray-500', CANCELLED: 'bg-gray-100 text-gray-400',
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div>
        <h1 class="text-xl font-bold text-gray-900">Statistik Keuangan</h1>
        <p class="text-sm text-gray-500 mt-0.5">Analisis pendapatan, tagihan, dan pembayaran</p>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

    <template v-else-if="data">
      <!-- Pendapatan Per Bulan -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Pendapatan Per Bulan (12 Bulan Terakhir)</h2>
        <div v-if="data.revenue_by_month?.length" class="flex items-end gap-1 h-48">
          <div v-for="item in data.revenue_by_month" :key="item.month" class="flex-1 flex flex-col items-center justify-end">
            <span class="text-[9px] font-medium text-gray-700 mb-1 rotate-0">{{ formatCurrency(item.total) }}</span>
            <div class="w-full bg-green-500 rounded-t-lg transition-all hover:bg-green-600" :style="{ height: `${Math.max((item.total / Math.max(...data.revenue_by_month.map((d: any) => d.total))) * 100, 5)}%` }" />
            <span class="text-[9px] text-gray-500 mt-1">{{ item.month.slice(5) }}</span>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400 text-center py-8">Belum ada data pendapatan.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Status Tagihan -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h2 class="text-sm font-semibold text-gray-800 mb-4">Status Tagihan</h2>
          <div class="space-y-2">
            <div v-for="item in data.invoice_by_status" :key="item.status" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div class="flex items-center gap-2">
                <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusColors[item.status] ?? 'bg-gray-100 text-gray-600']">{{ item.status }}</span>
                <span class="text-sm text-gray-600">{{ item.count }} tagihan</span>
              </div>
              <span class="text-sm font-medium text-gray-900">{{ formatCurrency(item.total_amount) }}</span>
            </div>
          </div>
        </div>

        <!-- Metode Pembayaran -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h2 class="text-sm font-semibold text-gray-800 mb-4">Metode Pembayaran</h2>
          <div v-if="data.payment_by_method?.length" class="space-y-2">
            <div v-for="item in data.payment_by_method" :key="item.payment_method" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div>
                <p class="text-sm font-medium text-gray-800">{{ item.payment_method ?? 'Lainnya' }}</p>
                <p class="text-xs text-gray-500">{{ item.count }} transaksi</p>
              </div>
              <span class="text-sm font-medium text-green-700">{{ formatCurrency(item.total) }}</span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 text-center py-4">Belum ada data.</p>
        </div>
      </div>

      <!-- Tunggakan Per Prodi -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Tunggakan Per Program Studi</h2>
        <div v-if="data.outstanding_by_program?.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase"><th class="px-4 py-2">Kode</th><th class="px-4 py-2">Program Studi</th><th class="px-4 py-2 text-center">Mahasiswa</th><th class="px-4 py-2 text-right">Total Tunggakan</th></tr></thead>
            <tbody>
              <tr v-for="item in data.outstanding_by_program" :key="item.code" class="border-t border-gray-100">
                <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ item.code }}</td>
                <td class="px-4 py-2 text-gray-800">{{ item.name }}</td>
                <td class="px-4 py-2 text-center"><span class="inline-flex px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-bold">{{ item.students }}</span></td>
                <td class="px-4 py-2 text-right font-medium text-red-700">{{ formatCurrency(item.outstanding) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="text-sm text-gray-400 text-center py-4">Tidak ada tunggakan.</p>
      </div>

      <!-- Beasiswa -->
      <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-xl border border-purple-200 p-5">
        <h2 class="text-sm font-semibold text-purple-800 mb-2">Total Beasiswa Diberikan</h2>
        <p class="text-2xl font-bold text-purple-900">{{ formatCurrency(data.scholarship_total) }}</p>
      </div>
    </template>
  </div>
</template>
