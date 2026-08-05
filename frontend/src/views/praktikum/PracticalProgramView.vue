<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { PlusIcon, EyeIcon, PencilIcon, TrashIcon, CheckCircleIcon, ClockIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const toast = useToast()
const auth = useAuthStore()
const isMahasiswa = computed(() => auth.hasRole('MAHASISWA'))
const isLP2M      = computed(() => auth.hasRole('LP2M'))

const items = ref<any[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const filterType = ref('')
const search = ref('')
const semesters = ref<any[]>([])
const programs = ref<any[]>([])
const lecturers = ref<any[]>([])

const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const form = reactive({
  name: '', program_type: 'KKN', semester_id: '', study_program_id: '', description: '',
  registration_start: '', registration_end: '', start_date: '', end_date: '',
  min_credits: '', credit_value: '', coordinator_id: '', is_active: true,
})

const programTypes = ['KKN', 'PPL', 'MAGANG', 'PRAKTIKUM', 'PKL']
const typeColor: Record<string, string> = { KKN: 'bg-green-100 text-green-700', PPL: 'bg-blue-100 text-blue-700', MAGANG: 'bg-purple-100 text-purple-700', PRAKTIKUM: 'bg-orange-100 text-orange-700', PKL: 'bg-teal-100 text-teal-700' }

const columns = [
  { key: 'name', label: 'Nama Program' }, { key: 'type', label: 'Jenis' },
  { key: 'semester', label: 'Semester' }, { key: 'participants', label: 'Peserta', class: 'text-center' },
  { key: 'period', label: 'Periode' }, { key: 'status', label: 'Status' },
  { key: 'aksi', label: '', class: 'text-right' },
]

onMounted(async () => {
  load()
  if (!isMahasiswa.value) {
    const [sRes, pRes, lRes] = await Promise.all([
      api.get('/semesters', { params: { per_page: 50 } }),
      api.get('/study-programs/all'),
      api.get('/lecturers/all'),
    ])
    semesters.value = sRes.data.data ?? sRes.data
    programs.value = pRes.data
    lecturers.value = lRes.data
  } else {
    loadMyPrograms()
  }
})

// Program yang sudah diikuti mahasiswa (untuk cek status daftar)
const myParticipations = ref<any[]>([])
const registering = ref<number | null>(null)

async function loadMyPrograms() {
  try {
    const { data } = await api.get('/practical-my-programs')
    myParticipations.value = data
  } catch { /* ignore */ }
}

function isRegistered(programId: number): boolean {
  return myParticipations.value.some((p: any) => p.program_id === programId || p.program?.id === programId)
}

function getMyParticipant(programId: number): any {
  return myParticipations.value.find((p: any) => p.program_id === programId || p.program?.id === programId)
}

async function selfRegister(item: any) {
  if (!confirm(`Daftar ke program "${item.name}"?`)) return
  registering.value = item.id
  try {
    await api.post(`/practical-programs/${item.id}/self-register`)
    toast.success('Berhasil mendaftar!')
    loadMyPrograms()
    load()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal mendaftar.')
  } finally { registering.value = null }
}

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/practical-programs', { params: { program_type: filterType.value, search: search.value, page } })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', program_type: 'KKN', semester_id: '', study_program_id: '', description: '', registration_start: '', registration_end: '', start_date: '', end_date: '', min_credits: '', credit_value: '', coordinator_id: '', is_active: true })
  modalOpen.value = true
}

