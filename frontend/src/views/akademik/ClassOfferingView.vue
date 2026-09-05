<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, PencilIcon, TrashIcon, ExclamationTriangleIcon, ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useCrud } from '@/composables/useCrud'
import { useToast } from 'vue-toastification'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const auth = useAuthStore()
const canManage = computed(() => auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK') || auth.hasPermission('jadwal.create'))

interface ClassItem {
  id: number; name: string; capacity: number; academic_level: number
  day: string; start_time: string; end_time: string; is_active: boolean
  members_count: number
  course?: { id: number; code: string; name: string; credits: number; study_program?: { code: string } }
  lecturer?: { id: number; full_name?: string; name?: string }
  room?: { id: number; code: string; name: string }
  semester?: { id: number; name: string; academic_year?: { name: string } }
  schedules?: Array<{
    id: number; day: string; start_time: string; end_time: string
    room?: { id: number; code?: string; name: string }
  }>
}

const toast = useToast()
const router = useRouter()
const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<ClassItem>('/classes')

const semesters = ref<any[]>([])
const programs  = ref<any[]>([])
const courses   = ref<any[]>([])
const lecturers = ref<any[]>([])
const rooms     = ref<any[]>([])

const filterSemester = ref('')
const filterProgram  = ref('')
const search         = ref('')

const modalOpen    = ref(false)
const editingId    = ref<number | null>(null)
const saving       = ref(false)
const conflictMsg  = ref<string[]>([])

const form = reactive({
  study_program_id: '', semester_id: '', course_id: '', lecturer_id: '', room_id: '',
  name: '', capacity: 40, academic_level: 1,
  day: '', start_time: '', end_time: '', is_active: true,
})

const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']

const columns = [
  { key: 'name', label: 'Kelas' }, { key: 'course', label: 'Mata Kuliah' },
  { key: 'lecturer', label: 'Dosen' }, { key: 'schedule', label: 'Jadwal' },
  { key: 'room', label: 'Ruangan' }, { key: 'quota', label: 'Kapasitas' },
  { key: 'aksi', label: 'Aksi', class: 'text-right' },
]

onMounted(async () => {
  const [sRes, pRes, lRes, rRes] = await Promise.all([
    api.get('/academic-years/all'),  // Nanti ganti ke semesters
    api.get('/study-programs/all'),
    api.get('/lecturers/all'),
    api.get('/rooms/all'),
  ])
  // Load semesters dari semua tahun akademik (ambil dari endpoint semesters)
  try {
    const { data } = await api.get('/semesters', { params: { per_page: 50 } })
    semesters.value = data.data ?? data
  } catch {
    semesters.value = []
  }
  programs.value  = pRes.data
  lecturers.value = lRes.data
  rooms.value     = rRes.data

  // Default ke semester aktif
  const active = semesters.value.find((s: any) => s.is_active)
  if (active) filterSemester.value = active.id
  load()
})

async function load(page = 1) {
  await fetchAll({ semester_id: filterSemester.value, study_program_id: filterProgram.value, search: search.value, page })
}

async function loadCourses() {
  if (!form.study_program_id) { courses.value = []; return }
  const { data } = await api.get('/courses/all', { params: { study_program_id: form.study_program_id } })
  courses.value = data
}

function openCreate() {
  editingId.value = null
  conflictMsg.value = []
  Object.assign(form, {
    study_program_id: filterProgram.value || '', semester_id: filterSemester.value || '',
    course_id: '', lecturer_id: '', room_id: '',
    name: '', capacity: 40, academic_level: 1,
    day: '', start_time: '', end_time: '', is_active: true,
  })
  if (form.study_program_id) loadCourses()
  modalOpen.value = true
}

function openEdit(item: ClassItem) {
  editingId.value = item.id
  conflictMsg.value = []
  Object.assign(form, {
    study_program_id: item.course?.study_program?.code ? filterProgram.value : '',
    semester_id: item.semester?.id ?? '',
    course_id: item.course?.id ?? '',
    lecturer_id: item.lecturer?.id ?? '',
    room_id: item.room?.id ?? '',
    name: item.name, capacity: item.capacity, academic_level: item.academic_level,
    day: item.day ?? '', start_time: item.start_time ?? '', end_time: item.end_time ?? '',
    is_active: item.is_active,
  })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  conflictMsg.value = []
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false; load()
  } catch (err: any) {
    // Tampilkan konflik jadwal jika ada
    if (err?.response?.data?.conflicts) {
      conflictMsg.value = err.response.data.conflicts
    }
  } finally { saving.value = false }
}

