<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ArrowLeftIcon, CheckCircleIcon, XCircleIcon, BanknotesIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const loading = ref(true)
const data = ref<any>(null)

onMounted(async () => {
  try {
    const { data: res } = await api.get(`/finance/invoices/${route.params.id}`)
    data.value = res
  } finally { loading.value = false }
})

const outstanding = computed(() => {
  if (!data.value) return 0
  return data.value.total_amount - (data.value.discount_amount || 0) - (data.value.scholarship_amount || 0) - (data.value.paid_amount || 0)
})

const statusColor: Record<string, string> = {
  UNPAID: 'bg-red-100 text-red-700', PARTIAL: 'bg-yellow-100 text-yellow-700',
  PAID: 'bg-green-100 text-green-700', OVERDUE: 'bg-red-200 text-red-800',
  CANCELLED: 'bg-gray-100 text-gray-500', WAIVED: 'bg-blue-100 text-blue-700',
}
const statusLabel: Record<string, string> = {
  UNPAID: 'Belum Bayar', PARTIAL: 'Sebagian', PAID: 'Lunas',
  OVERDUE: 'Jatuh Tempo', CANCELLED: 'Dibatalkan', WAIVED: 'Dibebaskan',
}
const paymentStatusColor: Record<string, string> = {
  PENDING: 'bg-yellow-100 text-yellow-700', VERIFIED: 'bg-green-100 text-green-700',
  REJECTED: 'bg-red-100 text-red-600', REFUNDED: 'bg-purple-100 text-purple-700',
}

function formatCurrency(n: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n)
}
function formatDate(d: string) {
  return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-'
}

async function cancelInvoice() {
  if (!confirm('Yakin membatalkan tagihan ini?')) return
  try {
    await api.post(`/finance/invoices/${data.value.id}/cancel`)
    toast.success('Tagihan dibatalkan.')
    data.value.status = 'CANCELLED'
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function verifyPayment(paymentId: number, action: string) {
  try {
    await api.post(`/finance/payments/${paymentId}/verify`, { action })
    toast.success(action === 'verify' ? 'Pembayaran diverifikasi.' : 'Pembayaran ditolak.')
    // Reload
    const { data: res } = await api.get(`/finance/invoices/${route.params.id}`)
    data.value = res
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="data" class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div>
        <h1 class="text-xl font-bold text-gray-900">Invoice #{{ data.invoice_number }}</h1>
        <p class="text-sm text-gray-500">{{ data.student?.name }} · {{ data.student?.nim }}</p>
      </div>
      <span :class="['ml-auto inline-flex px-3 py-1 rounded-full text-sm font-medium', statusColor[data.status]]">
        {{ statusLabel[data.status] ?? data.status }}
      </span>
    </div>

    <!-- Invoice Info -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
      <div><span class="text-xs text-gray-400">Tanggal Invoice</span><p class="text-gray-800 font-medium">{{ formatDate(data.invoice_date) }}</p></div>
      <div><span class="text-xs text-gray-400">Jatuh Tempo</span><p :class="new Date(data.due_date) < new Date() && data.status !== 'PAID' ? 'text-red-600 font-bold' : 'text-gray-800 font-medium'">{{ formatDate(data.due_date) }}</p></div>
      <div><span class="text-xs text-gray-400">Semester</span><p class="text-gray-800">{{ data.semester?.name ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">Program Studi</span><p class="text-gray-800">{{ data.student?.study_program?.name ?? '-' }}</p></div>
    </div>

    <!-- Items -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-3">Rincian Tagihan</h2>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2">Jenis</th><th class="pb-2">Deskripsi</th><th class="pb-2 text-right">Jumlah</th></tr></thead>
        <tbody>
          <tr v-for="item in data.items" :key="item.id" class="border-b border-gray-50">
            <td class="py-2.5 text-gray-700 font-medium">{{ item.fee_type?.name }}</td>
            <td class="py-2.5 text-gray-500">{{ item.description ?? '-' }}</td>
            <td class="py-2.5 text-right font-medium text-gray-900">{{ formatCurrency(item.amount) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="border-t border-gray-200">
            <td colspan="2" class="pt-3 text-right text-gray-500 font-medium">Subtotal</td>
            <td class="pt-3 text-right font-bold text-gray-900">{{ formatCurrency(data.total_amount) }}</td>
          </tr>
          <tr v-if="data.discount_amount > 0">
            <td colspan="2" class="pt-1 text-right text-gray-500">Diskon</td>
            <td class="pt-1 text-right text-green-600">- {{ formatCurrency(data.discount_amount) }}</td>
          </tr>
          <tr v-if="data.scholarship_amount > 0">
            <td colspan="2" class="pt-1 text-right text-gray-500">Beasiswa</td>
            <td class="pt-1 text-right text-blue-600">- {{ formatCurrency(data.scholarship_amount) }}</td>
          </tr>
          <tr v-if="data.paid_amount > 0">
            <td colspan="2" class="pt-1 text-right text-gray-500">Sudah Dibayar</td>
            <td class="pt-1 text-right text-green-600">- {{ formatCurrency(data.paid_amount) }}</td>
          </tr>
          <tr class="border-t border-gray-300">
            <td colspan="2" class="pt-2 text-right font-semibold text-gray-800">Sisa Tagihan</td>
            <td class="pt-2 text-right font-bold text-lg" :class="outstanding > 0 ? 'text-red-600' : 'text-green-600'">
              {{ formatCurrency(outstanding) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Payments History -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-3">Riwayat Pembayaran</h2>
      <div v-if="!data.payments?.length" class="text-sm text-gray-400 italic">Belum ada pembayaran.</div>
      <table v-else class="w-full text-sm">
        <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2">No. Pembayaran</th><th class="pb-2">Tanggal</th><th class="pb-2">Metode</th><th class="pb-2 text-right">Jumlah</th><th class="pb-2 text-center">Status</th><th class="pb-2 text-right">Aksi</th></tr></thead>
        <tbody>
          <tr v-for="p in data.payments" :key="p.id" class="border-b border-gray-50">
            <td class="py-2 font-mono text-xs text-gray-600">{{ p.payment_number }}</td>
            <td class="py-2 text-gray-600 text-xs">{{ formatDate(p.payment_date) }}</td>
            <td class="py-2 text-gray-600 text-xs">{{ p.payment_method }}</td>
            <td class="py-2 text-right font-medium">{{ formatCurrency(p.amount) }}</td>
            <td class="py-2 text-center">
              <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', paymentStatusColor[p.status] ?? '']">{{ p.status }}</span>
            </td>
            <td class="py-2 text-right">
              <div v-if="p.status === 'PENDING'" class="flex items-center justify-end gap-1">
                <button class="p-1 rounded text-green-600 hover:bg-green-50" title="Verifikasi" @click="verifyPayment(p.id, 'verify')"><CheckCircleIcon class="w-4 h-4" /></button>
                <button class="p-1 rounded text-red-500 hover:bg-red-50" title="Tolak" @click="verifyPayment(p.id, 'reject')"><XCircleIcon class="w-4 h-4" /></button>
              </div>
              <span v-else-if="p.verified_by" class="text-xs text-gray-400">oleh {{ p.verified_by?.name ?? '-' }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Actions -->
    <div v-if="!['PAID', 'CANCELLED', 'WAIVED'].includes(data.status)" class="flex items-center gap-3">
      <button class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg" @click="cancelInvoice">
        <XCircleIcon class="w-4 h-4 inline mr-1" /> Batalkan Tagihan
      </button>
    </div>
  </div>
</template>
