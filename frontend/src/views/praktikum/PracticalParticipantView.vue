<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ArrowLeftIcon, PlusIcon, PencilIcon, TrashIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isMahasiswa = computed(() => auth.hasRole('MAHASISWA'))
const toast = useToast()
const loading = ref(true)

const participant = ref<any>(null)
const logbooks = ref<any[]>([])
const attendances = ref<any[]>([])
const assessments = ref<any[]>([])
const activeTab = ref('logbook')

const tabs = [
  { key: 'logbook', label: 'Logbook' },
  { key: 'presensi', label: 'Presensi' },
  { key: 'nilai', label: 'Penilaian' },
  { key: 'laporan', label: 'Laporan' },
]

onMounted(async () => {
  try {
    // Load participant info from the program participants list
    // We need to load logbooks, attendances, assessments
    await loadAll()
  } finally { loading.value = false }
})

async function loadAll() {
  const [logRes, attRes, assRes] = await Promise.allSettled([
    api.get(`/practical-participants/${route.params.id}/logbooks`),
    api.get(`/practical-participants/${route.params.id}/attendances`),
    api.get(`/practical-participants/${route.params.id}/assessments`),
  ])
  logbooks.value = logRes.status === 'fulfilled' ? logRes.value.data : []
  attendances.value = attRes.status === 'fulfilled' ? attRes.value.data : []
  assessments.value = assRes.status === 'fulfilled' ? assRes.value.data : []
  loadReports()
}

// === LOGBOOK ===
const logModal = ref(false); const logSaving = ref(false)
const logForm = reactive({ activity_date: '', start_time: '', end_time: '', activity: '', result: '', notes: '', attachment_url: '' })
const editingLogId = ref<number | null>(null)

function openCreateLog() {
  editingLogId.value = null
  Object.assign(logForm, { activity_date: '', start_time: '', end_time: '', activity: '', result: '', notes: '', attachment_url: '' })
  logModal.value = true
}

function openEditLog(l: any) {
  editingLogId.value = l.id
  Object.assign(logForm, {
    activity_date: l.activity_date?.split('T')[0] ?? '',
    start_time: l.start_time ?? '',
    end_time: l.end_time ?? '',
    activity: l.activity ?? '',
    result: l.result ?? '',
    notes: '',
    attachment_url: l.attachment_url ?? '',
  })
  logModal.value = true
}