async function handleDelete(item: ClassItem) {
  if (!confirm(`Hapus kelas "${item.name}" (${item.course?.name})?`)) return
  await remove(item.id); load()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Pembagian Kelas & Jadwal</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola kelas, jadwal, dan alokasi dosen per semester</p>
      </div>
      <button v-if="canManage" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Kelas
      </button>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterSemester" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Semester</option>
        <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select v-model="filterProgram" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Prodi</option>
        <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
      </select>
      <input v-model="search" type="text" placeholder="Cari kelas / MK..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-52" @input="load()" />
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 font-semibold text-blue-700 text-sm">{{ row.name }}</td>
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.course?.name }}</p>
          <p class="text-xs text-gray-500">{{ row.course?.code }} · {{ row.course?.credits }} SKS</p>
        </td>
        <td class="px-4 py-3 text-gray-700 text-sm">{{ row.lecturer?.full_name ?? row.lecturer?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-gray-600 text-xs">
          <template v-if="row.schedules?.length">
            <div v-for="s in row.schedules" :key="s.id" class="whitespace-nowrap">
              {{ s.day }}, {{ s.start_time }} – {{ s.end_time }}
              <span v-if="s.room" class="text-gray-400 ml-1">({{ s.room.name }})</span>
            </div>
          </template>
          <span v-else class="text-gray-400">Belum dijadwalkan</span>
        </td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.room?.name ?? '-' }}</td>
        <td class="px-4 py-3">
          <span class="text-sm font-medium" :class="row.members_count >= row.capacity ? 'text-red-600' : 'text-green-600'">
            {{ row.members_count ?? 0 }}/{{ row.capacity }}
          </span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-green-600 hover:bg-green-50" title="Masuk Kelas" @click="router.push(`/perkuliahan/${row.id}`)"><ArrowRightOnRectangleIcon class="w-4 h-4" /></button>
            <button v-if="canManage" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
            <button v-if="canManage" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Kelas' : 'Tambah Kelas'" size="xl" @close="modalOpen = false">
    <!-- Konflik jadwal warning -->
    <div v-if="conflictMsg.length" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
      <div class="flex items-start gap-2">
        <ExclamationTriangleIcon class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
        <div>
          <p class="text-sm font-semibold text-red-700">Jadwal Bentrok!</p>
          <ul class="text-xs text-red-600 mt-1 space-y-0.5">
            <li v-for="(msg, i) in conflictMsg" :key="i">• {{ msg }}</li>
          </ul>
        </div>
      </div>
    </div>

    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Semester <span class="text-red-500">*</span></label>
          <select v-model="form.semester_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih --</option>
            <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Program Studi <span class="text-red-500">*</span></label>
          <select v-model="form.study_program_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="loadCourses()">
            <option value="">-- Pilih --</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Mata Kuliah <span class="text-red-500">*</span></label>
        <select v-model="form.course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Pilih --</option>
          <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }} ({{ c.credits }} SKS)</option>
        </select>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kelas <span class="text-red-500">*</span></label>
          <input v-model="form.name" required placeholder="Kelas A" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kapasitas</label>
          <input v-model="form.capacity" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tingkat</label>
          <input v-model="form.academic_level" type="number" min="1" max="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Dosen Pengampu <span class="text-red-500">*</span></label>
        <select v-model="form.lecturer_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Pilih --</option>
          <option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Hari</label>
          <select v-model="form.day" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih --</option>
            <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jam Mulai</label>
          <input v-model="form.start_time" type="time" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jam Selesai</label>
          <input v-model="form.end_time" type="time" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Ruangan</label>
        <select v-model="form.room_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Belum ditentukan --</option>
          <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.code }} - {{ r.name }}</option>
        </select>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </template>
  </BaseModal>
</template>
