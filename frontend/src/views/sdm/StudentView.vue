<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, PencilIcon, TrashIcon, EyeIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import { useExcel } from '@/composables/useExcel'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ExcelButtons from '@/components/ui/ExcelButtons.vue'
import api from '@/services/api'

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()
const canCreate = auth.hasPermission('mahasiswa.create')

// Bulk selection
const selectedIds = ref<number[]>([])
const bulkDeleting = ref(false)

const isAllSelected = computed(() => items.value.length > 0 && items.value.every((i: any) => selectedIds.value.includes(i.id)))

function toggleSelectAll() {
  if (isAllSelected.value) { selectedIds.value = [] }
  else { selectedIds.value = items.value.map((i: any) => i.id) }
}

function toggleSelect(id: number) {
  const idx = selectedIds.value.indexOf(id)
  if (idx >= 0) selectedIds.value.splice(idx, 1)
  else selectedIds.value.push(id)
}

async function bulkDelete() {
  if (!selectedIds.value.length) return
  if (!confirm(`Hapus ${selectedIds.value.length} mahasiswa yang dipilih?`)) return
  bulkDeleting.value = true
  try {
    const { data } = await api.post('/students/bulk-delete', { ids: selectedIds.value })
    toast.success(data.message); selectedIds.value = []; load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal menghapus.') }
  finally { bulkDeleting.value = false }
}

interface StudyProgram { id: number; name: string; code: string }
interface AcademicYear { id: number; name: string }
interface Lecturer { id: number; name: string }
interface Student {
  id: number; nim: string; name: string; gender: string; status: string
  current_semester: number; entry_year: number; email: string; phone: string
  study_program?: StudyProgram; advisor?: Lecturer; academic_year?: AcademicYear
}

const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<Student>('/students')
const { exporting, importing, importErrors, exportExcel, importExcel } = useExcel('/students')
const programs = ref<StudyProgram[]>([])
const academicYears = ref<AcademicYear[]>([])
const lecturers = ref<Lecturer[]>([])
const search = ref(''); const filterProgram = ref(''); const filterStatus = ref('')
const modalOpen = ref(false); const editingId = ref<number | null>(null); const saving = ref(false)

const form = reactive({
  study_program_id: '', academic_year_id: '', advisor_id: '', nim: '', name: '',
  gender: 'L', birth_place: '', birth_date: '', email: '', phone: '',
  address: '', origin_school: '', entry_year: new Date().getFullYear(), status: 'Aktif',
})

const studentStatuses = ['Aktif', 'Nonaktif', 'Cuti', 'Lulus', 'DO', 'Mengundurkan Diri']

const statusColor: Record<string, string> = {
  Aktif: 'bg-green-100 text-green-700', Cuti: 'bg-yellow-100 text-yellow-700',
  Lulus: 'bg-blue-100 text-blue-700', DO: 'bg-red-100 text-red-700',
  Nonaktif: 'bg-slate-100 text-slate-700', 'Mengundurkan Diri': 'bg-gray-100 text-gray-600',
}

const columns = [
  { key: 'select', label: '', class: 'w-8' },
  { key: 'nim', label: 'NIM' }, { key: 'name', label: 'Nama' }, { key: 'program', label: 'Prodi' },
  { key: 'semester', label: 'Smt' }, { key: 'entry_year', label: 'Angkatan' },
  { key: 'status', label: 'Status' }, { key: 'aksi', label: 'Aksi', class: 'text-right' },
]

onMounted(async () => {
  load()
  const [pRes, aRes, lRes] = await Promise.all([
    api.get('/study-programs/all'),
    api.get('/academic-years/all'),
    api.get('/lecturers/all'),
  ])
  programs.value = pRes.data
  academicYears.value = aRes.data
  lecturers.value = lRes.data
})

async function load(page = 1) { await fetchAll({ search: search.value, study_program_id: filterProgram.value, status: filterStatus.value, page }) }

function openCreate() {
  editingId.value = null
  Object.assign(form, { study_program_id: '', academic_year_id: '', advisor_id: '', nim: '', name: '', gender: 'L', birth_place: '', birth_date: '', email: '', phone: '', address: '', origin_school: '', entry_year: new Date().getFullYear(), status: 'Aktif' })
  modalOpen.value = true
}

function openEdit(item: Student) {
  editingId.value = item.id
  Object.assign(form, { study_program_id: item.study_program?.id ?? '', academic_year_id: item.academic_year?.id ?? '', advisor_id: item.advisor?.id ?? '', nim: item.nim, name: item.name, gender: item.gender, email: item.email, phone: item.phone, entry_year: item.entry_year, status: item.status })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false; load()
  } catch { } finally { saving.value = false }
}

