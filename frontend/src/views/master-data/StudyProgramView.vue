<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useCrud, cleanPayload } from '@/composables/useCrud'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface Faculty { id: number; name: string; code: string }
interface Lecturer { id: number; name: string; nidn: string }
interface StudyProgram {
  id: number; code: string; name: string; degree: string; level: string
  accreditation: string; status: boolean
  faculty?: Faculty; head_lecturer?: Lecturer; students_count?: number
}

const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<StudyProgram>('/study-programs')
const faculties = ref<Faculty[]>([])
const lecturers = ref<Lecturer[]>([])
const search = ref(''); const modalOpen = ref(false); const editingId = ref<number | null>(null); const saving = ref(false)
const form = reactive({ faculty_id: '', code: '', name: '', degree: '', level: 'S1', accreditation: 'B', head_lecturer_id: '', status: true })

const columns = [
  { key: 'code', label: 'Kode' }, { key: 'name', label: 'Program Studi' },
  { key: 'faculty', label: 'Fakultas' }, { key: 'level', label: 'Jenjang' },
  { key: 'accreditation', label: 'Akreditasi' }, { key: 'head', label: 'Kaprodi' },
  { key: 'status', label: 'Status' }, { key: 'aksi', label: 'Aksi', class: 'text-right' },
]

onMounted(async () => {
  load()
  const [fRes, lRes] = await Promise.all([api.get('/faculties/all'), api.get('/lecturers/all')])
  faculties.value = fRes.data
  lecturers.value = lRes.data
})

async function load(page = 1) { await fetchAll({ search: search.value, page }) }

function openCreate() {
  editingId.value = null
  Object.assign(form, { faculty_id: '', code: '', name: '', degree: '', level: 'S1', accreditation: 'B', head_lecturer_id: '', status: true })
  modalOpen.value = true
}

function openEdit(item: StudyProgram) {
  editingId.value = item.id
  Object.assign(form, { faculty_id: item.faculty?.id ?? '', code: item.code, name: item.name, degree: item.degree, level: item.level, accreditation: item.accreditation, head_lecturer_id: item.head_lecturer?.id ?? '', status: item.status })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false; load()
  } catch { } finally { saving.value = false }
}

async function handleDelete(item: StudyProgram) {
  if (!confirm(`Hapus program studi "${item.name}"?`)) return
  await remove(item.id); load()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Program Studi</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola data program studi</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Prodi
      </button>
    </div>
    <input v-model="search" type="text" placeholder="Cari nama atau kode..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64" @input="load()" />

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ row.code }}</td>
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.faculty?.name ?? '-' }}</td>
        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">{{ row.level }}</span></td>
        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ row.accreditation }}</span></td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.head_lecturer?.name ?? '-' }}</td>
        <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', row.status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">{{ row.status ? 'Aktif' : 'Nonaktif' }}</span></td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Program Studi' : 'Tambah Program Studi'" size="lg" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Fakultas <span class="text-red-500">*</span></label>
        <select v-model="form.faculty_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Pilih Fakultas --</option>
          <option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.code }} - {{ f.name }}</option>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.code" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Gelar Lulusan</label>
          <input v-model="form.degree" placeholder="S.Kom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Program Studi <span class="text-red-500">*</span></label>
        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenjang</label>
          <select v-model="form.level" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="l in ['D3','S1','S2','S3','Profesi']" :key="l" :value="l">{{ l }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Akreditasi</label>
          <select v-model="form.accreditation" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="a in ['Unggul','A','Baik Sekali','B','Baik','C']" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Ketua Program Studi (Kaprodi)</label>
        <select v-model="form.head_lecturer_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Belum ditentukan --</option>
          <option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }} ({{ l.nidn }})</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
        <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option :value="true">Aktif</option><option :value="false">Nonaktif</option>
        </select>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
