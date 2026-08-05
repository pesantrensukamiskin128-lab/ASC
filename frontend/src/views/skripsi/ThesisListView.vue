<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { PlusIcon, EyeIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()
const isMahasiswa = auth.user?.roles?.includes('MAHASISWA')

const items      = ref<any[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading    = ref(true)
const filterStatus = ref('')
const search     = ref('')
const dashboard  = ref<any>(null)

// Modal buat skripsi baru
const modalOpen = ref(false)
const saving    = ref(false)
const searchStudent  = ref('')
const studentResults = ref<any[]>([])
const form = reactive({
  student_id: '' as any,
  title: '',
  title_english: '',
  type: 'SKRIPSI',
  proposal_file_url: '',
})

const ALL_STATUSES = [
  'DRAFT', 'PENGAJUAN_JUDUL', 'JUDUL_DITOLAK',
  'SEMINAR_PROPOSAL', 'REVISI_PROPOSAL', 'PEMERIKSAAN_REVISI',
  'PENUNJUKAN_PEMBIMBING', 'BIMBINGAN',
  'SIDANG', 'REVISI_SIDANG', 'SELESAI', 'DIPUBLIKASIKAN', 'GAGAL',
]

const STATUS_LABELS: Record<string, string> = {
  DRAFT: 'Draft', PENGAJUAN_JUDUL: 'Pengajuan Judul', JUDUL_DITOLAK: 'Judul Ditolak',
  SEMINAR_PROPOSAL: 'Seminar Proposal', REVISI_PROPOSAL: 'Revisi Proposal',
  PEMERIKSAAN_REVISI: 'Pemeriksaan Revisi', PENUNJUKAN_PEMBIMBING: 'Penunjukan Pembimbing',
  BIMBINGAN: 'Bimbingan', SIDANG: 'Sidang Munaqosyah',
  REVISI_SIDANG: 'Revisi Sidang', SELESAI: 'Selesai',
  DIPUBLIKASIKAN: 'Dipublikasikan', GAGAL: 'Gagal',
}

const STATUS_COLORS: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600',
  PENGAJUAN_JUDUL: 'bg-blue-100 text-blue-700',
  JUDUL_DITOLAK: 'bg-red-100 text-red-600',
  SEMINAR_PROPOSAL: 'bg-indigo-100 text-indigo-700',
  REVISI_PROPOSAL: 'bg-yellow-100 text-yellow-700',
  PEMERIKSAAN_REVISI: 'bg-orange-100 text-orange-700',
  PENUNJUKAN_PEMBIMBING: 'bg-purple-100 text-purple-700',
  BIMBINGAN: 'bg-cyan-100 text-cyan-700',
  SIDANG: 'bg-orange-100 text-orange-700',
  REVISI_SIDANG: 'bg-yellow-100 text-yellow-700',
  SELESAI: 'bg-green-100 text-green-700',
  DIPUBLIKASIKAN: 'bg-emerald-100 text-emerald-700',
  GAGAL: 'bg-red-100 text-red-600',
}

const columns = [
  { key: 'student', label: 'Mahasiswa' },
  { key: 'title', label: 'Judul' },
  { key: 'supervisors', label: 'Pembimbing' },
  { key: 'status', label: 'Status' },
  { key: 'aksi', label: '', class: 'text-right' },
]

onMounted(async () => {
  load()
  const dRes = await api.get('/theses/dashboard')
  dashboard.value = dRes.data
  if (!isMahasiswa) {
    const lRes = await api.get('/lecturers/all')
    // lecturers tidak dipakai di list, hanya di detail
  }
})

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/theses', {
      params: { status: filterStatus.value, search: search.value, page },
    })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

let sTimeout: any
function onSearchStudent() {
  clearTimeout(sTimeout)
  if (searchStudent.value.length < 2) { studentResults.value = []; return }
  sTimeout = setTimeout(async () => {
    const { data } = await api.get('/students', { params: { search: searchStudent.value, per_page: 10 } })
    studentResults.value = data.data ?? data
  }, 300)
}

function selectStudent(s: any) {
  form.student_id   = s.id
  searchStudent.value = `${s.nim} - ${s.name}`
  studentResults.value = []
}

function openCreate() {
  Object.assign(form, { student_id: '', title: '', title_english: '', type: 'SKRIPSI', proposal_file_url: '' })
  searchStudent.value = ''
  if (isMahasiswa && auth.user) {
    const student = (auth.user as any).student
    if (student) {
      form.student_id   = student.id
      searchStudent.value = `${student.nim} - ${student.name ?? auth.user.name}`
    }
  }
  modalOpen.value = true
}