function openEdit(item: any) {
  editingId.value = item.id
  Object.assign(form, {
    name: item.name, program_type: item.program_type, semester_id: item.semester_id,
    study_program_id: item.study_program_id ?? '', description: item.description ?? '',
    registration_start: item.registration_start ?? '', registration_end: item.registration_end ?? '',
    start_date: item.start_date ?? '', end_date: item.end_date ?? '',
    min_credits: item.min_credits ?? '', credit_value: item.credit_value ?? '',
    coordinator_id: item.coordinator_id ?? '', is_active: item.is_active,
  })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    if (editingId.value) { await api.put(`/practical-programs/${editingId.value}`, form); toast.success('Program diupdate.') }
    else { await api.post('/practical-programs', form); toast.success('Program berhasil dibuat.') }
    modalOpen.value = false; load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

async function handleDelete(item: any) {
  if (!confirm(`Hapus program "${item.name}"?`)) return
  try { await api.delete(`/practical-programs/${item.id}`); toast.success('Dihapus.'); load() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Program Praktikum</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ isMahasiswa ? 'Lihat dan daftar program praktikum, KKN, atau magang' : 'Kelola KKN, PPL, Magang, Praktikum, dan PKL' }}</p>
      </div>
      <!-- Hanya admin/dosen/LP2M yang bisa buat program -->
      <button v-if="!isMahasiswa" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Buat Program
      </button>
    </div>

    <!-- Info mahasiswa -->
    <div v-if="isMahasiswa" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700 flex items-center gap-2">
      <CheckCircleIcon class="w-4 h-4 shrink-0" />
      Anda dapat mendaftar ke program yang sedang membuka pendaftaran. Klik tombol <strong>"Daftar"</strong> pada program yang diinginkan.
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterType" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Jenis</option>
        <option v-for="t in programTypes" :key="t" :value="t">{{ t }}</option>
      </select>
      <input v-model="search" type="text" placeholder="Cari program..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm w-52" @input="load()" />
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3"><p class="font-medium text-gray-900 text-sm">{{ row.name }}</p></td>
        <td class="px-4 py-3"><span :class="['text-xs px-2 py-0.5 rounded font-bold', typeColor[row.program_type] ?? 'bg-gray-100']">{{ row.program_type }}</span></td>
        <td class="px-4 py-3 text-sm text-gray-600">{{ row.semester?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-center"><span class="inline-flex px-2.5 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">{{ row.participants_count }}</span></td>
        <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(row.start_date) }} – {{ formatDate(row.end_date) }}</td>
        <td class="px-4 py-3"><span :class="['text-xs px-2 py-0.5 rounded-full font-medium', row.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <!-- Mahasiswa: tombol Daftar atau badge status -->
            <template v-if="isMahasiswa">
              <span v-if="isRegistered(row.id)"
                :class="['text-xs px-2.5 py-1 rounded-lg font-medium flex items-center gap-1',
                  getMyParticipant(row.id)?.status === 'SELESAI' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700']">
                <CheckCircleIcon class="w-3.5 h-3.5" />
                {{ getMyParticipant(row.id)?.status ?? 'Terdaftar' }}
              </span>
              <button v-else-if="row.is_active"
                :disabled="registering === row.id"
                class="px-3 py-1.5 text-xs bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg"
                @click="selfRegister(row)">
                {{ registering === row.id ? '...' : 'Daftar' }}
              </button>
              <span v-else class="text-xs text-gray-400">Ditutup</span>
            </template>
            <!-- Dosen/Admin: tombol manajemen -->
            <template v-else>
              <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/praktikum/${row.id}`)"><EyeIcon class="w-4 h-4" /></button>
              <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-50" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
              <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
            </template>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal v-if="!isMahasiswa" :open="modalOpen" :title="editingId ? 'Edit Program' : 'Buat Program Baru'" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Nama Program <span class="text-red-500">*</span></label><input v-model="form.name" required placeholder="KKN Reguler 2026" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Jenis <span class="text-red-500">*</span></label><select v-model="form.program_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option v-for="t in programTypes" :key="t" :value="t">{{ t }}</option></select></div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Semester <span class="text-red-500">*</span></label><select v-model="form.semester_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">-- Pilih --</option><option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Program Studi</label><select v-model="form.study_program_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">Semua Prodi</option><option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option></select></div>
      </div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label><textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      <p class="text-xs font-semibold text-gray-400 uppercase">Jadwal</p>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Pendaftaran Mulai</label><input v-model="form.registration_start" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Pendaftaran Selesai</label><input v-model="form.registration_end" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Pelaksanaan Mulai</label><input v-model="form.start_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Pelaksanaan Selesai</label><input v-model="form.end_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Min SKS</label><input v-model.number="form.min_credits" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Bobot SKS</label><input v-model.number="form.credit_value" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Koordinator</label><select v-model="form.coordinator_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">-- Pilih --</option><option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.is_active" type="checkbox" class="rounded" /> Aktifkan program</label>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
