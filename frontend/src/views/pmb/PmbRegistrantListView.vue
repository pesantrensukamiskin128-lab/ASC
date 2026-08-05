<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { EyeIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useRouter } from 'vue-router'
import { useCrud } from '@/composables/useCrud'
import { useToast } from 'vue-toastification'
import DataTable from '@/components/ui/DataTable.vue'
import api from '@/services/api'

interface Registrant {
  id: number; registration_number: string; full_name: string
  status: string; is_paid: boolean
  study_program_choice1?: { code: string; name: string }
  period?: { name: string }
  path?: { name: string }
}
interface Period { id: number; name: string }

const router = useRouter()
const toast = useToast()
const { items, pagination, loading, fetchAll } = useCrud<Registrant>('/pmb-registrants')
const periods = ref<Period[]>([])
const search = ref(''); const filterPeriod = ref(''); const filterStatus = ref('')

const statuses = ['DRAFT','SUBMITTED','MENUNGGU_VERIFIKASI','TERVERIFIKASI','MENGIKUTI_SELEKSI','LULUS','TIDAK_LULUS','DAFTAR_ULANG','MAHASISWA_BARU']
const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600', SUBMITTED: 'bg-blue-100 text-blue-700',
  MENUNGGU_VERIFIKASI: 'bg-yellow-100 text-yellow-700', TERVERIFIKASI: 'bg-indigo-100 text-indigo-700',
  MENGIKUTI_SELEKSI: 'bg-purple-100 text-purple-700', LULUS: 'bg-green-100 text-green-700',
  TIDAK_LULUS: 'bg-red-100 text-red-600', DAFTAR_ULANG: 'bg-teal-100 text-teal-700',
  MAHASISWA_BARU: 'bg-emerald-100 text-emerald-700',
}

const columns = [
  { key: 'no_reg', label: 'No. Pendaftaran' }, { key: 'name', label: 'Nama' },
  { key: 'period', label: 'Gelombang' }, { key: 'path', label: 'Jalur' },
  { key: 'prodi', label: 'Pilihan 1' }, { key: 'paid', label: 'Bayar' },
  { key: 'status', label: 'Status' }, { key: 'aksi', label: '', class: 'text-right' },
]

onMounted(async () => {
  load()
  const { data } = await api.get('/pmb-periods-all')
  periods.value = data
})

async function load(page = 1) {
  await fetchAll({ search: search.value, period_id: filterPeriod.value, status: filterStatus.value, page })
}

async function handleDelete(row: Registrant) {
  if (!confirm(`Hapus data pendaftar "${row.full_name}"? Tindakan ini tidak dapat dibatalkan.`)) return
  try {
    await api.delete(`/pmb-registrants/${row.id}`)
    toast.success('Data pendaftar berhasil dihapus.')
    load()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal menghapus.')
  }
}
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Pendaftar PMB</h1>
      <p class="text-sm text-gray-500 mt-0.5">Kelola dan verifikasi data pendaftar mahasiswa baru</p>
    </div>

    <div class="flex flex-wrap gap-3">
      <input v-model="search" type="text" placeholder="Cari nama/no pendaftaran..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-56" @input="load()" />
      <select v-model="filterPeriod" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Gelombang</option>
        <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ s.replace(/_/g, ' ') }}</option>
      </select>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ row.registration_number }}</td>
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.full_name }}</td>
        <td class="px-4 py-3 text-gray-500 text-xs">{{ row.period?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-gray-500 text-xs">{{ row.path?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ (row as any).study_program_choice1?.code ?? '-' }}</td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', row.is_paid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600']">
            {{ row.is_paid ? 'Lunas' : 'Belum' }}
          </span>
        </td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap', statusColor[row.status] ?? 'bg-gray-100 text-gray-600']">
            {{ row.status.replace(/_/g, ' ') }}
          </span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/pmb/registrants/${row.id}`)">
              <EyeIcon class="w-4 h-4" />
            </button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>
</template>
