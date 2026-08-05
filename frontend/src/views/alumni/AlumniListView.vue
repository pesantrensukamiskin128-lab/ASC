<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { PlusIcon, EyeIcon, TrashIcon, PencilIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface Alumni {
  id: number; nim: string; name: string; email: string; phone: string
  entry_year: number; graduation_year: number; gpa: number; predicate: string
  study_program?: { id: number; code: string; name: string }
  latest_employment?: { company_name: string; position: string; is_current: boolean }
}
interface StudyProgram { id: number; code: string; name: string }

const router = useRouter()
const toast = useToast()

const items = ref<Alumni[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const exporting = ref(false)

const programs = ref<StudyProgram[]>([])
const search = ref('')
const filterProgram = ref('')
const filterYear = ref('')

// Modal
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const students = ref<any[]>([])
const searchStudent = ref('')

const form = reactive({
  student_id: '', study_program_id: '', nim: '', name: '', email: '', phone: '',
  entry_year: new Date().getFullYear() - 4, graduation_year: new Date().getFullYear(),
  graduation_date: '', gpa: '', thesis_title: '', predicate: '', address: '', city: '', province: '',
})

const columns = [
  { key: 'nim', label: 'NIM' },
  { key: 'name', label: 'Nama' },
  { key: 'prodi', label: 'Program Studi' },
  { key: 'year', label: 'Angkatan' },
  { key: 'grad_year', label: 'Lulus' },
  { key: 'gpa', label: 'IPK', class: 'text-center' },
  { key: 'job', label: 'Pekerjaan Terkini' },
  { key: 'aksi', label: '', class: 'text-right' },
]

const predicates = ['Cum Laude', 'Sangat Memuaskan', 'Memuaskan', 'Cukup']
const yearOptions = Array.from({ length: 20 }, (_, i) => new Date().getFullYear() - i)

onMounted(async () => {
  load()
  const { data } = await api.get('/study-programs/all')
  programs.value = data
})

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/alumni', {
      params: { search: search.value, study_program_id: filterProgram.value, graduation_year: filterYear.value, page }
    })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, {
    student_id: '', study_program_id: '', nim: '', name: '', email: '', phone: '',
    entry_year: new Date().getFullYear() - 4, graduation_year: new Date().getFullYear(),
    graduation_date: '', gpa: '', thesis_title: '', predicate: '', address: '', city: '', province: '',
  })
  modalOpen.value = true
}

function openEdit(item: Alumni) {
  editingId.value = item.id
  Object.assign(form, {
    study_program_id: item.study_program?.id ?? '', nim: item.nim, name: item.name,
    email: item.email ?? '', phone: item.phone ?? '', entry_year: item.entry_year,
    graduation_year: item.graduation_year, gpa: item.gpa ?? '', predicate: item.predicate ?? '',
  })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    if (editingId.value) {
      await api.put(`/alumni/${editingId.value}`, form)
      toast.success('Data alumni diupdate.')
    } else {
      await api.post('/alumni', form)
      toast.success('Data alumni berhasil ditambahkan.')
    }
    modalOpen.value = false; load()
  } catch (e: any) {
    const msgs = e?.response?.data?.errors
    if (msgs) { toast.error(Object.values(msgs).flat()[0] as string) }
    else { toast.error(e?.response?.data?.message ?? 'Gagal menyimpan.') }
  } finally { saving.value = false }
}