async function saveLogbook() {
  logSaving.value = true
  try {
    if (editingLogId.value) {
      await api.put(`/practical-logbooks/${editingLogId.value}`, logForm)
      toast.success('Logbook berhasil diupdate.')
    } else {
      await api.post(`/practical-participants/${route.params.id}/logbooks`, logForm)
      toast.success('Logbook berhasil ditambahkan.')
    }
    logModal.value = false; const { data } = await api.get(`/practical-participants/${route.params.id}/logbooks`); logbooks.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { logSaving.value = false }
}

async function reviewLogbook(log: any, action: string) {
  try {
    await api.post(`/practical-logbooks/${log.id}/review`, { action })
    toast.success(action === 'approve' ? 'Logbook disetujui.' : 'Logbook perlu revisi.')
    const { data } = await api.get(`/practical-participants/${route.params.id}/logbooks`); logbooks.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// Mahasiswa: submit ulang logbook yang ditolak (ubah status kembali ke SUBMITTED)
async function resubmitLogbook(log: any) {
  try {
    await api.post(`/practical-logbooks/${log.id}/resubmit`)
    toast.success('Logbook berhasil disubmit ulang.')
    const { data } = await api.get(`/practical-participants/${route.params.id}/logbooks`); logbooks.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// === PRESENSI ===
const attModal = ref(false); const attSaving = ref(false)
const attForm = reactive({ attendance_date: '', status: 'HADIR', notes: '', latitude: null as number | null, longitude: null as number | null, proof_url: '' })
const geoLoading = ref(false)

function getGeolocation() {
  if (!navigator.geolocation) { toast.error('Geolocation tidak tersedia di browser ini.'); return }
  geoLoading.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => { attForm.latitude = pos.coords.latitude; attForm.longitude = pos.coords.longitude; geoLoading.value = false },
    () => { toast.error('Gagal mendapatkan lokasi. Pastikan izin lokasi diaktifkan.'); geoLoading.value = false },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

async function saveAttendance() {
  attSaving.value = true
  try {
    await api.post(`/practical-participants/${route.params.id}/attendances`, attForm)
    toast.success('Presensi berhasil dicatat.')
    attModal.value = false; const { data } = await api.get(`/practical-participants/${route.params.id}/attendances`); attendances.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { attSaving.value = false }
}

// Dosen: terima / tolak presensi
const rejectNote = ref('')
const showRejectModal = ref(false)
const rejectingAttId = ref<number | null>(null)

async function approveAttendance(att: any) {
  try {
    await api.post(`/practical-attendances/${att.id}/review`, { action: 'approve' })
    toast.success('Presensi diterima.')
    const { data } = await api.get(`/practical-participants/${route.params.id}/attendances`); attendances.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

function openRejectAttendance(att: any) {
  rejectingAttId.value = att.id; rejectNote.value = ''; showRejectModal.value = true
}

async function submitRejectAttendance() {
  if (!rejectingAttId.value) return
  try {
    await api.post(`/practical-attendances/${rejectingAttId.value}/review`, { action: 'reject', rejection_note: rejectNote.value })
    toast.success('Presensi ditolak.')
    showRejectModal.value = false
    const { data } = await api.get(`/practical-participants/${route.params.id}/attendances`); attendances.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// === PENILAIAN ===
const assModal = ref(false); const assSaving = ref(false)
const assForm = reactive({ component: '', score: 0, weight: 1, notes: '' })
const editingAssId = ref<number | null>(null)

function openCreateAss() {
  editingAssId.value = null
  Object.assign(assForm, { component: '', score: 0, weight: 1, notes: '' })
  assModal.value = true
}

function openEditAss(a: any) {
  editingAssId.value = a.id
  Object.assign(assForm, { component: a.component, score: a.score, weight: a.weight, notes: a.notes ?? '' })
  assModal.value = true
}

async function saveAssessment() {
  assSaving.value = true
  try {
    if (editingAssId.value) {
      await api.put(`/practical-assessments/${editingAssId.value}`, assForm)
      toast.success('Nilai berhasil diupdate.')
    } else {
      await api.post(`/practical-participants/${route.params.id}/assessments`, assForm)
      toast.success('Nilai berhasil disimpan.')
    }
    assModal.value = false; const { data } = await api.get(`/practical-participants/${route.params.id}/assessments`); assessments.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { assSaving.value = false }
}

async function deleteAssessment(a: any) {
  if (!confirm(`Hapus komponen "${a.component}"?`)) return
  try {
    await api.delete(`/practical-assessments/${a.id}`)
    toast.success('Komponen dihapus.')
    const { data } = await api.get(`/practical-participants/${route.params.id}/assessments`); assessments.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

const totalWeightedScore = computed(() => {
  if (!assessments.value.length) return 0
  const totalWeight = assessments.value.reduce((s, a) => s + Number(a.weight), 0)
  if (totalWeight === 0) return 0
  return (assessments.value.reduce((s, a) => s + (Number(a.score) * Number(a.weight)), 0) / totalWeight).toFixed(2)
})

// === LAPORAN ===
const repModal = ref(false); const repSaving = ref(false)
const repForm = reactive({ title: '', abstract: '', file_url: '', report_type: 'INDIVIDU' })
const reports = ref<any[]>([])
const reportsLoading = ref(false)

async function loadReports() {
  reportsLoading.value = true
  try {
    const { data } = await api.get(`/practical-participants/${route.params.id}/reports`)
    reports.value = data
  } catch {}
  finally { reportsLoading.value = false }
}

// Check if current participant is group leader
const isGroupLeader = ref(false)
async function checkLeaderStatus() {
  try {
    // Load participant info to check if they are group leader
    const { data } = await api.get(`/practical-participants/${route.params.id}/assessments`)
    // We'll check leader from the group data loaded via program detail
  } catch {}
}

async function saveReport() {
  repSaving.value = true
  try {
    await api.post(`/practical-participants/${route.params.id}/reports`, repForm)
    toast.success('Laporan berhasil disubmit.'); repModal.value = false; loadReports()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { repSaving.value = false }
}

const logStatusColor: Record<string, string> = { DRAFT: 'bg-gray-100 text-gray-600', SUBMITTED: 'bg-blue-100 text-blue-700', APPROVED: 'bg-green-100 text-green-700', REVISION: 'bg-yellow-100 text-yellow-700' }
const attStatusColor: Record<string, string> = { HADIR: 'bg-green-100 text-green-700', IZIN: 'bg-blue-100 text-blue-700', SAKIT: 'bg-yellow-100 text-yellow-700', ALPHA: 'bg-red-100 text-red-600' }
function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else class="space-y-5 max-w-4xl mx-auto">
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div>
        <h1 class="text-lg font-bold text-gray-900">Detail Peserta Praktikum</h1>
        <p class="text-sm text-gray-500">Logbook, presensi, penilaian, dan laporan</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-gray-200">
      <button v-for="t in tabs" :key="t.key" :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px', activeTab === t.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']" @click="activeTab = t.key">{{ t.label }}</button>
    </div>

    <!-- LOGBOOK -->
    <div v-if="activeTab === 'logbook'" class="space-y-4">
      <div class="flex justify-end"><button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openCreateLog()"><PlusIcon class="w-3.5 h-3.5" /> Tambah Logbook</button></div>
      <div v-if="!logbooks.length" class="text-center py-8 text-gray-400 text-sm">Belum ada logbook.</div>
      <div v-else class="space-y-2">
        <div v-for="l in logbooks" :key="l.id" :class="['p-4 bg-white rounded-xl border', l.status === 'REVISION' ? 'border-yellow-300 bg-yellow-50/30' : 'border-gray-200']">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-xs text-gray-500">{{ formatDate(l.activity_date) }}</span>
                <span v-if="l.start_time" class="text-xs text-gray-400">{{ l.start_time }} – {{ l.end_time }}</span>
                <span :class="['ml-auto px-2 py-0.5 rounded-full text-xs font-medium', logStatusColor[l.status]]">{{ l.status }}</span>
              </div>
              <p class="text-sm text-gray-800">{{ l.activity }}</p>
              <p v-if="l.result" class="text-xs text-gray-500 mt-1">Hasil: {{ l.result }}</p>
              <a v-if="l.attachment_url" :href="l.attachment_url" target="_blank" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 mt-1 font-medium">📎 Bukti Kegiatan</a>
              <!-- Catatan revisi dari dosen -->
              <div v-if="l.status === 'REVISION' && l.notes" class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-xs text-yellow-800 font-medium">📝 Catatan Pembimbing:</p>
                <p class="text-xs text-yellow-700 mt-0.5">{{ l.notes }}</p>
              </div>
            </div>
            <!-- Dosen: tombol approve/revisi -->
            <div v-if="!isMahasiswa && (l.status === 'SUBMITTED' || l.status === 'APPROVED')" class="flex items-center gap-1 ml-3 shrink-0">
              <button v-if="l.status === 'SUBMITTED'" class="p-1 rounded text-green-600 hover:bg-green-50" title="Approve" @click="reviewLogbook(l, 'approve')"><CheckCircleIcon class="w-4 h-4" /></button>
              <button class="p-1 rounded text-yellow-600 hover:bg-yellow-50" title="Revisi" @click="reviewLogbook(l, 'revision')"><XCircleIcon class="w-4 h-4" /></button>
            </div>
            <!-- Mahasiswa: tombol edit & submit ulang jika REVISION -->
            <div v-if="isMahasiswa && l.status === 'REVISION'" class="flex items-center gap-1 ml-3 shrink-0">
              <button class="px-2.5 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg" @click="openEditLog(l)">✏️ Edit</button>
              <button class="px-2.5 py-1.5 text-xs bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg" @click="resubmitLogbook(l)">Submit Ulang</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PRESENSI -->
    <div v-if="activeTab === 'presensi'" class="space-y-4">
      <div class="flex justify-end"><button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="Object.assign(attForm,{attendance_date:'',status:'HADIR',notes:'',latitude:null,longitude:null,proof_url:''}); attModal=true"><PlusIcon class="w-3.5 h-3.5" /> Catat Presensi</button></div>
      <div v-if="!attendances.length" class="text-center py-8 text-gray-400 text-sm">Belum ada data presensi.</div>
      <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead><tr class="bg-gray-50 text-left text-xs text-gray-500">
            <th class="px-4 py-2">Tanggal</th>
            <th class="px-4 py-2 text-center">Status</th>
            <th class="px-4 py-2 text-center">Verifikasi</th>
            <th class="px-4 py-2">Lokasi</th>
            <th class="px-4 py-2">Bukti</th>
            <th class="px-4 py-2">Catatan</th>
            <th v-if="!isMahasiswa" class="px-4 py-2 text-right">Aksi</th>
          </tr></thead>
          <tbody>
            <tr v-for="a in attendances" :key="a.id" class="border-t border-gray-100" :class="{'bg-yellow-50': a.approval_status === 'MENUNGGU', 'bg-red-50/40': a.approval_status === 'DITOLAK'}">
              <td class="px-4 py-2 text-gray-700">{{ formatDate(a.attendance_date) }}</td>
              <td class="px-4 py-2 text-center"><span :class="['px-2 py-0.5 rounded-full text-xs font-medium', attStatusColor[a.status]]">{{ a.status }}</span></td>
              <td class="px-4 py-2 text-center">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium',
                  a.approval_status === 'DITERIMA' ? 'bg-green-100 text-green-700' :
                  a.approval_status === 'DITOLAK' ? 'bg-red-100 text-red-700' :
                  'bg-yellow-100 text-yellow-700']">
                  {{ a.approval_status === 'DITERIMA' ? '✓ Diterima' : a.approval_status === 'DITOLAK' ? '✗ Ditolak' : '⏳ Menunggu' }}
                </span>
                <p v-if="a.approval_status === 'DITOLAK' && a.rejection_note" class="text-[10px] text-red-500 mt-0.5">{{ a.rejection_note }}</p>
              </td>
              <td class="px-4 py-2 text-xs text-gray-500">
                <a v-if="a.latitude && a.longitude" :href="`https://maps.google.com/?q=${a.latitude},${a.longitude}`" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">📍 {{ Number(a.latitude).toFixed(5) }}, {{ Number(a.longitude).toFixed(5) }}</a>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-2 text-xs">
                <a v-if="a.proof_url" :href="a.proof_url" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">📎 Lihat</a>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-2 text-gray-500 text-xs">{{ a.notes ?? '-' }}</td>
              <!-- Approve/Reject buttons for dosen -->
              <td v-if="!isMahasiswa" class="px-4 py-2 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button v-if="a.approval_status !== 'DITERIMA'" class="p-1 rounded text-green-600 hover:bg-green-50" title="Terima" @click="approveAttendance(a)">
                    <CheckCircleIcon class="w-4 h-4" />
                  </button>
                  <button v-if="a.approval_status !== 'DITOLAK'" class="p-1 rounded text-red-500 hover:bg-red-50" title="Tolak" @click="openRejectAttendance(a)">
                    <XCircleIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Reject Note Modal -->
      <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showRejectModal = false">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-xl">
          <h3 class="text-base font-bold text-gray-900 mb-3">Tolak Presensi</h3>
          <p class="text-xs text-gray-500 mb-3">Berikan alasan penolakan (opsional):</p>
          <textarea v-model="rejectNote" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Contoh: Bukti tidak valid..." />
          <div class="flex justify-end gap-2 mt-4">
            <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="showRejectModal = false">Batal</button>
            <button class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg" @click="submitRejectAttendance">Tolak Presensi</button>
          </div>
        </div>
      </div>
    </div>

    <!-- PENILAIAN -->
    <div v-if="activeTab === 'nilai'" class="space-y-4">
      <div class="flex items-center justify-between">
        <div v-if="assessments.length" class="text-sm text-gray-600">Nilai Akhir: <strong class="text-lg text-blue-700">{{ totalWeightedScore }}</strong></div>
        <button v-if="!isMahasiswa" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openCreateAss()"><PlusIcon class="w-3.5 h-3.5" /> Tambah Komponen</button>
      </div>
      <div v-if="!assessments.length" class="text-center py-8 text-gray-400 text-sm">{{ isMahasiswa ? 'Belum ada penilaian dari pembimbing.' : 'Belum ada penilaian.' }}</div>
      <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead><tr class="bg-gray-50 text-left text-xs text-gray-500"><th class="px-4 py-2">Komponen</th><th class="px-4 py-2 text-center">Bobot</th><th class="px-4 py-2 text-center">Nilai</th><th class="px-4 py-2">Catatan</th><th v-if="!isMahasiswa" class="px-4 py-2 text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="a in assessments" :key="a.id" class="border-t border-gray-100">
              <td class="px-4 py-2 font-medium text-gray-800">{{ a.component }}</td>
              <td class="px-4 py-2 text-center text-gray-600">{{ a.weight }}</td>
              <td class="px-4 py-2 text-center font-bold text-blue-700">{{ a.score }}</td>
              <td class="px-4 py-2 text-gray-500 text-xs">{{ a.notes ?? '-' }}</td>
              <td v-if="!isMahasiswa" class="px-4 py-2 text-right">
                <button class="p-1 rounded text-blue-600 hover:bg-blue-50" title="Edit" @click="openEditAss(a)"><PencilIcon class="w-3.5 h-3.5" /></button>
                <button class="p-1 rounded text-red-500 hover:bg-red-50" title="Hapus" @click="deleteAssessment(a)"><TrashIcon class="w-3.5 h-3.5" /></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- LAPORAN -->
    <div v-if="activeTab === 'laporan'" class="space-y-4">
      <div class="flex justify-end"><button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="Object.assign(repForm,{title:'',abstract:'',file_url:'',report_type:'INDIVIDU'}); repModal=true"><PlusIcon class="w-3.5 h-3.5" /> Submit Laporan</button></div>
      <div v-if="reportsLoading" class="text-center py-8 text-gray-400 text-sm">Memuat laporan...</div>
      <div v-else-if="!reports.length" class="bg-white rounded-xl border border-gray-200 p-6 text-center text-gray-400 text-sm">Belum ada laporan yang disubmit.</div>
      <div v-else class="space-y-2">
        <div v-for="r in reports" :key="r.id" class="p-4 bg-white rounded-xl border border-gray-200">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', r.report_type === 'KELOMPOK' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700']">{{ r.report_type === 'KELOMPOK' ? '👥 Kelompok' : '👤 Individu' }}</span>
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', logStatusColor[r.status]]">{{ r.status }}</span>
              </div>
              <p class="text-sm font-medium text-gray-900">{{ r.title }}</p>
              <p v-if="r.abstract" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ r.abstract }}</p>
              <a v-if="r.file_url" :href="r.file_url" target="_blank" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 mt-1 font-medium">📎 Buka File Laporan</a>
              <p v-if="r.reviewer_notes" class="text-xs text-orange-600 mt-1 italic">Catatan reviewer: {{ r.reviewer_notes }}</p>
              <p class="text-xs text-gray-400 mt-1">Disubmit: {{ r.submitted_at }} {{ r.participant?.student ? '· oleh ' + r.participant.student.name : '' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Logbook -->
  <BaseModal :open="logModal" :title="editingLogId ? 'Edit Logbook' : 'Tambah Logbook'" @close="logModal = false">
    <form class="space-y-3" @submit.prevent="saveLogbook">
      <div class="grid grid-cols-3 gap-3">
        <div><label class="text-xs text-gray-700">Tanggal <span class="text-red-500">*</span></label><input v-model="logForm.activity_date" type="date" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs text-gray-700">Jam Mulai</label><input v-model="logForm.start_time" type="time" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs text-gray-700">Jam Selesai</label><input v-model="logForm.end_time" type="time" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div><label class="text-xs text-gray-700">Kegiatan <span class="text-red-500">*</span></label><textarea v-model="logForm.activity" required rows="3" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs text-gray-700">Hasil</label><textarea v-model="logForm.result" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs text-gray-700">Link Bukti Kegiatan (Foto/File)</label><input v-model="logForm.attachment_url" type="url" placeholder="https://drive.google.com/..." class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="logModal = false">Batal</button>
      <button :disabled="logSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveLogbook">Simpan</button>
    </template>
  </BaseModal>

  <!-- Modal Presensi -->
  <BaseModal :open="attModal" title="Catat Presensi" @close="attModal = false">
    <form class="space-y-3" @submit.prevent="saveAttendance">
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-xs text-gray-700">Tanggal</label><input v-model="attForm.attendance_date" type="date" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs text-gray-700">Status</label><select v-model="attForm.status" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option value="HADIR">Hadir</option><option value="IZIN">Izin</option><option value="SAKIT">Sakit</option><option value="ALPHA">Alpha</option></select></div>
      </div>
      <!-- Geotag -->
      <div>
        <label class="text-xs text-gray-700">Lokasi (Geotag)</label>
        <div class="flex items-center gap-2 mt-1">
          <button type="button" :disabled="geoLoading" class="px-3 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-xs font-medium rounded-lg" @click="getGeolocation">
            {{ geoLoading ? '⏳ Memuat...' : '📍 Ambil Lokasi' }}
          </button>
          <span v-if="attForm.latitude && attForm.longitude" class="text-xs text-green-700 font-mono">{{ Number(attForm.latitude).toFixed(5) }}, {{ Number(attForm.longitude).toFixed(5) }}</span>
          <span v-else class="text-xs text-gray-400">Belum ada lokasi</span>
        </div>
      </div>
      <!-- Bukti kehadiran -->
      <div><label class="text-xs text-gray-700">Link Bukti Kehadiran (Foto)</label><input v-model="attForm.proof_url" type="url" placeholder="https://drive.google.com/..." class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs text-gray-700">Catatan</label><input v-model="attForm.notes" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="attModal = false">Batal</button>
      <button :disabled="attSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveAttendance">Simpan</button>
    </template>
  </BaseModal>

  <!-- Modal Penilaian -->
  <BaseModal :open="assModal" :title="editingAssId ? 'Edit Komponen Nilai' : 'Tambah Komponen Nilai'" @close="assModal = false">
    <form class="space-y-3" @submit.prevent="saveAssessment">
      <div><label class="text-xs text-gray-700">Komponen <span class="text-red-500">*</span></label><input v-model="assForm.component" required placeholder="Laporan / Presentasi / Kinerja..." class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-xs text-gray-700">Nilai (0-100)</label><input v-model.number="assForm.score" type="number" min="0" max="100" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs text-gray-700">Bobot</label><input v-model.number="assForm.weight" type="number" min="0" step="0.5" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div><label class="text-xs text-gray-700">Catatan</label><input v-model="assForm.notes" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="assModal = false">Batal</button>
      <button :disabled="assSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveAssessment">Simpan</button>
    </template>
  </BaseModal>

  <!-- Modal Laporan -->
  <BaseModal :open="repModal" title="Submit Laporan" @close="repModal = false">
    <form class="space-y-3" @submit.prevent="saveReport">
      <div><label class="text-xs text-gray-700">Jenis Laporan <span class="text-red-500">*</span></label>
        <div class="flex gap-3 mt-1">
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" v-model="repForm.report_type" value="INDIVIDU" class="text-blue-600" />
            <span>👤 Individu</span>
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" v-model="repForm.report_type" value="KELOMPOK" class="text-purple-600" />
            <span>👥 Kelompok</span>
          </label>
        </div>
        <p v-if="repForm.report_type === 'KELOMPOK'" class="text-xs text-purple-600 mt-1">Hanya ketua kelompok yang dapat submit laporan kelompok.</p>
      </div>
      <div><label class="text-xs text-gray-700">Judul <span class="text-red-500">*</span></label><input v-model="repForm.title" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs text-gray-700">Abstrak</label><textarea v-model="repForm.abstract" rows="3" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs text-gray-700">Link File (Google Drive)</label><input v-model="repForm.file_url" placeholder="https://drive.google.com/..." class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="repModal = false">Batal</button>
      <button :disabled="repSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveReport">Submit</button>
    </template>
  </BaseModal>
</template>
