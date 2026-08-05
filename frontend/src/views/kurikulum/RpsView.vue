<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { PlusIcon, EyeIcon, TrashIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCrud } from '@/composables/useCrud'
import { useToast } from 'vue-toastification'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface Rps {
  id: number; code: string; status: string
  course?: { id: number; name: string; code: string; credits: number }
  academic_year?: { id: number; name: string }
  lecturer?: { id: number; name: string }
}

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()
const { items, pagination, loading, fetchAll, create, remove } = useCrud<Rps>('/rpkps')

// Data sumber
const courses = ref<any[]>([])
const curriculums = ref<any[]>([])
const academicYears = ref<any[]>([])
const lecturers = ref<any[]>([])
const filterStatus = ref('')

// Data khusus dosen (dari penugasan)
const myLecturerId = ref<number | null>(null)
const myCourses = ref<any[]>([])

const modalOpen = ref(false)
const saving = ref(false)
const form = reactive({
  course_id: '', curriculum_id: '', academic_year_id: '',
  semester_id: '', lecturer_id: '', course_description: '',
})

const isAdmin = computed(() => auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK'))

const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600',
  DIAJUKAN: 'bg-yellow-100 text-yellow-700',
  DALAM_PEMERIKSAAN: 'bg-blue-100 text-blue-700',
  REVISI: 'bg-orange-100 text-orange-700',
  DISETUJUI: 'bg-green-100 text-green-700',
  DIKUNCI: 'bg-indigo-100 text-indigo-700',
  DIARSIPKAN: 'bg-gray-100 text-gray-500',
}

const columns = [
  { key: 'code', label: 'Kode' }, { key: 'course', label: 'Mata Kuliah' },
  { key: 'year', label: 'Tahun Akademik' }, { key: 'lecturer', label: 'Dosen' },
  { key: 'status', label: 'Status' }, { key: 'aksi', label: 'Aksi', class: 'text-right' },
]

onMounted(async () => {
  load()

  if (isAdmin.value) {
    // Admin: load semua data untuk dropdown
    const [cRes, curRes, aRes, lRes] = await Promise.all([
      api.get('/courses/all'),
      api.get('/curriculums', { params: { per_page: 100 } }),
      api.get('/academic-years/all'),
      api.get('/lecturers/all'),
    ])
    courses.value = cRes.data
    curriculums.value = curRes.data?.data ?? curRes.data
    academicYears.value = aRes.data
    lecturers.value = lRes.data
  } else {
    // Dosen: load hanya MK yang ditugaskan + data pendukung
    const [myRes, curRes, aRes] = await Promise.all([
      api.get('/rpkps/my-courses'),
      api.get('/curriculums', { params: { per_page: 100 } }),
      api.get('/academic-years/all'),
    ])
    myCourses.value = myRes.data.courses ?? []
    myLecturerId.value = myRes.data.lecturer_id ?? null
    curriculums.value = curRes.data?.data ?? curRes.data ?? []
    academicYears.value = aRes.data ?? []
  }
})

async function load(page = 1) { await fetchAll({ status: filterStatus.value, page }) }

function openCreate() {
  if (isAdmin.value) {
    Object.assign(form, { course_id: '', curriculum_id: '', academic_year_id: '', semester_id: '', lecturer_id: '', course_description: '' })
  } else {
    // Dosen: auto-fill lecturer_id dan tahun akademik aktif
    const activeYear = academicYears.value.find((a: any) => a.is_active) ?? academicYears.value[0]
    const activeCurriculum = curriculums.value.length > 0 ? curriculums.value[0] : null
    Object.assign(form, {
      course_id: '',
      curriculum_id: activeCurriculum?.id ?? '',
      academic_year_id: activeYear?.id ?? '',
      semester_id: '',
      lecturer_id: myLecturerId.value ?? '',
      course_description: '',
    })
  }
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    await create(form)
    modalOpen.value = false; load()
  } catch { } finally { saving.value = false }
}

async function handleSubmit(item: Rps) {
  if (!confirm(`Submit RPKPS "${item.code}" untuk review?`)) return
  try {
    await api.post(`/rpkps/${item.id}/submit`)
    toast.success('RPKPS berhasil disubmit.')
    load()
  } catch (err: any) {
    toast.error(err?.response?.data?.message || 'Gagal submit.')
  }
}

