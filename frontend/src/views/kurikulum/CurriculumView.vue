<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon, EyeIcon } from '@heroicons/vue/24/outline'
import { useRouter } from 'vue-router'
import { useCrud } from '@/composables/useCrud'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface StudyProgram { id: number; name: string; code: string }
interface Curriculum {
  id: number; code: string; name: string; year: number; status: string
  courses_count?: number; study_program?: StudyProgram
}

const router = useRouter()
const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<Curriculum>('/curriculums')
const programs = ref<StudyProgram[]>([])
const filterProgram = ref('')
const modalOpen = ref(false); const editingId = ref<number | null>(null); const saving = ref(false)
const form = reactive({ study_program_id: '', code: '', name: '', year: new Date().getFullYear(), description: '', status: 'Draft' })

const statusColor: Record<string, string> = {
  Draft: 'bg-gray-100 text-gray-600',
  Aktif: 'bg-green-100 text-green-700',
  Nonaktif: 'bg-red-100 text-red-600',
}

const columns = [
  { key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama Kurikulum' },
  { key: 'program', label: 'Program Studi' }, { key: 'year', label: 'Tahun' },
  { key: 'courses', label: 'MK' }, { key: 'status', label: 'Status' },
  { key: 'aksi', label: 'Aksi', class: 'text-right' },
]

onMounted(async () => {
  load()
  const { data } = await api.get('/study-programs/all')
  programs.value = data
})

async function load(page = 1) {
  await fetchAll({ study_program_id: filterProgram.value, page })
}

function openCreate() {
  editingId.value = null
  Object.assign(form, { study_program_id: '', code: '', name: '', year: new Date().getFullYear(), description: '', status: 'Draft' })
  modalOpen.value = true
}

function openEdit(item: Curriculum) {
  editingId.value = item.id
  Object.assign(form, { study_program_id: item.study_program?.id ?? '', code: item.code, name: item.name, year: item.year, status: item.status })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false; load()
  } catch { } finally { saving.value = false }
}

async function handleDelete(item: Curriculum) {
  if (!confirm(`Hapus kurikulum "${item.name}"?`)) return
  await remove(item.id); load()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Kurikulum OBE</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola kurikulum berbasis OBE per program studi</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Kurikulum
      </button>
    </div>

    <div class="flex gap-3">
      <select v-model="filterProgram" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Program Studi</option>
        <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
      </select>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ row.code }}</td>
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.study_program?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-gray-600">{{ row.year }}</td>
        <td class="px-4 py-3 text-center text-gray-600">{{ row.courses_count ?? 0 }} MK</td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusColor[row.status]]">{{ row.status }}</span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" @click="router.push(`/kurikulum/${row.id}`)">
              <EyeIcon class="w-4 h-4" />
            </button>
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Kurikulum' : 'Tambah Kurikulum'" size="lg" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Program Studi <span class="text-red-500">*</span></label>
        <select v-model="form.study_program_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Pilih Program Studi --</option>
          <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.code" required placeholder="KUR-TI-2024" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tahun <span class="text-red-500">*</span></label>
          <input v-model="form.year" type="number" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kurikulum <span class="text-red-500">*</span></label>
        <input v-model="form.name" required placeholder="Kurikulum OBE 2024" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
        <textarea v-model="form.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
        <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option v-for="s in ['Draft','Aktif','Nonaktif']" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
