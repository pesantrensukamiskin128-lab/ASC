<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { EyeIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import api from '@/services/api'

interface TracerStudy {
  id: number
  employment_status: string
  months_to_first_job: number
  satisfaction_score: number
  is_completed: boolean
  created_at: string
  alumni?: { id: number; nim: string; name: string; study_program?: { code: string; name: string } }
  period?: { name: string }
}

const router = useRouter()
const items = ref<TracerStudy[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const filterCompleted = ref('')

const columns = [
  { key: 'alumni', label: 'Alumni' },
  { key: 'prodi', label: 'Program Studi' },
  { key: 'period', label: 'Periode' },
  { key: 'status', label: 'Status Kerja' },
  { key: 'time', label: 'Waktu Kerja' },
  { key: 'satisfaction', label: 'Kepuasan', class: 'text-center' },
  { key: 'completed', label: 'Status', class: 'text-center' },
  { key: 'aksi', label: '', class: 'text-right' },
]

const statusColor: Record<string, string> = {
  BEKERJA: 'bg-green-100 text-green-700',
  WIRAUSAHA: 'bg-orange-100 text-orange-700',
  MELANJUTKAN_STUDI: 'bg-purple-100 text-purple-700',
  BELUM_BEKERJA: 'bg-red-100 text-red-600',
  LAINNYA: 'bg-gray-100 text-gray-600',
}

onMounted(() => load())

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/tracer-studies', {
      params: { is_completed: filterCompleted.value, page }
    })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Tracer Study</h1>
        <p class="text-sm text-gray-500 mt-0.5">Data survei karir dan feedback alumni</p>
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterCompleted" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua</option>
        <option value="1">Selesai</option>
        <option value="0">Belum Selesai</option>
      </select>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3">
          <p class="text-sm font-medium text-gray-900">{{ row.alumni?.name ?? '-' }}</p>
          <p class="text-xs text-gray-500 font-mono">{{ row.alumni?.nim ?? '-' }}</p>
        </td>
        <td class="px-4 py-3 text-xs text-gray-600">{{ row.alumni?.study_program?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-xs text-gray-500">{{ row.period?.name ?? '-' }}</td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusColor[row.employment_status] ?? 'bg-gray-100 text-gray-600']">
            {{ row.employment_status?.replace(/_/g, ' ') ?? '-' }}
          </span>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600">{{ row.months_to_first_job ? row.months_to_first_job + ' bln' : '-' }}</td>
        <td class="px-4 py-3 text-center text-sm">{{ row.satisfaction_score ? '⭐'.repeat(row.satisfaction_score) : '-' }}</td>
        <td class="px-4 py-3 text-center">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', row.is_completed ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
            {{ row.is_completed ? 'Selesai' : 'Draft' }}
          </span>
        </td>
        <td class="px-4 py-3">
          <button v-if="row.alumni" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/alumni/data/${row.alumni.id}`)">
            <EyeIcon class="w-4 h-4" />
          </button>
        </td>
      </template>
    </DataTable>
  </div>
</template>