async function handleDelete(item: Rps) {
  if (!confirm(`Hapus RPKPS "${item.code}"?`)) return
  await remove(item.id); load()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">RPKPS / RPS Digital</h1>
        <p class="text-sm text-gray-500 mt-0.5">Rencana Pembelajaran Semester</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Buat RPS
      </button>
    </div>

    <div class="flex gap-3">
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="s in ['DRAFT','DIAJUKAN','DALAM_PEMERIKSAAN','REVISI','DISETUJUI','DIKUNCI']" :key="s" :value="s">{{ s.replace(/_/g, ' ') }}</option>
      </select>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ row.code }}</td>
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.course?.name }}</p>
          <p class="text-xs text-gray-500">{{ row.course?.code }} · {{ row.course?.credits }} SKS</p>
        </td>
        <td class="px-4 py-3 text-gray-600 text-sm">{{ row.academic_year?.name }}</td>
        <td class="px-4 py-3 text-gray-600 text-sm">{{ row.lecturer?.name }}</td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusColor[row.status] ?? 'bg-gray-100 text-gray-600']">{{ row.status.replace(/_/g, ' ') }}</span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" title="Detail" @click="router.push(`/rps/${row.id}`)">
              <EyeIcon class="w-4 h-4" />
            </button>
            <button v-if="row.status === 'DRAFT' || row.status === 'REVISI'" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" title="Submit" @click="handleSubmit(row)">
              <PaperAirplaneIcon class="w-4 h-4" />
            </button>
            <button v-if="row.status === 'DRAFT'" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <!-- Modal Buat RPKPS -->
  <BaseModal :open="modalOpen" title="Buat RPKPS Baru" size="lg" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">

      <!-- ========== FORM UNTUK DOSEN ========== -->
      <template v-if="!isAdmin">
        <!-- Info: MK yang ditugaskan -->
        <div v-if="myCourses.length === 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <p class="text-sm text-yellow-800 font-medium">Anda belum ditugaskan mengampu mata kuliah apapun.</p>
          <p class="text-xs text-yellow-600 mt-1">Hubungi Ketua Program Studi atau Admin untuk penugasan mengajar.</p>
        </div>

        <div v-else>
          <!-- Mata Kuliah (hanya yang ditugaskan) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mata Kuliah yang Diampu <span class="text-red-500">*</span></label>
            <select v-model="form.course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">-- Pilih Mata Kuliah --</option>
              <option v-for="c in myCourses" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }} ({{ c.credits }} SKS)</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Hanya menampilkan MK yang telah ditugaskan kepada Anda</p>
          </div>

          <!-- Kurikulum (auto-select jika hanya 1) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Kurikulum <span class="text-red-500">*</span></label>
            <select v-model="form.curriculum_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">-- Pilih Kurikulum --</option>
              <option v-for="c in curriculums" :key="c.id" :value="c.id">{{ c.code ?? '' }} {{ c.name }} ({{ c.year }})</option>
            </select>
          </div>

          <!-- Tahun Akademik (auto-select aktif) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Akademik</label>
            <select v-model="form.academic_year_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50">
              <option v-for="a in academicYears" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>

          <!-- Dosen (readonly, otomatis terisi) -->
          <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
            <p class="text-xs text-gray-500">Dosen Pengampu</p>
            <p class="text-sm font-medium text-gray-800">{{ auth.user?.name }}</p>
            <input type="hidden" v-model="form.lecturer_id" />
          </div>

          <!-- Deskripsi -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Mata Kuliah</label>
            <textarea v-model="form.course_description" rows="3" placeholder="Deskripsi singkat mata kuliah..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>
      </template>

      <!-- ========== FORM UNTUK ADMIN/KAPRODI ========== -->
      <template v-else>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mata Kuliah <span class="text-red-500">*</span></label>
          <select v-model="form.course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih Mata Kuliah --</option>
            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }} ({{ c.credits }} SKS)</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kurikulum <span class="text-red-500">*</span></label>
          <select v-model="form.curriculum_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih Kurikulum --</option>
            <option v-for="c in curriculums" :key="c.id" :value="c.id">{{ c.code ?? '' }} {{ c.name }} ({{ c.year }})</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Akademik <span class="text-red-500">*</span></label>
          <select v-model="form.academic_year_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih Tahun Akademik --</option>
            <option v-for="a in academicYears" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Dosen Pengampu <span class="text-red-500">*</span></label>
          <select v-model="form.lecturer_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih Dosen --</option>
            <option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Mata Kuliah</label>
          <textarea v-model="form.course_description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </template>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving || (!isAdmin && myCourses.length === 0)" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">
        {{ saving ? 'Membuat...' : 'Buat RPKPS' }}
      </button>
    </template>
  </BaseModal>
</template>
