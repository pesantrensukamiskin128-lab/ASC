<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { ArrowLeftIcon, PlusIcon, TrashIcon, DocumentArrowUpIcon, LinkIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()
const loading = ref(true)
const thesis  = ref<any>(null)
const activeTab = ref('info')
const lecturers = ref<any[]>([])

// Role flags
const isAdmin     = auth.user?.roles?.includes('SUPER_ADMIN') || auth.user?.roles?.includes('ADMIN_AKADEMIK')
const isMahasiswa = auth.hasRole('MAHASISWA')
const isKaprodi   = auth.hasPermission('skripsi.approve')
const isDosenWali = auth.hasPermission('krs.approve')
const isDosen     = auth.hasRole('DOSEN') && !isMahasiswa

const canReviewTitle    = computed(() => isAdmin || isKaprodi || isDosenWali)
const canManageSeminar  = computed(() => isAdmin || isKaprodi)
const canAddSupervisor  = computed(() => isAdmin || isKaprodi)
const isMyThesis        = computed(() => isMahasiswa && auth.user?.student?.id === thesis.value?.student_id)
const isSupervisor      = computed(() => {
  if (!thesis.value || !auth.user?.lecturer) return false
  return thesis.value.supervisors?.some((s: any) => s.lecturer_id === auth.user?.lecturer?.id)
})

// Status labels & colors
const STATUS_LABELS: Record<string, string> = {
  DRAFT: 'Draft', PENGAJUAN_JUDUL: 'Pengajuan Judul', JUDUL_DITOLAK: 'Judul Ditolak',
  SEMINAR_PROPOSAL: 'Seminar Proposal', REVISI_PROPOSAL: 'Revisi Proposal',
  PEMERIKSAAN_REVISI: 'Pemeriksaan Revisi', PENUNJUKAN_PEMBIMBING: 'Penunjukan Pembimbing',
  BIMBINGAN: 'Bimbingan', SIDANG: 'Sidang Munaqosyah',
  REVISI_SIDANG: 'Revisi Sidang', SELESAI: 'Selesai', DIPUBLIKASIKAN: 'Dipublikasikan', GAGAL: 'Gagal',
}
const STATUS_COLORS: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600', PENGAJUAN_JUDUL: 'bg-blue-100 text-blue-700',
  JUDUL_DITOLAK: 'bg-red-100 text-red-600', SEMINAR_PROPOSAL: 'bg-indigo-100 text-indigo-700',
  REVISI_PROPOSAL: 'bg-yellow-100 text-yellow-700', PEMERIKSAAN_REVISI: 'bg-orange-100 text-orange-700',
  PENUNJUKAN_PEMBIMBING: 'bg-purple-100 text-purple-700', BIMBINGAN: 'bg-cyan-100 text-cyan-700',
  SIDANG: 'bg-orange-100 text-orange-700', REVISI_SIDANG: 'bg-yellow-100 text-yellow-700',
  SELESAI: 'bg-green-100 text-green-700', DIPUBLIKASIKAN: 'bg-emerald-100 text-emerald-700', GAGAL: 'bg-red-100 text-red-600',
}

const tabs = [
  { key: 'info', label: 'Informasi' },
  { key: 'seminar', label: 'Seminar Proposal' },
  { key: 'tim', label: 'Pembimbing & Penguji' },
  { key: 'bimbingan', label: 'Bimbingan' },
  { key: 'sidang', label: 'Sidang Munaqosyah' },
]

onMounted(async () => {
  try {
    const [tRes, lRes] = await Promise.all([
      api.get(`/theses/${route.params.id}`),
      api.get('/lecturers/all'),
    ])
    thesis.value  = tRes.data
    lecturers.value = lRes.data
  } finally { loading.value = false }
})

async function reload() {
  const { data } = await api.get(`/theses/${route.params.id}`)
  thesis.value = data
}

function formatDate(d: string | null) {
  return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-'
}
const guidanceCount   = computed(() => thesis.value?.guidances?.length ?? 0)
const latestProgress  = computed(() => thesis.value?.guidances?.[0]?.progress_percentage ?? 0)

// === ACTION HANDLERS ===

