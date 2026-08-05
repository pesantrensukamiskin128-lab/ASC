<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, EyeIcon, XCircleIcon, BanknotesIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import DataTable from '@/components/ui/DataTable.vue'
import api from '@/services/api'

interface Invoice {
  id: number; invoice_number: string; invoice_date: string; due_date: string
  total_amount: number; discount_amount: number; scholarship_amount: number
  paid_amount: number; status: string; note: string
  student?: { id: number; nim: string; name: string; study_program?: { code: string } }
  semester?: { name: string; academic_year?: { name: string } }
  items?: { fee_type?: { name: string }; amount: number }[]
}

const router = useRouter()
const toast = useToast()
const items = ref<Invoice[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const semesters = ref<any[]>([])
const filterSemester = ref('')
const filterStatus = ref('')
const search = ref('')

const columns = [
  { key: 'invoice', label: 'No. Invoice' },
  { key: 'student', label: 'Mahasiswa' },
  { key: 'semester', label: 'Semester' },
  { key: 'amount', label: 'Total', class: 'text-right' },
  { key: 'paid', label: 'Dibayar', class: 'text-right' },
  { key: 'status', label: 'Status' },
  { key: 'aksi', label: '', class: 'text-right' },
]

const statusColor: Record<string, string> = {
  UNPAID: 'bg-red-100 text-red-700',
  PARTIAL: 'bg-yellow-100 text-yellow-700',
  PAID: 'bg-green-100 text-green-700',
  OVERDUE: 'bg-red-200 text-red-800',
  CANCELLED: 'bg-gray-100 text-gray-500',
  WAIVED: 'bg-blue-100 text-blue-700',
}

const statusLabel: Record<string, string> = {
  UNPAID: 'Belum Bayar', PARTIAL: 'Sebagian', PAID: 'Lunas',
  OVERDUE: 'Jatuh Tempo', CANCELLED: 'Dibatalkan', WAIVED: 'Dibebaskan',
}

onMounted(async () => {
  try {
    const { data } = await api.get('/semesters', { params: { per_page: 50 } })
    semesters.value = data.data ?? data
  } catch { semesters.value = [] }
  load()
})

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/finance/invoices', {
      params: { semester_id: filterSemester.value, status: filterStatus.value, search: search.value, page }
    })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

async function handleDelete(row: Invoice) {
  if (!confirm(`Hapus tagihan "${row.invoice_number}" untuk ${(row as any).student?.name ?? ''}? Data tidak dapat dikembalikan.`)) return
  try {
    await api.delete(`/finance/invoices/${row.id}`)
    toast.success('Tagihan berhasil dihapus.')
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal menghapus.') }
}

function formatCurrency(n: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n)
}

function outstanding(row: Invoice) {
  return row.total_amount - (row.discount_amount || 0) - (row.scholarship_amount || 0) - (row.paid_amount || 0)
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Tagihan Mahasiswa</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola tagihan pembayaran mahasiswa per semester</p>
      </div>
      <div class="flex items-center gap-2">
        <button class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg" @click="router.push('/keuangan/tagihan/generate')">
          <BanknotesIcon class="w-4 h-4" /> Generate Batch
        </button>
        <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="router.push('/keuangan/tagihan/create')">
          <PlusIcon class="w-4 h-4" /> Buat Tagihan
        </button>
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterSemester" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Semester</option>
        <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Status</option>
        <option value="UNPAID">Belum Bayar</option>
        <option value="PARTIAL">Sebagian</option>
        <option value="PAID">Lunas</option>
        <option value="OVERDUE">Jatuh Tempo</option>
      </select>
      <input v-model="search" type="text" placeholder="Cari NIM/nama..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-52" @input="load()" />
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3">
          <p class="font-mono text-xs text-gray-600">{{ row.invoice_number }}</p>
          <p class="text-xs text-gray-400">{{ new Date(row.due_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}</p>
        </td>
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.student?.name }}</p>
          <p class="text-xs text-gray-500">{{ row.student?.nim }} · {{ row.student?.study_program?.code }}</p>
        </td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.semester?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-right font-medium text-gray-900 text-sm">{{ formatCurrency(row.total_amount) }}</td>
        <td class="px-4 py-3 text-right text-sm">
          <span :class="row.paid_amount > 0 ? 'text-green-600' : 'text-gray-400'">{{ formatCurrency(row.paid_amount) }}</span>
        </td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap', statusColor[row.status] ?? 'bg-gray-100 text-gray-600']">
            {{ statusLabel[row.status] ?? row.status }}
          </span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/keuangan/tagihan/${row.id}`)">
              <EyeIcon class="w-4 h-4" />
            </button>
            <button v-if="row.status === 'UNPAID' || row.status === 'OVERDUE'" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" title="Hapus tagihan" @click="handleDelete(row)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>
</template>
