<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'vue-toastification'
import { CheckCircleIcon, XCircleIcon, EyeIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface PaymentItem {
  id: number; payment_number: string; amount: number
  payment_method: string; payment_date: string; reference_number: string
  bank_name: string; account_name: string; receipt_path: string
  status: string; note: string; rejection_reason: string
  student?: { nim: string; name: string; study_program?: { code: string } }
  invoice?: { invoice_number: string }
  verified_by?: { name: string }
}

const toast = useToast()
const items = ref<PaymentItem[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const filterStatus = ref('PENDING')
const search = ref('')

// Detail/preview modal
const detailModal = ref(false)
const selectedPayment = ref<PaymentItem | null>(null)

const baseUrl = computed(() => (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/api\/?$/, ''))

const columns = [
  { key: 'payment', label: 'No. Pembayaran' },
  { key: 'student', label: 'Mahasiswa' },
  { key: 'invoice', label: 'Invoice' },
  { key: 'method', label: 'Metode' },
  { key: 'amount', label: 'Jumlah', class: 'text-right' },
  { key: 'status', label: 'Status' },
  { key: 'aksi', label: '', class: 'text-right' },
]

const statusColor: Record<string, string> = {
  PENDING: 'bg-yellow-100 text-yellow-700', VERIFIED: 'bg-green-100 text-green-700',
  REJECTED: 'bg-red-100 text-red-600', REFUNDED: 'bg-purple-100 text-purple-700',
}

onMounted(() => load())

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/finance/payments', {
      params: { status: filterStatus.value, search: search.value, page }
    })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

async function verify(payment: PaymentItem, action: 'verify' | 'reject') {
  const reason = action === 'reject' ? prompt('Alasan penolakan:') : null
  if (action === 'reject' && !reason) return

  try {
    await api.post(`/finance/payments/${payment.id}/verify`, { action, reason })
    toast.success(action === 'verify' ? 'Pembayaran diverifikasi.' : 'Pembayaran ditolak.')
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

function formatCurrency(n: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n)
}
function formatDate(d: string) {
  return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-'
}

function openDetail(payment: PaymentItem) {
  selectedPayment.value = payment
  detailModal.value = true
}
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Verifikasi Pembayaran</h1>
      <p class="text-sm text-gray-500 mt-0.5">Verifikasi pembayaran yang masuk dari mahasiswa</p>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Status</option>
        <option value="PENDING">Pending</option>
        <option value="VERIFIED">Verified</option>
        <option value="REJECTED">Rejected</option>
      </select>
      <input v-model="search" type="text" placeholder="Cari NIM/nama..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-52" @input="load()" />
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3">
          <p class="font-mono text-xs text-gray-600">{{ row.payment_number }}</p>
          <p class="text-xs text-gray-400">{{ formatDate(row.payment_date) }}</p>
        </td>
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.student?.name }}</p>
          <p class="text-xs text-gray-500">{{ row.student?.nim }}</p>
        </td>
        <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ row.invoice?.invoice_number }}</td>
        <td class="px-4 py-3 text-sm text-gray-600">{{ row.payment_method }}
          <p v-if="row.reference_number" class="text-xs text-gray-400">Ref: {{ row.reference_number }}</p>
        </td>
        <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ formatCurrency(row.amount) }}</td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusColor[row.status] ?? '']">{{ row.status }}</span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" title="Detail" @click="openDetail(row)"><EyeIcon class="w-4 h-4" /></button>
            <template v-if="row.status === 'PENDING'">
              <button class="p-1.5 rounded-lg text-green-600 hover:bg-green-50" title="Verifikasi" @click="verify(row, 'verify')"><CheckCircleIcon class="w-4 h-4" /></button>
              <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" title="Tolak" @click="verify(row, 'reject')"><XCircleIcon class="w-4 h-4" /></button>
            </template>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <!-- Modal Detail Pembayaran -->
  <BaseModal :open="detailModal" title="Detail Pembayaran" size="lg" @close="detailModal = false">
    <div v-if="selectedPayment" class="space-y-4">
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div><span class="text-xs text-gray-400">No. Pembayaran</span><p class="font-mono font-medium">{{ selectedPayment.payment_number }}</p></div>
        <div><span class="text-xs text-gray-400">Status</span><p><span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusColor[selectedPayment.status]]">{{ selectedPayment.status }}</span></p></div>
        <div><span class="text-xs text-gray-400">Mahasiswa</span><p class="font-medium">{{ selectedPayment.student?.name }}</p><p class="text-xs text-gray-500">{{ selectedPayment.student?.nim }}</p></div>
        <div><span class="text-xs text-gray-400">Invoice</span><p class="font-mono text-xs">{{ selectedPayment.invoice?.invoice_number }}</p></div>
        <div><span class="text-xs text-gray-400">Jumlah</span><p class="font-bold text-lg">{{ formatCurrency(selectedPayment.amount) }}</p></div>
        <div><span class="text-xs text-gray-400">Tanggal Bayar</span><p>{{ formatDate(selectedPayment.payment_date) }}</p></div>
        <div><span class="text-xs text-gray-400">Metode</span><p>{{ selectedPayment.payment_method }}</p></div>
        <div><span class="text-xs text-gray-400">No. Referensi</span><p class="font-mono text-xs">{{ selectedPayment.reference_number || '-' }}</p></div>
        <div><span class="text-xs text-gray-400">Nama Bank</span><p>{{ selectedPayment.bank_name || '-' }}</p></div>
        <div><span class="text-xs text-gray-400">Nama Rekening</span><p>{{ selectedPayment.account_name || '-' }}</p></div>
      </div>

      <!-- Bukti Pembayaran -->
      <div class="border border-gray-200 rounded-lg p-4">
        <p class="text-xs font-medium text-gray-700 mb-2">Bukti Pembayaran</p>
        <div v-if="selectedPayment.receipt_path">
          <div v-if="selectedPayment.receipt_path.endsWith('.pdf')" class="bg-gray-50 rounded-lg p-4 text-center">
            <p class="text-sm text-gray-600 mb-2">File PDF</p>
            <a :href="`${baseUrl}/storage/${selectedPayment.receipt_path}`" target="_blank" rel="noopener" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg inline-block">Buka PDF</a>
          </div>
          <div v-else>
            <img :src="`${baseUrl}/storage/${selectedPayment.receipt_path}`" alt="Bukti Pembayaran" class="w-full max-h-80 object-contain rounded-lg border" />
            <a :href="`${baseUrl}/storage/${selectedPayment.receipt_path}`" target="_blank" rel="noopener" class="text-xs text-blue-600 hover:underline mt-2 inline-block">Buka di tab baru ↗</a>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400 italic">Tidak ada bukti pembayaran yang diupload.</p>
      </div>

      <!-- Catatan -->
      <div v-if="selectedPayment.note">
        <p class="text-xs font-medium text-gray-700">Catatan:</p>
        <p class="text-sm text-gray-600 mt-0.5">{{ selectedPayment.note }}</p>
      </div>

      <!-- Aksi verifikasi dari modal -->
      <div v-if="selectedPayment.status === 'PENDING'" class="flex gap-2 pt-3 border-t border-gray-200">
        <button class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg" @click="verify(selectedPayment, 'verify'); detailModal = false">
          ✓ Verifikasi Pembayaran
        </button>
        <button class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg" @click="verify(selectedPayment, 'reject'); detailModal = false">
          ✗ Tolak Pembayaran
        </button>
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="detailModal = false">Tutup</button>
    </template>
  </BaseModal>
</template>