// Review judul (Kaprodi/Dosen Wali)
const reviewNote = ref('')
const reviewing  = ref(false)
async function reviewTitle(action: 'approve' | 'reject') {
  if (action === 'reject' && !reviewNote.value.trim()) { toast.warning('Mohon isi alasan penolakan.'); return }
  if (!confirm(action === 'approve' ? 'Setujui judul skripsi ini?' : 'Tolak judul skripsi ini?')) return
  reviewing.value = true
  try {
    await api.post(`/theses/${thesis.value.id}/review-title`, { action, admin_note: reviewNote.value })
    toast.success(action === 'approve' ? 'Judul disetujui.' : 'Judul ditolak.')
    reviewNote.value = ''
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { reviewing.value = false }
}

// Mahasiswa submit draft
const submitting = ref(false)
const submitLink = ref('')
async function submitToKaprodi() {
  if (!confirm('Ajukan judul skripsi ke Ka.Prodi / Dosen Pembimbing Akademik?')) return
  submitting.value = true
  try {
    await api.post(`/theses/${thesis.value.id}/submit`, { submission_link: submitLink.value })
    toast.success('Pengajuan berhasil dikirim.')
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { submitting.value = false }
}

// Mahasiswa resubmit setelah ditolak
async function resubmit() {
  if (!confirm('Kembalikan ke Draft untuk diedit dan diajukan ulang?')) return
  try { await api.post(`/theses/${thesis.value.id}/resubmit`); toast.success('Dikembalikan ke Draft.'); reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// Jadwal seminar proposal
const seminarModal   = ref(false)
const seminarSaving  = ref(false)
const seminarForm    = reactive({ seminar_date: '', room: '', examiner_ids: [] as number[] })
async function saveSeminar() {
  seminarSaving.value = true
  try {
    await api.post(`/theses/${thesis.value.id}/schedule-seminar`, seminarForm)
    toast.success('Seminar proposal dijadwalkan.')
    seminarModal.value = false
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { seminarSaving.value = false }
}

// Hasil seminar proposal
const seminarResultModal = ref(false)
const seminarResultForm  = reactive({ result: 'DISETUJUI', notes: '', seminar_type: 'PROPOSAL' })
async function saveSeminarResult() {
  try {
    await api.post(`/theses/${thesis.value.id}/seminar-result`, seminarResultForm)
    toast.success('Hasil seminar berhasil dicatat.')
    seminarResultModal.value = false
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// Input nilai seminar (penguji)
const scoreModal = ref(false)
const scoreForm  = reactive({ score: 0, notes: '', type: 'SEMINAR_PROPOSAL' })
async function saveScore() {
  try {
    await api.post(`/theses/${thesis.value.id}/seminar-score`, scoreForm)
    toast.success('Nilai berhasil disimpan.')
    scoreModal.value = false
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// Upload link revisi (mahasiswa)
const revisionLinkInput = ref('')
const savingRevision    = ref(false)
async function uploadRevisionLink() {
  if (!revisionLinkInput.value.trim()) { toast.warning('Masukkan link revisi.'); return }
  savingRevision.value = true
  try {
    await api.post(`/theses/${thesis.value.id}/revision-link`, { revision_link: revisionLinkInput.value })
    toast.success('Link revisi berhasil dikirim.')
    revisionLinkInput.value = ''
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { savingRevision.value = false }
}

// Penguji review revisi
const revisionReviewModal = ref(false)
const revisionReviewForm  = reactive({ result: 'SELESAI', notes: '' })
async function saveRevisionReview() {
  try {
    await api.post(`/theses/${thesis.value.id}/review-revision`, revisionReviewForm)
    toast.success('Review revisi berhasil.')
    revisionReviewModal.value = false
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// Penunjukan pembimbing (Kaprodi)
const supModal  = ref(false)
const supSaving = ref(false)
const supForm   = ref([{ id: '', role: 'PEMBIMBING_1' }, { id: '', role: 'PEMBIMBING_2' }])
async function assignSupervisors() {
  const payload = supForm.value.filter(s => s.id).map(s => ({ id: Number(s.id), role: s.role }))
  if (!payload.length) { toast.warning('Pilih minimal 1 dosen pembimbing.'); return }
  supSaving.value = true
  try {
    await api.post(`/theses/${thesis.value.id}/assign-supervisors`, { supervisors: payload })
    toast.success('Dosen pembimbing berhasil ditunjuk.')
    supModal.value = false
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { supSaving.value = false }
}

// Catat bimbingan
const gModal  = ref(false)
const gSaving = ref(false)
const gForm   = reactive({ guidance_date: '', topic: '', discussion: '', suggestion: '', chapter_reviewed: '', progress_percentage: 0, revision_link: '' })
async function saveGuidance() {
  gSaving.value = true
  try {
    await api.post(`/theses/${thesis.value.id}/guidances`, gForm)
    toast.success('Bimbingan berhasil dicatat.')
    gModal.value = false
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { gSaving.value = false }
}

// Dosen pembimbing nyatakan siap sidang
async function declareReady() {
  if (!confirm('Nyatakan skripsi ini siap untuk sidang munaqosyah?')) return
  try {
    await api.post(`/theses/${thesis.value.id}/ready-defense`)
    toast.success('Skripsi dinyatakan siap sidang.')
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// Jadwal sidang (Kaprodi)
const defenseModal  = ref(false)
const defenseSaving = ref(false)
const defenseForm   = reactive({ defense_date: '', defense_time: '', room: '', examiner_ids: [] as number[] })
async function saveDefense() {
  defenseSaving.value = true
  try {
    await api.post(`/theses/${thesis.value.id}/schedule-defense`, defenseForm)
    toast.success('Sidang berhasil dijadwalkan.')
    defenseModal.value = false
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { defenseSaving.value = false }
}

// Hasil sidang (Kaprodi)
const defResultModal = ref(false)
const defResultForm  = reactive({ result: 'DISETUJUI', notes: '', seminar_type: 'SIDANG' })
async function saveDefenseResult() {
  try {
    await api.post(`/theses/${thesis.value.id}/seminar-result`, defResultForm)
    toast.success('Hasil sidang berhasil dicatat.')
    defResultModal.value = false
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// Upload skripsi final (mahasiswa)
const finalFile    = ref<File | null>(null)
const uploadingFinal = ref(false)
async function uploadFinal() {
  if (!finalFile.value) { toast.warning('Pilih file PDF.'); return }
  uploadingFinal.value = true
  try {
    const fd = new FormData()
    fd.append('file', finalFile.value)
    await api.post(`/theses/${thesis.value.id}/upload-final`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success('Skripsi final berhasil diupload.')
    finalFile.value = null
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { uploadingFinal.value = false }
}

// Publikasi (admin)
async function publish() {
  if (!confirm('Publikasikan skripsi ini ke repository?')) return
  try {
    await api.post(`/theses/${thesis.value.id}/publish`)
    toast.success('Skripsi berhasil dipublikasikan.')
    reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// Hapus pembimbing/penguji
async function removeSupervisor(id: number) {
  if (!confirm('Hapus dosen pembimbing ini?')) return
  try { await api.delete(`/theses/${thesis.value.id}/supervisors/${id}`); toast.success('Dihapus.'); reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}
async function removeExaminer(id: number) {
  if (!confirm('Hapus penguji ini?')) return
  try { await api.delete(`/theses/${thesis.value.id}/examiners/${id}`); toast.success('Dihapus.'); reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="thesis" class="space-y-5 max-w-5xl mx-auto">

    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div class="flex-1">
        <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold', STATUS_COLORS[thesis.status] ?? 'bg-gray-100 text-gray-600']">
          {{ STATUS_LABELS[thesis.status] ?? thesis.status }}
        </span>
        <h1 class="text-lg font-bold text-gray-900 mt-1.5">{{ thesis.title }}</h1>
        <p class="text-sm text-gray-500">{{ thesis.student?.name }} · {{ thesis.student?.nim }} · {{ thesis.study_program?.name }}</p>
      </div>
    </div>

    <!-- Progress cards -->
    <div class="grid grid-cols-4 gap-3">
      <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
        <p class="text-xs text-gray-400">Bimbingan</p><p class="text-xl font-bold text-blue-700">{{ guidanceCount }}x</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
        <p class="text-xs text-gray-400">Progress</p><p class="text-xl font-bold text-purple-700">{{ latestProgress }}%</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
        <p class="text-xs text-gray-400">Nilai Akhir</p><p class="text-xl font-bold text-green-700">{{ thesis.final_score ?? '-' }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
        <p class="text-xs text-gray-400">Diajukan</p><p class="text-sm font-medium text-gray-700">{{ formatDate(thesis.submission_date) }}</p>
      </div>
    </div>

    <!-- === AKSI SESUAI STATUS & ROLE === -->

    <!-- Mahasiswa: submit draft -->
    <div v-if="isMyThesis && thesis.status === 'DRAFT'" class="bg-blue-50 border border-blue-200 rounded-xl p-4 space-y-3">
      <p class="text-sm font-semibold text-blue-800">📋 Draft — Siap diajukan ke Ka.Prodi?</p>
      <div>
        <label class="text-xs text-blue-700 font-medium">Link Dokumen Pengajuan (Google Drive, opsional)</label>
        <input v-model="submitLink" type="url" placeholder="https://drive.google.com/..." class="w-full mt-1 px-3 py-2 border border-blue-300 rounded-lg text-sm" />
      </div>
      <button :disabled="submitting" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="submitToKaprodi">
        {{ submitting ? 'Mengirim...' : '📤 Ajukan ke Ka.Prodi / Dosen PA' }}
      </button>
    </div>

    <!-- Mahasiswa: resubmit setelah ditolak -->
    <div v-if="isMyThesis && thesis.status === 'JUDUL_DITOLAK'" class="bg-red-50 border border-red-200 rounded-xl p-4">
      <p class="text-sm font-semibold text-red-700 mb-1">❌ Judul Ditolak</p>
      <p v-if="thesis.admin_note" class="text-sm text-red-600 mb-3">Catatan: {{ thesis.admin_note }}</p>
      <button class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg" @click="resubmit">↩ Edit & Ajukan Ulang</button>
    </div>

    <!-- Kaprodi/DosenWali: review judul -->
    <div v-if="canReviewTitle && thesis.status === 'PENGAJUAN_JUDUL'" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 space-y-3">
      <p class="text-sm font-semibold text-yellow-800">📝 Pengajuan Judul — Menunggu Review</p>
      <a v-if="thesis.submission_link" :href="thesis.submission_link" target="_blank" class="text-sm text-blue-600 underline flex items-center gap-1">
        <LinkIcon class="w-4 h-4" /> Lihat Dokumen Pengajuan
      </a>
      <textarea v-model="reviewNote" rows="2" placeholder="Catatan untuk mahasiswa (wajib jika ditolak)..." class="w-full px-3 py-2 border rounded-lg text-sm" />
      <div class="flex gap-2">
        <button :disabled="reviewing" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg" @click="reviewTitle('approve')">✓ Setujui Judul</button>
        <button :disabled="reviewing" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg" @click="reviewTitle('reject')">✗ Tolak Judul</button>
      </div>
    </div>

    <!-- Kaprodi: jadwalkan seminar proposal -->
    <div v-if="canManageSeminar && thesis.status === 'SEMINAR_PROPOSAL'" class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 flex items-center justify-between">
      <p class="text-sm font-semibold text-indigo-800">📅 Jadwalkan Seminar Proposal</p>
      <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg" @click="Object.assign(seminarForm,{seminar_date:'',room:'',examiner_ids:[]}); seminarModal=true">Jadwalkan</button>
    </div>

    <!-- Mahasiswa: upload link revisi -->
    <div v-if="isMyThesis && ['REVISI_PROPOSAL','REVISI_SIDANG'].includes(thesis.status)" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 space-y-3">
      <p class="text-sm font-semibold text-yellow-800">✏️ {{ thesis.status === 'REVISI_PROPOSAL' ? 'Revisi Proposal' : 'Revisi Sidang' }}</p>
      <p v-if="thesis.admin_note" class="text-xs text-yellow-700">Catatan: {{ thesis.admin_note }}</p>
      <div class="flex gap-2">
        <input v-model="revisionLinkInput" type="url" placeholder="Link Google Drive dokumen revisi..." class="flex-1 px-3 py-2 border rounded-lg text-sm" />
        <button :disabled="savingRevision" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg" @click="uploadRevisionLink">Kirim Revisi</button>
      </div>
    </div>

    <!-- Penguji: review revisi -->
    <div v-if="(canManageSeminar || isDosen) && thesis.status === 'PEMERIKSAAN_REVISI'" class="bg-orange-50 border border-orange-200 rounded-xl p-4 flex items-center justify-between">
      <div>
        <p class="text-sm font-semibold text-orange-800">🔍 Pemeriksaan Revisi Proposal</p>
        <a v-if="thesis.revision_link" :href="thesis.revision_link" target="_blank" class="text-xs text-blue-600 underline">Lihat Revisi Mahasiswa</a>
      </div>
      <button class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg" @click="Object.assign(revisionReviewForm,{result:'SELESAI',notes:''}); revisionReviewModal=true">Periksa Revisi</button>
    </div>

    <!-- Kaprodi: tunjuk pembimbing -->
    <div v-if="canAddSupervisor && thesis.status === 'PENUNJUKAN_PEMBIMBING'" class="bg-purple-50 border border-purple-200 rounded-xl p-4 flex items-center justify-between">
      <p class="text-sm font-semibold text-purple-800">👨‍🏫 Tunjuk Dosen Pembimbing</p>
      <button class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg" @click="supModal=true">Tunjuk Pembimbing</button>
    </div>

    <!-- Dosen pembimbing: nyatakan siap sidang -->
    <div v-if="isSupervisor && thesis.status === 'BIMBINGAN'" class="bg-cyan-50 border border-cyan-200 rounded-xl p-4 flex items-center justify-between">
      <p class="text-sm font-semibold text-cyan-800">✅ Nyatakan mahasiswa siap sidang?</p>
      <button class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-medium rounded-lg" @click="declareReady">Siap Sidang</button>
    </div>

    <!-- Kaprodi: jadwalkan sidang -->
    <div v-if="canManageSeminar && thesis.status === 'SIDANG'" class="bg-orange-50 border border-orange-200 rounded-xl p-4 flex items-center justify-between">
      <p class="text-sm font-semibold text-orange-800">⚖️ Jadwalkan Sidang Munaqosyah</p>
      <div class="flex gap-2">
        <button class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg" @click="Object.assign(defenseForm,{defense_date:'',defense_time:'',room:'',examiner_ids:[]}); defenseModal=true">Jadwalkan Sidang</button>
        <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg" @click="Object.assign(defResultForm,{result:'DISETUJUI',notes:'',seminar_type:'SIDANG'}); defResultModal=true">Input Hasil Sidang</button>
      </div>
    </div>

    <!-- Mahasiswa: upload skripsi final -->
    <div v-if="isMyThesis && ['SELESAI','REVISI_SIDANG'].includes(thesis.status)" class="bg-green-50 border border-green-200 rounded-xl p-4 space-y-3">
      <p class="text-sm font-semibold text-green-800">📄 Upload Skripsi Final (PDF, maks 30MB)</p>
      <div class="flex gap-2">
        <input type="file" accept=".pdf" class="flex-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-green-50 file:text-green-700 border border-gray-200 rounded-lg" @change="(e) => finalFile = (e.target as HTMLInputElement).files?.[0] ?? null" />
        <button :disabled="uploadingFinal || !finalFile" class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-300 text-white text-sm font-medium rounded-lg" @click="uploadFinal">
          {{ uploadingFinal ? 'Uploading...' : 'Upload' }}
        </button>
      </div>
      <p v-if="thesis.final_pdf_path" class="text-xs text-green-600">✓ File skripsi sudah diupload.</p>
    </div>

    <!-- Admin: publikasi -->
    <div v-if="isAdmin && thesis.status === 'SELESAI'" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center justify-between">
      <p class="text-sm font-semibold text-emerald-800">🌐 Publikasikan ke Repository</p>
      <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg" @click="publish">Publikasikan</button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-gray-200 overflow-x-auto">
      <button v-for="t in tabs" :key="t.key"
        :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition-colors', activeTab===t.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
        @click="activeTab = t.key">{{ t.label }}</button>
    </div>

    <!-- TAB: INFO -->
    <div v-if="activeTab === 'info'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div><span class="text-xs text-gray-400">Jenis</span><p class="font-medium">{{ thesis.type }}</p></div>
        <div><span class="text-xs text-gray-400">Program Studi</span><p>{{ thesis.study_program?.name }}</p></div>
        <div v-if="thesis.research_field"><span class="text-xs text-gray-400">Bidang Penelitian</span><p>{{ thesis.research_field }}</p></div>
        <div v-if="thesis.keywords"><span class="text-xs text-gray-400">Kata Kunci</span><p>{{ thesis.keywords }}</p></div>
      </div>
      <div v-if="thesis.title_english" class="pt-3 border-t">
        <p class="text-xs text-gray-400 mb-1">Judul (English)</p><p class="text-sm italic text-gray-700">{{ thesis.title_english }}</p>
      </div>
      <div v-if="thesis.proposal_file_url" class="pt-3 border-t">
        <p class="text-xs text-gray-400 mb-1">File Proposal</p>
        <a :href="thesis.proposal_file_url" target="_blank" class="text-sm text-blue-600 underline">📄 Buka File Proposal</a>
      </div>
      <div v-if="thesis.revision_link" class="pt-3 border-t">
        <p class="text-xs text-gray-400 mb-1">Link Revisi Terakhir</p>
        <a :href="thesis.revision_link" target="_blank" class="text-sm text-blue-600 underline">🔗 Lihat Revisi</a>
      </div>
      <div v-if="thesis.abstract" class="pt-3 border-t">
        <p class="text-xs text-gray-400 mb-1">Abstrak</p><p class="text-sm text-gray-700 whitespace-pre-line">{{ thesis.abstract }}</p>
      </div>
      <div v-if="thesis.admin_note" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
        <p class="text-xs text-yellow-600 font-medium">Catatan Admin/Kaprodi</p>
        <p class="text-sm text-yellow-800 mt-0.5">{{ thesis.admin_note }}</p>
      </div>
      <div v-if="thesis.is_published" class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
        <p class="text-xs text-emerald-600 font-medium">🌐 Dipublikasikan</p>
        <p class="text-xs text-emerald-700 mt-0.5">{{ formatDate(thesis.published_at) }}</p>
        <a v-if="thesis.repository_url" :href="thesis.repository_url" target="_blank" class="text-sm text-blue-600 underline">Lihat di Repository</a>
      </div>
    </div>

    <!-- TAB: SEMINAR PROPOSAL -->
    <div v-if="activeTab === 'seminar'" class="space-y-4">
      <!-- Nilai penguji -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-gray-800">Nilai Penguji Seminar Proposal</h3>
          <button v-if="isDosen || isAdmin" class="text-xs text-blue-600 font-medium" @click="Object.assign(scoreForm,{score:0,notes:'',type:'SEMINAR_PROPOSAL'}); scoreModal=true">+ Input Nilai Saya</button>
        </div>
        <div v-if="!thesis.revision_reviews?.filter((r:any)=>r.type==='SEMINAR_PROPOSAL').length" class="text-gray-400 text-sm">Belum ada nilai.</div>
        <div v-else class="space-y-2">
          <div v-for="r in thesis.revision_reviews?.filter((r:any)=>r.type==='SEMINAR_PROPOSAL')" :key="r.id" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
            <div class="flex-1"><p class="text-sm font-medium text-gray-900">{{ r.examiner?.name }}</p><p class="text-xs text-gray-500">{{ r.notes }}</p></div>
            <span class="text-2xl font-bold text-blue-700">{{ r.score ?? '-' }}</span>
          </div>
        </div>
      </div>
      <!-- Hasil seminar -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-gray-800">Hasil Seminar Proposal</h3>
          <button v-if="canManageSeminar && ['SEMINAR_PROPOSAL','REVISI_PROPOSAL','PEMERIKSAAN_REVISI'].includes(thesis.status)" class="text-xs text-purple-600 font-medium" @click="Object.assign(seminarResultForm,{result:'DISETUJUI',notes:'',seminar_type:'PROPOSAL'}); seminarResultModal=true">Catat Hasil</button>
        </div>
        <div v-for="r in thesis.seminar_results?.filter((r:any)=>r.seminar_type==='PROPOSAL')" :key="r.id" class="p-3 bg-gray-50 rounded-lg">
          <div class="flex items-center gap-2 mb-1">
            <span :class="['text-xs px-2 py-0.5 rounded-full font-semibold', r.result==='DISETUJUI'?'bg-green-100 text-green-700':r.result==='REVISI'?'bg-yellow-100 text-yellow-700':'bg-red-100 text-red-600']">{{ r.result }}</span>
            <span class="text-xs text-gray-400">{{ formatDate(r.seminar_date) }}</span>
          </div>
          <p v-if="r.notes" class="text-sm text-gray-700">{{ r.notes }}</p>
        </div>
        <p v-if="!thesis.seminar_results?.filter((r:any)=>r.seminar_type==='PROPOSAL').length" class="text-gray-400 text-sm">Belum ada hasil seminar.</p>
      </div>
    </div>

    <!-- TAB: TIM -->
    <div v-if="activeTab === 'tim'" class="space-y-4">
      <div v-if="['PENUNJUKAN_PEMBIMBING','BIMBINGAN'].includes(thesis.status) && canAddSupervisor" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700">
        💡 Ka.Prodi dapat menunjuk dosen pembimbing setelah mahasiswa lulus seminar proposal.
      </div>
      <div v-else-if="['DRAFT','PENGAJUAN_JUDUL','SEMINAR_PROPOSAL','REVISI_PROPOSAL','PEMERIKSAAN_REVISI'].includes(thesis.status)" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-xs text-yellow-700">
        ⏳ Dosen pembimbing akan ditunjuk Ka.Prodi setelah lulus seminar proposal.
      </div>
      <!-- Pembimbing -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold">Dosen Pembimbing</h3>
          <button v-if="canAddSupervisor && ['PENUNJUKAN_PEMBIMBING','BIMBINGAN','SIDANG'].includes(thesis.status)" class="text-xs text-blue-600" @click="supModal=true">Ganti Pembimbing</button>
        </div>
        <div v-if="!thesis.supervisors?.length" class="text-gray-400 text-sm">Belum ada pembimbing.</div>
        <div v-else class="space-y-2">
          <div v-for="s in thesis.supervisors" :key="s.id" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs">{{ s.lecturer?.name?.charAt(0) }}</div>
            <div class="flex-1"><p class="font-medium text-gray-900 text-sm">{{ s.lecturer?.name }}</p><p class="text-xs text-gray-500">{{ s.role?.replace(/_/g,' ') }}</p></div>
            <button v-if="canAddSupervisor" class="p-1 text-red-500 hover:bg-red-50 rounded" @click="removeSupervisor(s.id)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </div>
      </div>
      <!-- Penguji -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold">Dewan Penguji</h3>
        </div>
        <div v-if="!thesis.examiners?.length" class="text-gray-400 text-sm">Belum ada penguji.</div>
        <div v-else class="space-y-2">
          <div v-for="e in thesis.examiners" :key="e.id" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-xs">{{ e.lecturer?.name?.charAt(0) }}</div>
            <div class="flex-1"><p class="font-medium text-gray-900 text-sm">{{ e.lecturer?.name }}</p><p class="text-xs text-gray-500">{{ e.role?.replace(/_/g,' ') }}</p></div>
            <button v-if="canManageSeminar" class="p-1 text-red-500 hover:bg-red-50 rounded" @click="removeExaminer(e.id)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: BIMBINGAN -->
    <div v-if="activeTab === 'bimbingan'" class="space-y-4">
      <div class="flex justify-end">
        <button v-if="(isSupervisor || isAdmin) && thesis.status === 'BIMBINGAN'" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="Object.assign(gForm,{guidance_date:new Date().toISOString().slice(0,10),topic:'',discussion:'',suggestion:'',chapter_reviewed:'',progress_percentage:0,revision_link:''}); gModal=true">
          <PlusIcon class="w-3.5 h-3.5" /> Catat Bimbingan
        </button>
      </div>
      <div v-if="!thesis.guidances?.length" class="text-center py-8 text-gray-400 text-sm">Belum ada riwayat bimbingan.</div>
      <div v-else class="space-y-3">
        <div v-for="g in thesis.guidances" :key="g.id" class="bg-white rounded-xl border border-gray-200 p-4">
          <div class="flex items-center gap-3 mb-2">
            <span class="text-xs font-medium text-gray-500">{{ formatDate(g.guidance_date) }}</span>
            <span class="text-xs text-gray-400">{{ g.supervisor?.name }}</span>
            <span v-if="g.progress_percentage" class="ml-auto text-xs font-bold text-purple-600">{{ g.progress_percentage }}%</span>
          </div>
          <p class="text-sm font-medium text-gray-800">{{ g.topic }}</p>
          <p v-if="g.discussion" class="text-xs text-gray-600 mt-1">{{ g.discussion }}</p>
          <p v-if="g.suggestion" class="text-xs text-blue-700 mt-1">💡 {{ g.suggestion }}</p>
          <a v-if="g.revision_link" :href="g.revision_link" target="_blank" class="text-xs text-blue-600 underline mt-1 block">🔗 Link Revisi</a>
        </div>
      </div>
    </div>

    <!-- TAB: SIDANG -->
    <div v-if="activeTab === 'sidang'" class="space-y-4">
      <!-- Nilai sidang -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold">Nilai Sidang Munaqosyah</h3>
          <button v-if="isDosen || isAdmin" class="text-xs text-blue-600" @click="Object.assign(scoreForm,{score:0,notes:'',type:'SIDANG_AKHIR'}); scoreModal=true">+ Input Nilai</button>
        </div>
        <div v-if="!thesis.revision_reviews?.filter((r:any)=>r.type==='SIDANG_AKHIR').length" class="text-gray-400 text-sm">Belum ada nilai sidang.</div>
        <div v-else class="space-y-2">
          <div v-for="r in thesis.revision_reviews?.filter((r:any)=>r.type==='SIDANG_AKHIR')" :key="r.id" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
            <div class="flex-1"><p class="text-sm font-medium">{{ r.examiner?.name }}</p><p class="text-xs text-gray-500">{{ r.notes }}</p></div>
            <span class="text-2xl font-bold text-blue-700">{{ r.score ?? '-' }}</span>
          </div>
        </div>
      </div>
      <!-- Hasil sidang -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold mb-3">Hasil Sidang Munaqosyah</h3>
        <div v-for="r in thesis.seminar_results?.filter((r:any)=>r.seminar_type==='SIDANG')" :key="r.id" class="p-3 bg-gray-50 rounded-lg mb-2">
          <div class="flex items-center gap-2 mb-1">
            <span :class="['text-xs px-2 py-0.5 rounded-full font-semibold', r.result==='DISETUJUI'?'bg-green-100 text-green-700':r.result==='REVISI'?'bg-yellow-100 text-yellow-700':'bg-red-100 text-red-600']">{{ r.result }}</span>
            <span class="text-xs text-gray-400">{{ formatDate(r.seminar_date) }}</span>
          </div>
          <p v-if="r.notes" class="text-sm text-gray-700">{{ r.notes }}</p>
        </div>
        <p v-if="!thesis.seminar_results?.filter((r:any)=>r.seminar_type==='SIDANG').length" class="text-gray-400 text-sm">Belum ada hasil sidang.</p>
        <!-- Nilai akhir -->
        <div v-if="thesis.final_score" class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg text-center">
          <p class="text-xs text-green-600">Nilai Akhir Sidang</p>
          <p class="text-3xl font-bold text-green-700">{{ thesis.final_score }}</p>
        </div>
      </div>
    </div>

  </div>

  <!-- Modal: Jadwal Seminar Proposal -->
  <BaseModal :open="seminarModal" title="Jadwalkan Seminar Proposal" size="xl" @close="seminarModal = false">
    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div><label class="text-xs font-medium text-gray-700">Tanggal Seminar *</label><input v-model="seminarForm.seminar_date" type="date" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs font-medium text-gray-700">Ruangan</label><input v-model="seminarForm.room" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-700">Penguji Seminar Proposal (pilih 2-3)</label>
        <div class="mt-1 space-y-1 max-h-48 overflow-y-auto border rounded-lg p-2">
          <label v-for="l in lecturers" :key="l.id" class="flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded cursor-pointer text-sm">
            <input type="checkbox" :value="l.id" v-model="seminarForm.examiner_ids" class="rounded" />
            {{ l.name }}
          </label>
        </div>
        <p class="text-xs text-gray-400 mt-1">Dipilih: {{ seminarForm.examiner_ids.length }} penguji</p>
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="seminarModal = false">Batal</button>
      <button :disabled="seminarSaving" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white text-sm font-medium rounded-lg" @click="saveSeminar">{{ seminarSaving ? 'Menyimpan...' : 'Jadwalkan' }}</button>
    </template>
  </BaseModal>

  <!-- Modal: Hasil Seminar Proposal -->
  <BaseModal :open="seminarResultModal" title="Catat Hasil Seminar Proposal" @close="seminarResultModal = false">
    <div class="space-y-3">
      <div><label class="text-xs font-medium text-gray-700">Hasil Seminar</label>
        <select v-model="seminarResultForm.result" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
          <option value="DISETUJUI">✓ Disetujui — Lanjut Penunjukan Pembimbing</option>
          <option value="REVISI">✏️ Revisi — Mahasiswa harus merevisi proposal</option>
          <option value="TIDAK_LULUS">✗ Tidak Lulus</option>
        </select>
      </div>
      <div><label class="text-xs font-medium text-gray-700">Catatan</label><textarea v-model="seminarResultForm.notes" rows="3" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="seminarResultModal = false">Batal</button>
      <button class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg" @click="saveSeminarResult">Simpan Hasil</button>
    </template>
  </BaseModal>

  <!-- Modal: Input Nilai Penguji -->
  <BaseModal :open="scoreModal" title="Input Nilai" @close="scoreModal = false">
    <div class="space-y-3">
      <div><label class="text-xs font-medium text-gray-700">Tipe</label>
        <select v-model="scoreForm.type" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
          <option value="SEMINAR_PROPOSAL">Seminar Proposal</option>
          <option value="SIDANG_AKHIR">Sidang Munaqosyah</option>
        </select>
      </div>
      <div><label class="text-xs font-medium text-gray-700">Nilai (0–100)</label><input v-model.number="scoreForm.score" type="number" min="0" max="100" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs font-medium text-gray-700">Catatan</label><textarea v-model="scoreForm.notes" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="scoreModal = false">Batal</button>
      <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="saveScore">Simpan Nilai</button>
    </template>
  </BaseModal>

  <!-- Modal: Review Revisi -->
  <BaseModal :open="revisionReviewModal" title="Periksa Revisi Proposal" @close="revisionReviewModal = false">
    <div class="space-y-3">
      <div v-if="thesis?.revision_link" class="p-3 bg-blue-50 rounded-lg">
        <p class="text-xs text-blue-600 mb-1">Link Revisi Mahasiswa</p>
        <a :href="thesis.revision_link" target="_blank" class="text-sm text-blue-700 underline break-all">{{ thesis.revision_link }}</a>
      </div>
      <div><label class="text-xs font-medium text-gray-700">Hasil Pemeriksaan</label>
        <select v-model="revisionReviewForm.result" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
          <option value="SELESAI">✓ Selesai — Lanjut Penunjukan Pembimbing</option>
          <option value="PERLU_REVISI">✏️ Perlu Revisi Lagi</option>
          <option value="SIAP_SIDANG">🎯 Siap Sidang Munaqosyah</option>
        </select>
      </div>
      <div><label class="text-xs font-medium text-gray-700">Catatan</label><textarea v-model="revisionReviewForm.notes" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="revisionReviewModal = false">Batal</button>
      <button class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg" @click="saveRevisionReview">Simpan Review</button>
    </template>
  </BaseModal>

  <!-- Modal: Tunjuk Pembimbing -->
  <BaseModal :open="supModal" title="Tunjuk Dosen Pembimbing" size="xl" @close="supModal = false">
    <div class="space-y-4">
      <div v-for="(s, i) in supForm" :key="i" class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
          <label class="text-xs font-medium text-gray-700">Dosen Pembimbing {{ i + 1 }}</label>
          <select v-model="s.id" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
            <option value="">-- Pilih Dosen --</option>
            <option v-for="l in lecturers" :key="l.id" :value="l.id">{{ l.name }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Peran</label>
          <select v-model="s.role" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
            <option value="PEMBIMBING_1">Pembimbing 1</option>
            <option value="PEMBIMBING_2">Pembimbing 2</option>
            <option value="PEMBIMBING_3">Pembimbing 3</option>
          </select>
        </div>
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="supModal = false">Batal</button>
      <button :disabled="supSaving" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-400 text-white text-sm font-medium rounded-lg" @click="assignSupervisors">{{ supSaving ? 'Menyimpan...' : 'Tunjuk Pembimbing' }}</button>
    </template>
  </BaseModal>

  <!-- Modal: Catat Bimbingan -->
  <BaseModal :open="gModal" title="Catat Bimbingan" size="xl" @close="gModal = false">
    <form class="space-y-3" @submit.prevent="saveGuidance">
      <div class="grid grid-cols-3 gap-3">
        <div><label class="text-xs text-gray-700">Tanggal *</label><input v-model="gForm.guidance_date" type="date" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs text-gray-700">Bab/Bagian</label><input v-model="gForm.chapter_reviewed" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" placeholder="BAB I" /></div>
        <div><label class="text-xs text-gray-700">Progress (%)</label><input v-model.number="gForm.progress_percentage" type="number" min="0" max="100" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div><label class="text-xs text-gray-700">Topik *</label><input v-model="gForm.topic" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs text-gray-700">Pembahasan</label><textarea v-model="gForm.discussion" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs text-gray-700">Saran / Catatan Revisi</label><textarea v-model="gForm.suggestion" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs text-gray-700">Link Dokumen Revisi (Google Drive)</label><input v-model="gForm.revision_link" type="url" placeholder="https://drive.google.com/..." class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="gModal = false">Batal</button>
      <button :disabled="gSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveGuidance">{{ gSaving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>

  <!-- Modal: Jadwal Sidang Munaqosyah -->
  <BaseModal :open="defenseModal" title="Jadwalkan Sidang Munaqosyah" size="xl" @close="defenseModal = false">
    <div class="space-y-4">
      <div class="grid grid-cols-3 gap-3">
        <div><label class="text-xs font-medium text-gray-700">Tanggal *</label><input v-model="defenseForm.defense_date" type="date" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs font-medium text-gray-700">Waktu</label><input v-model="defenseForm.defense_time" type="time" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs font-medium text-gray-700">Ruangan</label><input v-model="defenseForm.room" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-700">Dewan Penguji Sidang (pilih 3)</label>
        <div class="mt-1 space-y-1 max-h-48 overflow-y-auto border rounded-lg p-2">
          <label v-for="l in lecturers" :key="l.id" class="flex items-center gap-2 p-1.5 hover:bg-gray-50 rounded cursor-pointer text-sm">
            <input type="checkbox" :value="l.id" v-model="defenseForm.examiner_ids" class="rounded" />
            {{ l.name }}
          </label>
        </div>
        <p class="text-xs text-gray-400 mt-1">Dipilih: {{ defenseForm.examiner_ids.length }} penguji</p>
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="defenseModal = false">Batal</button>
      <button :disabled="defenseSaving" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:bg-orange-400 text-white text-sm font-medium rounded-lg" @click="saveDefense">{{ defenseSaving ? 'Menyimpan...' : 'Jadwalkan' }}</button>
    </template>
  </BaseModal>

  <!-- Modal: Hasil Sidang -->
  <BaseModal :open="defResultModal" title="Catat Hasil Sidang Munaqosyah" @close="defResultModal = false">
    <div class="space-y-3">
      <div><label class="text-xs font-medium text-gray-700">Hasil Sidang</label>
        <select v-model="defResultForm.result" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
          <option value="DISETUJUI">🎓 Lulus</option>
          <option value="REVISI">✏️ Lulus dengan Revisi</option>
          <option value="TIDAK_LULUS">✗ Tidak Lulus</option>
        </select>
      </div>
      <div><label class="text-xs font-medium text-gray-700">Catatan</label><textarea v-model="defResultForm.notes" rows="3" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="defResultModal = false">Batal</button>
      <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg" @click="saveDefenseResult">Simpan Hasil</button>
    </template>
  </BaseModal>
</template>
