<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ArrowLeftIcon, PlusIcon, TrashIcon, PencilIcon, CheckCircleIcon, MapPinIcon, UsersIcon, ClipboardDocumentListIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()
const loading = ref(true)
const program = ref<any>(null)
const activeTab = ref('peserta')

// Dosen pembimbing hanya bisa lihat & nilai, tidak bisa tambah/edit peserta/lokasi/kelompok
const canManage = computed(() => auth.hasPermission('kkn.create') || auth.hasPermission('kkn.edit'))

const tabs = [
  { key: 'peserta', label: 'Peserta' },
  { key: 'lokasi', label: 'Lokasi' },
  { key: 'kelompok', label: 'Kelompok' },
]

// Data
const participants = ref<any[]>([])
const participantPagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const students = ref<any[]>([])
const lecturers = ref<any[]>([])

onMounted(async () => {
  try {
    const [pRes, lRes] = await Promise.all([
      api.get(`/practical-programs/${route.params.id}`),
      api.get('/lecturers/all'),
    ])
    program.value = pRes.data
    lecturers.value = lRes.data
    loadTab()
  } finally { loading.value = false }
})

async function loadTab() {
  if (activeTab.value === 'peserta') await loadParticipants()
}

function switchTab(key: string) { activeTab.value = key; loadTab() }

// === PARTICIPANTS ===
async function loadParticipants(page = 1) {
  const { data } = await api.get(`/practical-programs/${route.params.id}/participants`, {
    params: { page, per_page: 20, search: participantSearch.value, status: participantStatusFilter.value }
  })
  participants.value = data.data
  participantPagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
}

const participantSearch = ref('')
const participantStatusFilter = ref('')
let searchTimeout: any

function onParticipantSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => loadParticipants(1), 300)
}

const partModal = ref(false); const partSaving = ref(false)
const partForm = reactive({ student_id: '', group_id: '', location_id: '', supervisor_id: '', supervisor2_id: '' })
const searchStudent = ref(''); const studentResults = ref<any[]>([])

// Edit participant
const editPartModal = ref(false); const editPartSaving = ref(false)
const editPartId = ref<number | null>(null)
const editPartForm = reactive({ group_id: '', location_id: '', supervisor_id: '', supervisor2_id: '', status: '' })

function openEditParticipant(p: any) {
  editPartId.value = p.id
  Object.assign(editPartForm, {
    group_id: p.group_id ?? '',
    location_id: p.location_id ?? '',
    supervisor_id: p.supervisor_id ?? '',
    supervisor2_id: p.supervisor2_id ?? '',
    status: p.status ?? 'TERDAFTAR',
  })
  editPartModal.value = true
}