async function handleDelete(item: Alumni) {
  if (!confirm(`Hapus data alumni "${item.name}"? Semua data terkait (pekerjaan, tracer study) akan dihapus.`)) return
  try {
    await api.delete(`/alumni/${item.id}`)
    toast.success('Data alumni dihapus.')
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function handleExport() {
  exporting.value = true
  try {
    const res = await api.get('/alumni/export', {
      params: { study_program_id: filterProgram.value, graduation_year: filterYear.value },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = `alumni-${new Date().toISOString().slice(0, 10)}.xlsx`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
    toast.success('Export berhasil.')
  } catch { toast.error('Gagal export.') }
  finally { exporting.value = false }
}

async function searchStudents() {
  if (searchStudent.value.length < 2) return
  try {
    const { data } = await api.get('/students', { params: { search: searchStudent.value, status: 'Lulus', per_page: 10 } })
    students.value = data.data
  } catch { /* silent */ }
}

function selectStudent(s: any) {
  form.student_id = s.id
  form.nim = s.nim
  form.name = s.name
  form.email = s.email ?? ''
  form.phone = s.phone ?? ''
  form.study_program_id = s.study_program_id
  form.entry_year = s.entry_year ?? form.entry_year
  students.value = []
  searchStudent.value = ''
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Data Alumni</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola data alumni dan riwayat karir</p>
      </div>
      <div class="flex items-center gap-2">
        <button :disabled="exporting" class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg" @click="handleExport">
          <ArrowDownTrayIcon class="w-4 h-4" />
          <span>{{ exporting ? 'Mengunduh...' : 'Export' }}</span>
        </button>
        <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
          <PlusIcon class="w-4 h-4" /> Tambah Alumni
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3">
      <input v-model="search" type="text" placeholder="Cari nama/NIM..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm w-56" @input="load()" />
      <select v-model="filterProgram" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Program Studi</option>
        <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
      </select>
      <select v-model="filterYear" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Tahun Lulus</option>
        <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
      </select>
    </div>

    <!-- Table -->
    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ row.nim }}</td>
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.study_program?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-gray-500 text-sm text-center">{{ row.entry_year }}</td>
        <td class="px-4 py-3 text-gray-700 text-sm text-center font-medium">{{ row.graduation_year }}</td>
        <td class="px-4 py-3 text-center">
          <span v-if="row.gpa" class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold" :class="row.gpa >= 3.5 ? 'bg-green-100 text-green-700' : row.gpa >= 3.0 ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700'">
            {{ Number(row.gpa).toFixed(2) }}
          </span>
          <span v-else class="text-gray-400 text-xs">-</span>
        </td>
        <td class="px-4 py-3">
          <template v-if="row.latest_employment">
            <p class="text-xs text-gray-800 font-medium">{{ row.latest_employment.position }}</p>
            <p class="text-xs text-gray-500">{{ row.latest_employment.company_name }}</p>
          </template>
          <span v-else class="text-xs text-gray-400">-</span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/alumni/data/${row.id}`)"><EyeIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-50" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <!-- Modal Create/Edit -->
  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Data Alumni' : 'Tambah Alumni'" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <!-- Cari dari mahasiswa -->
      <div v-if="!editingId" class="bg-blue-50 border border-blue-200 rounded-lg p-3">
        <p class="text-xs font-medium text-blue-700 mb-2">Ambil dari data mahasiswa (opsional)</p>
        <div class="flex items-center gap-2">
          <input v-model="searchStudent" placeholder="Ketik nama/NIM mahasiswa lulus..." class="flex-1 px-3 py-1.5 border border-blue-200 rounded-lg text-sm" @input="searchStudents" />
        </div>
        <div v-if="students.length" class="mt-2 max-h-32 overflow-y-auto space-y-1">
          <button v-for="s in students" :key="s.id" type="button" class="w-full text-left px-3 py-1.5 text-xs bg-white border border-gray-200 rounded hover:bg-blue-50" @click="selectStudent(s)">
            <strong>{{ s.nim }}</strong> — {{ s.name }}
          </button>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Program Studi <span class="text-red-500">*</span></label>
          <select v-model="form.study_program_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">-- Pilih --</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">NIM <span class="text-red-500">*</span></label>
          <input v-model="form.nim" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label><input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label><input v-model="form.phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tahun Masuk <span class="text-red-500">*</span></label><input v-model.number="form.entry_year" required type="number" min="2000" max="2030" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tahun Lulus <span class="text-red-500">*</span></label><input v-model.number="form.graduation_year" required type="number" min="2000" max="2030" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">IPK</label><input v-model="form.gpa" type="number" step="0.01" min="0" max="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Wisuda</label><input v-model="form.graduation_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Predikat</label><select v-model="form.predicate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">-- Pilih --</option><option v-for="p in predicates" :key="p" :value="p">{{ p }}</option></select></div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Judul Skripsi/Tesis</label>
        <textarea v-model="form.thesis_title" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label><input v-model="form.address" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Kota</label><input v-model="form.city" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Provinsi</label><input v-model="form.province" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