async function handleSave() {
  if (!form.student_id || !form.title) { toast.error('Mahasiswa dan judul wajib diisi.'); return }
  saving.value = true
  try {
    const { data } = await api.post('/theses', form)
    toast.success('Draft skripsi berhasil dibuat.')
    modalOpen.value = false
    router.push(`/skripsi/${data.data.id}`)
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Skripsi / Tugas Akhir</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ isMahasiswa ? 'Kelola pengajuan skripsi Anda' : 'Pengelolaan skripsi mahasiswa' }}</p>
      </div>
      <!-- Hanya mahasiswa dan admin yang bisa buat draft skripsi baru -->
      <button v-if="isMahasiswa || auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK')"
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg"
        @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Buat Draft Skripsi
      </button>
    </div>

    <!-- Dashboard stats -->
    <div v-if="dashboard" class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="rounded-xl border bg-blue-50 border-blue-200 p-3 text-center">
        <p class="text-lg font-bold text-blue-700">{{ dashboard.pengajuan_judul ?? 0 }}</p>
        <p class="text-[10px] text-blue-600">Pengajuan Judul</p>
      </div>
      <div class="rounded-xl border bg-indigo-50 border-indigo-200 p-3 text-center">
        <p class="text-lg font-bold text-indigo-700">{{ dashboard.seminar_proposal ?? 0 }}</p>
        <p class="text-[10px] text-indigo-600">Seminar Proposal</p>
      </div>
      <div class="rounded-xl border bg-cyan-50 border-cyan-200 p-3 text-center">
        <p class="text-lg font-bold text-cyan-700">{{ dashboard.bimbingan ?? 0 }}</p>
        <p class="text-[10px] text-cyan-600">Bimbingan</p>
      </div>
      <div class="rounded-xl border bg-orange-50 border-orange-200 p-3 text-center">
        <p class="text-lg font-bold text-orange-700">{{ dashboard.sidang ?? 0 }}</p>
        <p class="text-[10px] text-orange-600">Sidang</p>
      </div>
      <div class="rounded-xl border bg-green-50 border-green-200 p-3 text-center">
        <p class="text-lg font-bold text-green-700">{{ dashboard.selesai ?? 0 }}</p>
        <p class="text-[10px] text-green-600">Selesai</p>
      </div>
      <div class="rounded-xl border bg-emerald-50 border-emerald-200 p-3 text-center">
        <p class="text-lg font-bold text-emerald-700">{{ dashboard.dipublikasikan ?? 0 }}</p>
        <p class="text-[10px] text-emerald-600">Dipublikasikan</p>
      </div>
      <div class="rounded-xl border bg-yellow-50 border-yellow-200 p-3 text-center">
        <p class="text-lg font-bold text-yellow-700">{{ (dashboard.revisi_proposal ?? 0) + (dashboard.pemeriksaan_revisi ?? 0) }}</p>
        <p class="text-[10px] text-yellow-600">Revisi Proposal</p>
      </div>
      <div class="rounded-xl border bg-gray-50 border-gray-200 p-3 text-center">
        <p class="text-lg font-bold text-gray-700">{{ dashboard.total ?? 0 }}</p>
        <p class="text-[10px] text-gray-600">Total</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3">
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="s in ALL_STATUSES" :key="s" :value="s">{{ STATUS_LABELS[s] ?? s }}</option>
      </select>
      <input v-model="search" type="text" placeholder="Cari judul/NIM/nama..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm w-56" @input="load()" />
    </div>

    <!-- Table -->
    <DataTable :columns="columns" :rows="items" :loading="loading"
      :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage"
      @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.student?.name }}</p>
          <p class="text-xs text-gray-500">{{ row.student?.nim }}</p>
        </td>
        <td class="px-4 py-3">
          <p class="text-sm text-gray-800 max-w-xs truncate">{{ row.title }}</p>
        </td>
        <td class="px-4 py-3 text-xs text-gray-600">
          {{ row.supervisors?.map((s: any) => s.lecturer?.name).filter(Boolean).join(', ') || '—' }}
        </td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap', STATUS_COLORS[row.status] ?? 'bg-gray-100 text-gray-600']">
            {{ STATUS_LABELS[row.status] ?? row.status }}
          </span>
        </td>
        <td class="px-4 py-3 text-right">
          <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/skripsi/${row.id}`)">
            <EyeIcon class="w-4 h-4" />
          </button>
        </td>
      </template>
    </DataTable>
  </div>

  <!-- Modal Buat Draft -->
  <BaseModal :open="modalOpen" title="Buat Draft Skripsi" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <!-- Field mahasiswa (hanya untuk admin/dosen) -->
      <div v-if="!isMahasiswa" class="relative">
        <label class="block text-sm font-medium text-gray-700 mb-1">Mahasiswa <span class="text-red-500">*</span></label>
        <input v-model="searchStudent" placeholder="Ketik NIM/nama..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" @input="onSearchStudent" />
        <div v-if="studentResults.length" class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
          <button v-for="s in studentResults" :key="s.id" type="button"
            class="w-full text-left px-3 py-2 hover:bg-blue-50 text-sm"
            @click="selectStudent(s)">
            <span class="font-mono text-xs text-gray-500">{{ s.nim }}</span> — {{ s.name }}
          </button>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Judul Skripsi <span class="text-red-500">*</span></label>
        <input v-model="form.title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Tuliskan judul skripsi secara lengkap..." />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Judul (English)</label>
        <input v-model="form.title_english" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Link File Proposal (Google Drive, opsional)</label>
        <input v-model="form.proposal_file_url" type="url" placeholder="https://drive.google.com/..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        <p class="text-xs text-gray-400 mt-1">Anda bisa menambahkan link ini nanti. Draft masih bisa diedit sebelum diajukan.</p>
      </div>
      <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700">
        📋 Setelah disimpan, skripsi akan berstatus <strong>Draft</strong>. Anda bisa mengedit dan menambahkan detail sebelum mengajukan ke Ka.Prodi.
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">
        {{ saving ? 'Menyimpan...' : 'Simpan Draft' }}
      </button>
    </template>
  </BaseModal>
</template>