async function saveEditParticipant() {
  if (!editPartId.value) return
  editPartSaving.value = true
  try {
    await api.put(`/practical-participants/${editPartId.value}`, editPartForm)
    toast.success('Data peserta berhasil diupdate.')
    editPartModal.value = false
    loadParticipants(participantPagination.value.currentPage)
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { editPartSaving.value = false }
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
function selectStudent(s: any) { partForm.student_id = s.id; searchStudent.value = `${s.nim} - ${s.name}`; studentResults.value = [] }

async function saveParticipant() {
  partSaving.value = true
  try {
    await api.post(`/practical-programs/${route.params.id}/participants`, partForm)
    toast.success('Peserta berhasil didaftarkan.'); partModal.value = false; loadParticipants()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { partSaving.value = false }
}

async function removeParticipant(p: any) {
  if (!confirm(`Hapus peserta ${p.student?.name}?`)) return
  try { await api.delete(`/practical-participants/${p.id}`); toast.success('Dihapus.'); loadParticipants() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// === LOCATIONS ===
const locModal = ref(false); const locSaving = ref(false)
const locForm = reactive({ name: '', address: '', city: '', contact_person: '', contact_phone: '', capacity: '', supervisor_id: '', supervisor2_id: '' })
const editingLocId = ref<number | null>(null)

function openCreateLocation() {
  editingLocId.value = null
  Object.assign(locForm, { name: '', address: '', city: '', contact_person: '', contact_phone: '', capacity: '', supervisor_id: '', supervisor2_id: '' })
  locModal.value = true
}

function openEditLocation(loc: any) {
  editingLocId.value = loc.id
  Object.assign(locForm, {
    name: loc.name ?? '',
    address: loc.address ?? '',
    city: loc.city ?? '',
    contact_person: loc.contact_person ?? '',
    contact_phone: loc.contact_phone ?? '',
    capacity: loc.capacity ?? '',
    supervisor_id: loc.supervisor_id ?? '',
    supervisor2_id: loc.supervisor2_id ?? '',
  })
  locModal.value = true
}

async function saveLocation() {
  locSaving.value = true
  try {
    if (editingLocId.value) {
      await api.put(`/practical-programs/${route.params.id}/locations/${editingLocId.value}`, locForm)
      toast.success('Lokasi berhasil diupdate.')
    } else {
      await api.post(`/practical-programs/${route.params.id}/locations`, locForm)
      toast.success('Lokasi ditambahkan.')
    }
    locModal.value = false
    const { data } = await api.get(`/practical-programs/${route.params.id}`); program.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { locSaving.value = false }
}

async function removeLocation(loc: any) {
  if (!confirm(`Hapus lokasi "${loc.name}"?`)) return
  await api.delete(`/practical-programs/${route.params.id}/locations/${loc.id}`)
  const { data } = await api.get(`/practical-programs/${route.params.id}`); program.value = data
}

// === GROUPS ===
const grpModal = ref(false); const grpSaving = ref(false)
const grpForm = reactive({ name: '', location_id: '', supervisor_id: '', supervisor2_id: '', leader_id: '', notes: '' })
const editingGroupId = ref<number | null>(null)

function openCreateGroup() {
  editingGroupId.value = null
  Object.assign(grpForm, { name: '', location_id: '', supervisor_id: '', supervisor2_id: '', leader_id: '', notes: '' })
  grpModal.value = true
}

function openEditGroup(g: any) {
  editingGroupId.value = g.id
  Object.assign(grpForm, {
    name: g.name,
    location_id: g.location_id ?? '',
    supervisor_id: g.supervisor_id ?? '',
    supervisor2_id: g.supervisor2_id ?? '',
    leader_id: g.leader_id ?? '',
    notes: g.notes ?? '',
  })
  grpModal.value = true
}

async function saveGroup() {
  grpSaving.value = true
  try {
    if (editingGroupId.value) {
      await api.put(`/practical-programs/${route.params.id}/groups/${editingGroupId.value}`, grpForm)
      toast.success('Kelompok berhasil diupdate.')
    } else {
      await api.post(`/practical-programs/${route.params.id}/groups`, grpForm)
      toast.success('Kelompok ditambahkan.')
    }
    grpModal.value = false
    const { data } = await api.get(`/practical-programs/${route.params.id}`); program.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { grpSaving.value = false }
}

async function removeGroup(g: any) {
  if (!confirm(`Hapus kelompok "${g.name}"?`)) return
  await api.delete(`/practical-programs/${route.params.id}/groups/${g.id}`)
  const { data } = await api.get(`/practical-programs/${route.params.id}`); program.value = data
}

const statusColor: Record<string, string> = { TERDAFTAR: 'bg-gray-100 text-gray-600', AKTIF: 'bg-green-100 text-green-700', SELESAI: 'bg-blue-100 text-blue-700', MENGUNDURKAN_DIRI: 'bg-yellow-100 text-yellow-700', GAGAL: 'bg-red-100 text-red-600' }
const typeColor: Record<string, string> = { KKN: 'bg-green-100 text-green-700', PPL: 'bg-blue-100 text-blue-700', MAGANG: 'bg-purple-100 text-purple-700', PRAKTIKUM: 'bg-orange-100 text-orange-700', PKL: 'bg-teal-100 text-teal-700' }
function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="program" class="space-y-5 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div class="flex-1">
        <div class="flex items-center gap-2">
          <span :class="['text-xs px-2 py-0.5 rounded font-bold', typeColor[program.program_type]]">{{ program.program_type }}</span>
          <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', program.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">{{ program.is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mt-1">{{ program.name }}</h1>
        <p class="text-sm text-gray-500">{{ program.semester?.name }} · {{ program.study_program?.name ?? 'Semua Prodi' }}</p>
      </div>
    </div>

    <!-- Info -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
      <div><span class="text-xs text-gray-400">Pelaksanaan</span><p class="text-gray-800">{{ formatDate(program.start_date) }} – {{ formatDate(program.end_date) }}</p></div>
      <div><span class="text-xs text-gray-400">Koordinator</span><p class="text-gray-800 font-medium">{{ program.coordinator?.name ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">Bobot SKS</span><p class="text-gray-800 font-bold">{{ program.credit_value ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">Min SKS</span><p class="text-gray-800">{{ program.min_credits ?? '-' }}</p></div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-gray-200">
      <button v-for="t in tabs" :key="t.key" :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px', activeTab === t.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']" @click="switchTab(t.key)">{{ t.label }}</button>
    </div>

    <!-- TAB: Peserta -->
    <div v-if="activeTab === 'peserta'" class="space-y-4">
      <!-- Header: search + filter + add button -->
      <div class="flex flex-wrap items-center gap-2">
        <input v-model="participantSearch" type="text" placeholder="Cari nama / NIM..."
          class="px-3 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-48"
          @input="onParticipantSearch()" />
        <select v-model="participantStatusFilter" class="px-3 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="loadParticipants(1)">
          <option value="">Semua Status</option>
          <option value="TERDAFTAR">Terdaftar</option>
          <option value="AKTIF">Aktif</option>
          <option value="SELESAI">Selesai</option>
          <option value="MENGUNDURKAN_DIRI">Mengundurkan Diri</option>
          <option value="GAGAL">Gagal</option>
        </select>
        <span class="text-xs text-gray-500 ml-1">{{ participantPagination.total }} peserta</span>
        <div v-if="canManage" class="ml-auto">
          <button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="Object.assign(partForm, {student_id:'',group_id:'',location_id:'',supervisor_id:'',supervisor2_id:''}); searchStudent=''; partModal=true"><PlusIcon class="w-3.5 h-3.5" /> Daftarkan Peserta</button>
        </div>
      </div>

      <div v-if="!participants.length" class="text-center py-8 text-gray-400 text-sm">Belum ada peserta.</div>
      <div v-else class="space-y-2">
        <div v-for="p in participants" :key="p.id" class="flex items-center gap-4 p-4 bg-white rounded-xl border border-gray-200">
          <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0">{{ p.student?.name?.charAt(0) }}</div>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-900 text-sm">{{ p.student?.name }}</p>
            <p class="text-xs text-gray-500">{{ p.student?.nim }} · {{ p.group?.name ?? 'Belum dikelompokkan' }} · {{ p.location?.name ?? '-' }}</p>
            <p v-if="p.supervisor" class="text-xs text-gray-400">Pembimbing: {{ p.supervisor.name }}</p>
          </div>
          <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusColor[p.status]]">{{ p.status }}</span>
          <div class="flex items-center gap-1">
            <button class="p-1 rounded text-blue-600 hover:bg-blue-50 text-xs font-medium" @click="router.push(`/praktikum/peserta/${p.id}`)">Detail</button>
            <button v-if="canManage" class="p-1 rounded text-gray-500 hover:bg-gray-100" title="Edit" @click="openEditParticipant(p)"><PencilIcon class="w-4 h-4" /></button>
            <button v-if="canManage" class="p-1 rounded text-red-500 hover:bg-red-50" @click="removeParticipant(p)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="participantPagination.lastPage > 1" class="flex items-center justify-between pt-2">
        <p class="text-xs text-gray-500">Halaman {{ participantPagination.currentPage }} dari {{ participantPagination.lastPage }}</p>
        <div class="flex gap-1">
          <button v-for="p in participantPagination.lastPage" :key="p"
            :class="['px-3 py-1 rounded text-xs transition-colors', p === participantPagination.currentPage ? 'bg-blue-600 text-white font-medium' : 'text-gray-600 hover:bg-gray-100']"
            @click="loadParticipants(p)">{{ p }}</button>
        </div>
      </div>
    </div>

    <!-- TAB: Lokasi -->
    <div v-if="activeTab === 'lokasi'" class="space-y-4">
      <div v-if="canManage" class="flex justify-end">
        <button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openCreateLocation()"><PlusIcon class="w-3.5 h-3.5" /> Tambah Lokasi</button>
      </div>
      <div v-if="!program.locations?.length" class="text-center py-8 text-gray-400 text-sm">Belum ada lokasi.</div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div v-for="loc in program.locations" :key="loc.id" class="p-4 bg-white rounded-xl border border-gray-200">
          <div class="flex items-start justify-between">
            <div>
              <p class="font-medium text-gray-900 text-sm flex items-center gap-1"><MapPinIcon class="w-4 h-4 text-red-500" /> {{ loc.name }}</p>
              <p v-if="loc.address" class="text-xs text-gray-500 mt-1">{{ loc.address }}, {{ loc.city ?? '' }}</p>
              <p v-if="loc.supervisor" class="text-xs text-gray-500 mt-1">Pembimbing 1: {{ loc.supervisor.name }}</p>
              <p v-if="loc.supervisor2" class="text-xs text-gray-500 mt-0.5">Pembimbing 2: {{ loc.supervisor2.name }}</p>
            </div>
            <div v-if="canManage" class="flex items-center gap-1">
              <button class="p-1 rounded text-blue-600 hover:bg-blue-50" title="Edit" @click="openEditLocation(loc)"><PencilIcon class="w-4 h-4" /></button>
              <button class="p-1 rounded text-red-500 hover:bg-red-50" @click="removeLocation(loc)"><TrashIcon class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: Kelompok -->
    <div v-if="activeTab === 'kelompok'" class="space-y-4">
      <div v-if="canManage" class="flex justify-end">
        <button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openCreateGroup()"><PlusIcon class="w-3.5 h-3.5" /> Tambah Kelompok</button>
      </div>
      <div v-if="!program.groups?.length" class="text-center py-8 text-gray-400 text-sm">Belum ada kelompok.</div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <div v-for="g in program.groups" :key="g.id" class="p-4 bg-white rounded-xl border border-gray-200">
          <div class="flex items-start justify-between">
            <div>
              <p class="font-semibold text-gray-900 text-sm flex items-center gap-1"><UsersIcon class="w-4 h-4 text-blue-500" /> {{ g.name }}</p>
              <p v-if="g.location" class="text-xs text-gray-500 mt-1">📍 {{ g.location.name }}</p>
              <p v-if="g.supervisor" class="text-xs text-gray-500 mt-0.5">Pembimbing 1: {{ g.supervisor.name }}</p>
              <p v-if="g.supervisor2" class="text-xs text-gray-500 mt-0.5">Pembimbing 2: {{ g.supervisor2.name }}</p>
              <p v-if="g.leader?.student" class="text-xs text-green-700 mt-0.5 font-medium">👑 Ketua: {{ g.leader.student.name }}</p>
            </div>
            <div v-if="canManage" class="flex items-center gap-1">
              <button class="p-1 rounded text-blue-600 hover:bg-blue-50" @click="openEditGroup(g)" title="Edit"><PencilIcon class="w-4 h-4" /></button>
              <button class="p-1 rounded text-red-500 hover:bg-red-50" @click="removeGroup(g)" title="Hapus"><TrashIcon class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Peserta -->
  <BaseModal :open="partModal" title="Daftarkan Peserta" @close="partModal = false">
    <form class="space-y-4" @submit.prevent="saveParticipant">
      <div class="relative">
        <label class="block text-sm font-medium text-gray-700 mb-1">Mahasiswa <span class="text-red-500">*</span></label>
        <input v-model="searchStudent" placeholder="Ketik NIM/nama..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" @input="onSearchStudent" />
        <div v-if="studentResults.length" class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
          <button v-for="s in studentResults" :key="s.id" type="button" class="w-full text-left px-3 py-2 hover:bg-blue-50 text-sm" @click="selectStudent(s)"><span class="font-mono text-xs text-gray-500">{{ s.nim }}</span> — {{ s.name }}</button>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Kelompok</label><select v-model="partForm.group_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">-- Belum --</option><option v-for="g in program.groups" :key="g.id" :value="g.id">{{ g.name }}</option></select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label><select v-model="partForm.location_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">-- Belum --</option><option v-for="l in program.locations" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
      </div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Dosen Pembimbing 1</label><select v-model="partForm.supervisor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">-- Pilih --</option><option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Dosen Pembimbing 2 <span class="text-xs text-gray-400">(opsional)</span></label><select v-model="partForm.supervisor2_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="">-- Tidak ada --</option><option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="partModal = false">Batal</button>
      <button :disabled="partSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveParticipant">Daftarkan</button>
    </template>
  </BaseModal>

  <!-- Modal Lokasi -->
  <BaseModal :open="locModal" :title="editingLocId ? 'Edit Lokasi' : 'Tambah Lokasi'" @close="locModal = false">
    <form class="space-y-3" @submit.prevent="saveLocation">
      <div><label class="text-xs font-medium text-gray-700">Nama Lokasi <span class="text-red-500">*</span></label><input v-model="locForm.name" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs font-medium text-gray-700">Alamat</label><textarea v-model="locForm.address" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-xs font-medium text-gray-700">Kota</label><input v-model="locForm.city" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs font-medium text-gray-700">Kapasitas</label><input v-model.number="locForm.capacity" type="number" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-xs font-medium text-gray-700">Kontak</label><input v-model="locForm.contact_person" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs font-medium text-gray-700">No. HP</label><input v-model="locForm.contact_phone" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div><label class="text-xs font-medium text-gray-700">Dosen Pembimbing Lapangan 1</label><select v-model="locForm.supervisor_id" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option value="">-- Pilih --</option><option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
      <div><label class="text-xs font-medium text-gray-700">Dosen Pembimbing Lapangan 2 <span class="text-xs text-gray-400">(opsional)</span></label><select v-model="locForm.supervisor2_id" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option value="">-- Tidak ada --</option><option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="locModal = false">Batal</button>
      <button :disabled="locSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveLocation">Simpan</button>
    </template>
  </BaseModal>

  <!-- Modal Kelompok -->
  <BaseModal :open="grpModal" :title="editingGroupId ? 'Edit Kelompok' : 'Tambah Kelompok'" @close="grpModal = false">
    <form class="space-y-3" @submit.prevent="saveGroup">
      <div><label class="text-xs font-medium text-gray-700">Nama Kelompok <span class="text-red-500">*</span></label><input v-model="grpForm.name" required placeholder="Kelompok 1" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs font-medium text-gray-700">Lokasi</label><select v-model="grpForm.location_id" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option value="">-- Pilih --</option><option v-for="l in program.locations" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
      <div><label class="text-xs font-medium text-gray-700">Dosen Pembimbing 1</label><select v-model="grpForm.supervisor_id" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option value="">-- Pilih --</option><option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
      <div><label class="text-xs font-medium text-gray-700">Dosen Pembimbing 2 <span class="text-xs text-gray-400">(opsional)</span></label><select v-model="grpForm.supervisor2_id" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option value="">-- Tidak ada --</option><option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
      <div><label class="text-xs font-medium text-gray-700">Ketua Kelompok</label><select v-model="grpForm.leader_id" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option value="">-- Belum ditentukan --</option><option v-for="p in participants" :key="p.id" :value="p.id">{{ p.student?.name }} ({{ p.student?.nim }})</option></select></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="grpModal = false">Batal</button>
      <button :disabled="grpSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveGroup">Simpan</button>
    </template>
  </BaseModal>

  <!-- Modal Edit Peserta -->
  <BaseModal :open="editPartModal" title="Edit Peserta" @close="editPartModal = false">
    <form class="space-y-3" @submit.prevent="saveEditParticipant">
      <div>
        <label class="text-xs font-medium text-gray-700">Kelompok</label>
        <select v-model="editPartForm.group_id" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="">-- Belum dikelompokkan --</option>
          <option v-for="g in program.groups" :key="g.id" :value="g.id">{{ g.name }}</option>
        </select>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-700">Lokasi</label>
        <select v-model="editPartForm.location_id" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="">-- Belum ada lokasi --</option>
          <option v-for="l in program.locations" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-700">Dosen Pembimbing 1</label>
        <select v-model="editPartForm.supervisor_id" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="">-- Tidak ada --</option>
          <option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-700">Dosen Pembimbing 2 <span class="text-xs text-gray-400">(opsional)</span></label>
        <select v-model="editPartForm.supervisor2_id" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="">-- Tidak ada --</option>
          <option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-700">Status</label>
        <select v-model="editPartForm.status" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="TERDAFTAR">Terdaftar</option>
          <option value="AKTIF">Aktif</option>
          <option value="SELESAI">Selesai</option>
          <option value="MENGUNDURKAN_DIRI">Mengundurkan Diri</option>
          <option value="GAGAL">Gagal</option>
        </select>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="editPartModal = false">Batal</button>
      <button :disabled="editPartSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveEditParticipant">Simpan</button>
    </template>
  </BaseModal>
</template>
