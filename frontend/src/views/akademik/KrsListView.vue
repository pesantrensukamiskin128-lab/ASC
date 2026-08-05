<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, EyeIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { useCrud } from '@/composables/useCrud'
import DataTable from '@/components/ui/DataTable.vue'
import api from '@/services/api'

interface Krs {
  id: number; total_credits: number; status: string
  student?: { id: number; nim: string; name: string; study_program?: { name: string; code: string } }
  semester?: { id: number; name: string; academic_year?: { name: string } }
  advisor?: { id: number; full_name?: string; name?: string }
}

const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()
const { items, pagination, loading, fetchAll } = useCrud<Krs>('/krs')

const semesters    = ref<any[]>([])
const filterSem    = ref('')
const filterStatus = ref('')
const search       = ref('')
const creating     = ref(false)

const statusColor: Record<string, string> = {
  DRAFT:     'bg-gray-100 text-gray-600',
  SUBMITTED: 'bg-yellow-100 text-yellow-700',
  APPROVED:  'bg-green-100 text-green-700',
  REJECTED:  'bg-red-100 text-red-600',
  CANCELLED: 'bg-gray-100 text-gray-400',
}

const columns = [
  { key: 'student', label: 'Mahasiswa' }, { key: 'semester', label: 'Semester' },
  { key: 'credits', label: 'SKS' }, { key: 'advisor', label: 'Dosen Wali' },
  { key: 'status', label: 'Status' }, { key: 'aksi', label: '', class: 'text-right' },
]

const isMahasiswa = auth.hasRole('MAHASISWA')

onMounted(async () => {
  try {
    const { data } = await api.get('/semesters', { params: { per_page: 50 } })
    semesters.value = data.data ?? data
    const active = semesters.value.find((s: any) => s.is_active)
    if (active) filterSem.value = active.id
  } catch {}
  load()
})

async function load(page = 1) {
  await fetchAll({ semester_id: filterSem.value, status: filterStatus.value, search: search.value, page })
}

async function handleDeleteKrs(row: any) {
  if (!confirm(`Hapus KRS mahasiswa "${row.student?.name ?? ''}"? Data tidak dapat dikembalikan.`)) return
  try {
    await api.delete(`/krs/${row.id}`)
    toast.success('KRS berhasil dihapus.')
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal menghapus.') }
}

async function createKrs() {
  if (!filterSem.value) { toast.warning('Pilih semester terlebih dahulu.'); return }
  creating.value = true
  try {
    const { data } = await api.post('/krs', { student_id: (auth.user as any)?.id, semester_id: filterSem.value })
    toast.success(data.message)
    router.push(`/akademik/krs/${data.data.id}`)
  } catch (err: any) {
    toast.error(err?.response?.data?.message || 'Gagal membuat KRS.')
  } finally { creating.value = false }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">KRS & Perwalian</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kartu Rencana Studi mahasiswa</p>
      </div>
      <button v-if="isMahasiswa" :disabled="creating"
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg"
        @click="createKrs()">
        <PlusIcon class="w-4 h-4" /> {{ creating ? 'Membuat...' : 'Buat KRS' }}
      </button>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterSem" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Semester</option>
        <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="s in ['DRAFT','SUBMITTED','APPROVED','REJECTED']" :key="s" :value="s">{{ s }}</option>
      </select>
      <input v-if="!isMahasiswa" v-model="search" type="text" placeholder="Cari NIM/nama..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-52" @input="load()" />
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.student?.name }}</p>
          <p class="text-xs text-gray-500">{{ row.student?.nim }} · {{ row.student?.study_program?.code }}</p>
        </td>
        <td class="px-4 py-3 text-gray-700 text-sm">{{ row.semester?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-center">
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-blue-50 text-blue-700 font-bold text-sm">{{ row.total_credits }}</span>
        </td>
        <td class="px-4 py-3 text-gray-600 text-sm">{{ row.advisor?.full_name ?? row.advisor?.name ?? '-' }}</td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusColor[row.status] ?? 'bg-gray-100']">{{ row.status }}</span>
        </td>
        <td class="px-4 py-3 text-right">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/akademik/krs/${row.id}`)">
              <EyeIcon class="w-4 h-4" />
            </button>
            <button v-if="row.status !== 'APPROVED' || auth.hasRole('SUPER_ADMIN')" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" title="Hapus KRS" @click="handleDeleteKrs(row)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>
</template>