async function handleDelete(item: Student) {
  if (!confirm(`Hapus mahasiswa "${item.name}" (${item.nim})?`)) return
  await remove(item.id); load()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Data Mahasiswa</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola data mahasiswa</p>
      </div>
      <div class="flex items-center gap-2">
        <ExcelButtons
          :exporting="exporting"
          :importing="canCreate ? importing : false"
          :import-errors="importErrors"
          :export-params="{ study_program_id: filterProgram, status: filterStatus }"
          template-type="students"
          :hide-import="!canCreate"
          @export="exportExcel($event, 'mahasiswa.xlsx')"
          @import="importExcel($event, () => load())"
        />
        <button v-if="canCreate" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors" @click="openCreate">
          <PlusIcon class="w-4 h-4" /> Tambah Mahasiswa
        </button>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-3">
      <input v-model="search" type="text" placeholder="Cari NIM atau nama..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-56" @input="load()" />
      <select v-model="filterProgram" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Prodi</option>
        <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }}</option>
      </select>
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="s in studentStatuses" :key="s" :value="s">{{ s }}</option>
      </select>
      <!-- Bulk delete -->
      <button v-if="selectedIds.length" :disabled="bulkDeleting" class="ml-auto px-3 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white text-xs font-medium rounded-lg inline-flex items-center gap-1.5" @click="bulkDelete">
        <TrashIcon class="w-3.5 h-3.5" /> Hapus {{ selectedIds.length }} dipilih
      </button>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #header-select>
        <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded border-gray-300" />
      </template>
      <template #default="{ row }">
        <td class="px-4 py-3"><input type="checkbox" :checked="selectedIds.includes(row.id)" @change="toggleSelect(row.id)" class="rounded border-gray-300" /></td>
        <td class="px-4 py-3 font-mono text-xs font-medium text-gray-700">{{ row.nim }}</td>
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.study_program?.code ?? '-' }}</td>
        <td class="px-4 py-3 text-center text-gray-600">{{ row.current_semester }}</td>
        <td class="px-4 py-3 text-center text-gray-600">{{ row.entry_year }}</td>
        <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusColor[row.status] ?? 'bg-gray-100 text-gray-500']">{{ row.status }}</span></td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" @click="router.push(`/sdm/students/${row.id}`)"><EyeIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Mahasiswa' : 'Tambah Mahasiswa'" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Program Studi <span class="text-red-500">*</span></label>
          <select v-model="form.study_program_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih --</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Akademik Masuk</label>
          <select v-model="form.academic_year_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih --</option>
            <option v-for="a in academicYears" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">NIM <span class="text-red-500">*</span></label>
          <input v-model="form.nim" :disabled="!!editingId" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Masuk</label>
          <input v-model="form.entry_year" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin</label>
          <select v-model="form.gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="L">Laki-laki</option><option value="P">Perempuan</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="s in studentStatuses" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
          <input v-model="form.phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Dosen Wali / Pembimbing Akademik</label>
        <select v-model="form.advisor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Belum ditentukan --</option>
          <option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Asal Sekolah</label>
        <input v-model="form.origin_school" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
