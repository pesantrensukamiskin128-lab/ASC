<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import { EyeIcon, FunnelIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface AuditLog {
  id: number; user_id: number; action: string; model_type: string | null
  model_id: number | null; old_values: any; new_values: any
  ip_address: string; user_agent: string; created_at: string
  user?: { id: number; name: string; email: string }
}

const toast = useToast()
const items = ref<AuditLog[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)

// Filters
const search = ref('')
const filterAction = ref('')
const filterModelType = ref('')
const filterDateFrom = ref('')
const filterDateTo = ref('')
const actions = ref<string[]>([])
const modelTypes = ref<string[]>([])

// Detail modal
const detailModal = ref(false)
const detailItem = ref<AuditLog | null>(null)

const columns = [
  { key: 'time', label: 'Waktu' },
  { key: 'user', label: 'Pengguna' },
  { key: 'action', label: 'Aksi' },
  { key: 'model', label: 'Data' },
  { key: 'ip', label: 'IP Address' },
  { key: 'detail', label: '', class: 'text-right' },
]

const actionColors: Record<string, string> = {
  CREATE: 'bg-green-100 text-green-700',
  UPDATE: 'bg-blue-100 text-blue-700',
  DELETE: 'bg-red-100 text-red-700',
  LOGIN: 'bg-purple-100 text-purple-700',
  LOGOUT: 'bg-gray-100 text-gray-600',
  APPROVE: 'bg-teal-100 text-teal-700',
  REJECT: 'bg-orange-100 text-orange-700',
}

onMounted(async () => {
  load()
  try {
    const [actRes, mtRes] = await Promise.all([
      api.get('/audit-logs/actions'),
      api.get('/audit-logs/model-types'),
    ])
    actions.value = actRes.data
    modelTypes.value = mtRes.data
  } catch { /* silent */ }
})

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/audit-logs', {
      params: {
        search: search.value, action: filterAction.value,
        model_type: filterModelType.value,
        date_from: filterDateFrom.value, date_to: filterDateTo.value, page,
      },
    })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal memuat audit log.')
  } finally { loading.value = false }
}

function openDetail(item: AuditLog) {
  detailItem.value = item
  detailModal.value = true
}

function formatDate(d: string) {
  return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function shortModel(type: string | null) {
  if (!type) return '-'
  return type.split('\\').pop() ?? type
}

function resetFilters() {
  search.value = ''; filterAction.value = ''; filterModelType.value = ''
  filterDateFrom.value = ''; filterDateTo.value = ''
  load()
}
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Audit Log</h1>
      <p class="text-sm text-gray-500 mt-0.5">Riwayat aktivitas dan perubahan data di sistem</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <div class="flex items-center gap-2 mb-3">
        <FunnelIcon class="w-4 h-4 text-gray-400" />
        <span class="text-xs font-semibold text-gray-500 uppercase">Filter</span>
      </div>
      <div class="flex flex-wrap gap-3">
        <input v-model="search" type="text" placeholder="Cari user/aksi..." class="px-3 py-2 rounded-lg border border-gray-300 text-sm w-48" @input="load()" />
        <select v-model="filterAction" class="px-3 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
          <option value="">Semua Aksi</option>
          <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
        </select>
        <select v-model="filterModelType" class="px-3 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
          <option value="">Semua Model</option>
          <option v-for="m in modelTypes" :key="m" :value="m">{{ m }}</option>
        </select>
        <input v-model="filterDateFrom" type="date" class="px-3 py-2 rounded-lg border border-gray-300 text-sm" @change="load()" />
        <input v-model="filterDateTo" type="date" class="px-3 py-2 rounded-lg border border-gray-300 text-sm" @change="load()" />
        <button class="px-3 py-2 text-xs text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg" @click="resetFilters">Reset</button>
      </div>
    </div>

    <!-- Table -->
    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(row.created_at) }}</td>
        <td class="px-4 py-3">
          <p class="text-sm font-medium text-gray-900">{{ row.user?.name ?? 'System' }}</p>
          <p class="text-xs text-gray-400">{{ row.user?.email ?? '-' }}</p>
        </td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', actionColors[row.action] ?? 'bg-gray-100 text-gray-600']">
            {{ row.action }}
          </span>
        </td>
        <td class="px-4 py-3">
          <p class="text-xs text-gray-700 font-medium">{{ shortModel(row.model_type) }}</p>
          <p v-if="row.model_id" class="text-xs text-gray-400">ID: {{ row.model_id }}</p>
        </td>
        <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ row.ip_address ?? '-' }}</td>
        <td class="px-4 py-3 text-right">
          <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openDetail(row)">
            <EyeIcon class="w-4 h-4" />
          </button>
        </td>
      </template>
    </DataTable>
  </div>

  <!-- Detail Modal -->
  <BaseModal :open="detailModal" title="Detail Audit Log" size="xl" @close="detailModal = false">
    <div v-if="detailItem" class="space-y-4">
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div><span class="text-gray-400 text-xs">Waktu</span><p class="text-gray-800">{{ formatDate(detailItem.created_at) }}</p></div>
        <div><span class="text-gray-400 text-xs">Pengguna</span><p class="text-gray-800">{{ detailItem.user?.name ?? 'System' }}</p></div>
        <div><span class="text-gray-400 text-xs">Aksi</span><p><span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', actionColors[detailItem.action] ?? 'bg-gray-100 text-gray-600']">{{ detailItem.action }}</span></p></div>
        <div><span class="text-gray-400 text-xs">Model</span><p class="text-gray-800">{{ shortModel(detailItem.model_type) }} #{{ detailItem.model_id ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">IP Address</span><p class="text-gray-800 font-mono text-xs">{{ detailItem.ip_address ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">User Agent</span><p class="text-gray-700 text-xs truncate" :title="detailItem.user_agent">{{ detailItem.user_agent ?? '-' }}</p></div>
      </div>

      <!-- Old Values -->
      <div v-if="detailItem.old_values" class="border border-red-100 rounded-lg p-3">
        <p class="text-xs font-semibold text-red-600 mb-2">Data Sebelum (Old Values)</p>
        <pre class="text-xs text-gray-700 bg-red-50 rounded p-2 overflow-x-auto max-h-48">{{ JSON.stringify(detailItem.old_values, null, 2) }}</pre>
      </div>

      <!-- New Values -->
      <div v-if="detailItem.new_values" class="border border-green-100 rounded-lg p-3">
        <p class="text-xs font-semibold text-green-600 mb-2">Data Sesudah (New Values)</p>
        <pre class="text-xs text-gray-700 bg-green-50 rounded p-2 overflow-x-auto max-h-48">{{ JSON.stringify(detailItem.new_values, null, 2) }}</pre>
      </div>

      <div v-if="!detailItem.old_values && !detailItem.new_values" class="text-center text-gray-400 text-sm py-4">
        Tidak ada detail perubahan data.
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="detailModal = false">Tutup</button>
    </template>
  </BaseModal>
</template>
