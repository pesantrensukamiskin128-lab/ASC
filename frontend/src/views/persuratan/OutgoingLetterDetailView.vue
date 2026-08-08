<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const previewLoading = ref(false)
const downloadLoading = ref(false)
const showPdfModal = ref(false)
const pdfBlobUrl = ref('')

const letter = ref<any>(null)
const loading = ref(true)
const actionLoading = ref(false)
const revisionNote = ref('')
const showRevisionModal = ref(false)
const revisionAction = ref<'review' | 'sign'>('review')

// Distribusi
const showDistributeModal = ref(false)
const internalRecipientIds = ref<number[]>([])
const allUsers = ref<any[]>([])
const distributeSearch = ref('')
const distributeFilter = ref('all')
const distributeProdi = ref('')
const prodiList = ref<any[]>([])

const filteredDistributeUsers = computed(() => {
  let users = allUsers.value
  if (distributeFilter.value === 'dosen') {
    users = users.filter((u: any) => u.role === 'DOSEN' && !u.has_position)
  } else if (distributeFilter.value === 'mahasiswa') {
    users = users.filter((u: any) => u.role === 'MAHASISWA')
  } else if (distributeFilter.value === 'struktural') {
    users = users.filter((u: any) => u.has_position)
  }
  if (distributeProdi.value && (distributeFilter.value === 'dosen' || distributeFilter.value === 'mahasiswa')) {
    users = users.filter((u: any) => u.study_program_id == distributeProdi.value)
  }
  if (distributeSearch.value.trim()) {
    const q = distributeSearch.value.toLowerCase()
    users = users.filter((u: any) => u.name.toLowerCase().includes(q))
  }
  return users
})

const isAllFilteredSelected = computed(() => {
  if (!filteredDistributeUsers.value.length) return false
  return filteredDistributeUsers.value.every((u: any) => internalRecipientIds.value.includes(u.id))
})

function toggleSelectAll() {
  const ids = filteredDistributeUsers.value.map((u: any) => u.id)
  if (isAllFilteredSelected.value) {
    internalRecipientIds.value = internalRecipientIds.value.filter(id => !ids.includes(id))
  } else {
    const merged = new Set([...internalRecipientIds.value, ...ids])
    internalRecipientIds.value = [...merged]
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/outgoing-letters/${route.params.id}`)
    letter.value = data
    if (data.status === 'DITANDATANGANI') {
      const [usersRes, prodiRes] = await Promise.all([
        api.get('/user-list'),
        api.get('/study-programs/all'),
      ])
      allUsers.value = usersRes.data
      prodiList.value = prodiRes.data
    }
  } catch { toast.error('Gagal memuat data surat.') }
  finally { loading.value = false }
})

const canReview = computed(() => letter.value?.status === 'MENUNGGU_PEMERIKSA' && letter.value?.reviewer_id === auth.user?.id)
const canSign = computed(() => letter.value?.status === 'MENUNGGU_PENANDATANGAN' && letter.value?.signer_id === auth.user?.id)
const canDistribute = computed(() => letter.value?.status === 'DITANDATANGANI' && auth.hasPermission('surat-keluar.send'))

const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600 border-gray-200',
  MENUNGGU_PEMERIKSA: 'bg-yellow-100 text-yellow-700 border-yellow-200',
  MENUNGGU_PENANDATANGAN: 'bg-blue-100 text-blue-700 border-blue-200',
  REVISI_PEMERIKSA: 'bg-red-100 text-red-600 border-red-200',
  REVISI_PENANDATANGAN: 'bg-red-100 text-red-600 border-red-200',
  DITANDATANGANI: 'bg-green-100 text-green-700 border-green-200',
  TERKIRIM: 'bg-emerald-100 text-emerald-700 border-emerald-200',
}
const statusLabel: Record<string, string> = {
  DRAFT: 'Draft', MENUNGGU_PEMERIKSA: 'Menunggu Pemeriksa', MENUNGGU_PENANDATANGAN: 'Menunggu Tanda Tangan',
  REVISI_PEMERIKSA: 'Revisi dari Pemeriksa', REVISI_PENANDATANGAN: 'Revisi dari Penandatangan',
  DITANDATANGANI: 'Ditandatangani', TERKIRIM: 'Terkirim',
}

const approvalSteps = computed(() => {
  if (!letter.value) return []
  const steps = []
  // Step 1: Dibuat
  steps.push({
    label: 'Dibuat oleh',
    name: letter.value.creator?.name || '-',
    role: 'Admin',
    done: true,
    date: letter.value.created_at,
  })
  // Step 2: Diperiksa (reviewer)
  if (letter.value.reviewer) {
    const reviewDone = !['DRAFT', 'MENUNGGU_PEMERIKSA', 'REVISI_PEMERIKSA'].includes(letter.value.status)
    steps.push({
      label: 'Diperiksa',
      name: letter.value.reviewer?.name || '-',
      role: 'Pemeriksa',
      done: reviewDone,
      date: reviewDone ? letter.value.reviewed_at : null,
    })
  }
  // Step 3: Ditandatangani
  const signDone = ['DITANDATANGANI', 'TERKIRIM'].includes(letter.value.status)
  steps.push({
    label: 'Ditandatangani',
    name: letter.value.signer?.name || '-',
    role: letter.value.signer_position || 'Penandatangan',
    done: signDone,
    date: signDone ? letter.value.signed_at : null,
  })
  // Step 4: Terkirim
  if (letter.value.status === 'TERKIRIM') {
    steps.push({ label: 'Didistribusikan', name: '', role: '', done: true, date: letter.value.distributed_at })
  }
  return steps
})

async function handlePreviewPdf() {
  previewLoading.value = true
  try {
    const res = await api.get(`/outgoing-letters/${letter.value.id}/preview-pdf`, { responseType: 'blob' })
    const blob = new Blob([res.data], { type: 'application/pdf' })
    pdfBlobUrl.value = URL.createObjectURL(blob)
    showPdfModal.value = true
  } catch (e: any) { toast.error('Gagal memuat preview PDF.') }
  finally { previewLoading.value = false }
}

async function handleDownloadPdf() {
  downloadLoading.value = true
  try {
    const res = await api.get(`/outgoing-letters/${letter.value.id}/pdf`, { responseType: 'blob' })
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `surat-${letter.value.letter_number?.replace(/\//g, '-') || letter.value.id}.pdf`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch (e: any) { toast.error('Gagal mengunduh PDF.') }
  finally { downloadLoading.value = false }
}

function closePdfModal() {
  showPdfModal.value = false
  if (pdfBlobUrl.value) { URL.revokeObjectURL(pdfBlobUrl.value); pdfBlobUrl.value = '' }
}

async function handleReview(action: 'approve' | 'revise') {
  if (action === 'revise') { revisionAction.value = 'review'; showRevisionModal.value = true; return }
  actionLoading.value = true
  try {
    await api.post(`/outgoing-letters/${letter.value.id}/review`, { action: 'approve' })
    toast.success('Surat diperiksa dan diteruskan ke penandatangan.')
    const { data } = await api.get(`/outgoing-letters/${route.params.id}`)
    letter.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { actionLoading.value = false }
}

async function handleSign(action: 'sign' | 'revise') {
  if (action === 'revise') { revisionAction.value = 'sign'; showRevisionModal.value = true; return }
  actionLoading.value = true
  try {
    await api.post(`/outgoing-letters/${letter.value.id}/sign`, { action: 'sign' })
    toast.success('Surat berhasil ditandatangani!')
    const { data } = await api.get(`/outgoing-letters/${route.params.id}`)
    letter.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { actionLoading.value = false }
}

async function submitRevision() {
  if (!revisionNote.value.trim()) { toast.error('Catatan revisi wajib diisi.'); return }
  actionLoading.value = true
  try {
    const endpoint = revisionAction.value === 'review' ? 'review' : 'sign'
    await api.post(`/outgoing-letters/${letter.value.id}/${endpoint}`, { action: 'revise', revision_note: revisionNote.value })
    toast.success('Surat dikembalikan untuk revisi.')
    showRevisionModal.value = false
    revisionNote.value = ''
    const { data } = await api.get(`/outgoing-letters/${route.params.id}`)
    letter.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { actionLoading.value = false }
}

async function handleDistribute() {
  actionLoading.value = true
  try {
    await api.post(`/outgoing-letters/${letter.value.id}/distribute`, {
      internal_recipient_ids: internalRecipientIds.value,
      external_recipients: letter.value.external_recipients,
    })
    toast.success('Surat berhasil didistribusikan.')
    showDistributeModal.value = false
    const { data } = await api.get(`/outgoing-letters/${route.params.id}`)
    letter.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { actionLoading.value = false }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Loading -->
    <div v-if="loading" class="text-center py-20 text-gray-400">
      <div class="inline-block w-8 h-8 border-3 border-blue-200 border-t-blue-600 rounded-full animate-spin mb-3"></div>
      <p class="text-sm">Memuat data surat...</p>
    </div>

    <template v-else-if="letter">
      <!-- Header with back button and actions -->
      <div class="flex items-center gap-4">
        <button @click="router.back()" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
        </button>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl font-bold text-gray-900 truncate">Detail Surat Keluar</h1>
            <span :class="['px-3 py-1 rounded-full text-xs font-semibold border', statusColor[letter.status]]">
              {{ statusLabel[letter.status] }}
            </span>
          </div>
          <p v-if="letter.letter_number" class="text-sm text-gray-500 font-mono mt-0.5">{{ letter.letter_number }}</p>
        </div>
        <!-- Top action buttons -->
        <div class="flex items-center gap-2 flex-shrink-0">
          <button @click="handlePreviewPdf" :disabled="previewLoading"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            {{ previewLoading ? 'Memuat...' : 'Preview' }}
          </button>
          <button v-if="['DITANDATANGANI','TERKIRIM'].includes(letter.status)" @click="handleDownloadPdf" :disabled="downloadLoading"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            {{ downloadLoading ? 'Mengunduh...' : 'Download PDF' }}
          </button>
        </div>
      </div>

      <!-- Revision Note Alert -->
      <div v-if="letter.revision_note && ['REVISI_PEMERIKSA','REVISI_PENANDATANGAN'].includes(letter.status)"
        class="flex gap-3 bg-red-50 border border-red-200 rounded-xl p-4">
        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
        </svg>
        <div>
          <p class="text-sm font-semibold text-red-800">Surat Dikembalikan untuk Revisi</p>
          <p class="text-sm text-red-600 mt-1">{{ letter.revision_note }}</p>
        </div>
      </div>

      <!-- 2-Column Layout -->
      <div class="grid lg:grid-cols-3 gap-6">
        <!-- Main Content (2/3) -->
        <div class="lg:col-span-2 space-y-5">
          <!-- Info Surat Card -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider">Informasi Surat</h2>
            </div>
            <div class="px-6 py-5">
              <dl class="space-y-3.5">
                <div class="flex gap-4">
                  <dt class="text-sm text-gray-500 w-36 flex-shrink-0">Perihal</dt>
                  <dd class="text-sm font-medium text-gray-800">{{ letter.subject }}</dd>
                </div>
                <div class="flex gap-4">
                  <dt class="text-sm text-gray-500 w-36 flex-shrink-0">Jenis Surat</dt>
                  <dd class="text-sm font-medium text-gray-800">{{ letter.letter_type?.name ?? '-' }}</dd>
                </div>
                <div class="flex gap-4">
                  <dt class="text-sm text-gray-500 w-36 flex-shrink-0">Nomor Surat</dt>
                  <dd class="text-sm font-medium text-gray-800 font-mono">{{ letter.letter_number || 'Belum ditetapkan' }}</dd>
                </div>
                <div class="flex gap-4">
                  <dt class="text-sm text-gray-500 w-36 flex-shrink-0">Tanggal Surat</dt>
                  <dd class="text-sm font-medium text-gray-800">{{ letter.letter_date }}</dd>
                </div>
                <div class="flex gap-4">
                  <dt class="text-sm text-gray-500 w-36 flex-shrink-0">Kepada</dt>
                  <dd class="text-sm font-medium text-gray-800 whitespace-pre-line">{{ letter.recipient }}</dd>
                </div>
                <div class="flex gap-4">
                  <dt class="text-sm text-gray-500 w-36 flex-shrink-0">Dibuat oleh</dt>
                  <dd class="text-sm font-medium text-gray-800">{{ letter.creator?.name }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- Isi Surat Card -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider">Isi Surat</h2>
            </div>
            <div class="px-6 py-5">
              <div class="prose prose-sm max-w-none text-gray-700" v-html="letter.body"></div>
            </div>
          </div>

          <!-- Lampiran -->
          <div v-if="letter.attachment && letter.attachment !== '<p></p>'" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider">Lampiran</h2>
            </div>
            <div class="px-6 py-5">
              <div class="prose prose-sm max-w-none text-gray-700" v-html="letter.attachment"></div>
            </div>
          </div>

          <!-- Penerima Internal -->
          <div v-if="letter.internal_recipients?.length" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider">Penerima Internal</h2>
            </div>
            <div class="px-6 py-5">
              <div class="flex flex-wrap gap-2">
                <span v-for="r in letter.internal_recipients" :key="r.id"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-700 text-xs rounded-full font-medium border border-blue-100">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  {{ r.name }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar (1/3) -->
        <div class="space-y-5">
          <!-- Alur Penandatanganan -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider">Alur Penandatanganan</h2>
            </div>
            <div class="px-5 py-5">
              <div class="space-y-1">
                <template v-for="(step, idx) in approvalSteps" :key="idx">
                  <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                      <div :class="['w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 border-2', step.done ? 'bg-green-50 border-green-500' : 'bg-gray-50 border-gray-300']">
                        <svg v-if="step.done" class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span v-else class="text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                      </div>
                      <div v-if="idx < approvalSteps.length - 1" :class="['w-0.5 flex-1 my-1', step.done ? 'bg-green-300' : 'bg-gray-200']"></div>
                    </div>
                    <div class="pb-4">
                      <p class="text-sm font-medium text-gray-800">{{ step.name || step.label }}</p>
                      <p class="text-xs text-gray-500">{{ step.role }}</p>
                      <p v-if="step.done && step.date" class="text-xs text-green-600 mt-0.5">✓ {{ step.date }}</p>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <!-- Action Card (for reviewer/signer) -->
          <div v-if="canReview || canSign || canDistribute" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider">Tindakan</h2>
            </div>
            <div class="px-5 py-5 space-y-3">
              <!-- Review actions -->
              <template v-if="canReview">
                <button :disabled="actionLoading" @click="handleReview('approve')"
                  class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                  Periksa & Teruskan
                </button>
                <button :disabled="actionLoading" @click="handleReview('revise')"
                  class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors disabled:opacity-50">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                  Kembalikan untuk Revisi
                </button>
              </template>
              <!-- Sign actions -->
              <template v-if="canSign">
                <button :disabled="actionLoading" @click="handleSign('sign')"
                  class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  Tandatangani Surat
                </button>
                <button :disabled="actionLoading" @click="handleSign('revise')"
                  class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors disabled:opacity-50">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                  Kembalikan untuk Revisi
                </button>
              </template>
              <!-- Distribute -->
              <button v-if="canDistribute" @click="showDistributeModal = true"
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Distribusikan Surat
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Revision Modal -->
    <div v-if="showRevisionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showRevisionModal = false">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold text-gray-900 mb-2">Catatan Revisi</h3>
        <p class="text-sm text-gray-500 mb-4">Berikan catatan perbaikan untuk admin:</p>
        <textarea v-model="revisionNote" rows="4"
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
          placeholder="Tulis catatan perbaikan..." />
        <div class="flex justify-end gap-2 mt-4">
          <button class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" @click="showRevisionModal = false">Batal</button>
          <button :disabled="actionLoading" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50" @click="submitRevision">Kirim Revisi</button>
        </div>
      </div>
    </div>

    <!-- Distribute Modal -->
    <div v-if="showDistributeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDistributeModal = false">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md max-h-[85vh] flex flex-col shadow-xl">
        <h3 class="text-lg font-bold text-gray-900 mb-1">Distribusi Surat</h3>
        <p class="text-sm text-gray-500 mb-4">Pilih penerima internal untuk surat ini</p>

        <!-- Search -->
        <input v-model="distributeSearch" type="text" placeholder="Cari nama..."
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-xl text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />

        <!-- Filter buttons -->
        <div class="flex flex-wrap gap-2 mb-3">
          <button type="button" v-for="f in [{val:'all',lbl:'Semua'},{val:'dosen',lbl:'Dosen'},{val:'mahasiswa',lbl:'Mahasiswa'},{val:'struktural',lbl:'Struktural'}]" :key="f.val"
            :class="['px-3 py-1.5 rounded-full text-xs font-medium border transition-colors', distributeFilter === f.val ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50']"
            @click="distributeFilter = f.val">{{ f.lbl }}</button>
        </div>

        <!-- Filter prodi -->
        <div v-if="distributeFilter === 'dosen' || distributeFilter === 'mahasiswa'" class="mb-3">
          <select v-model="distributeProdi" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Semua Program Studi</option>
            <option v-for="p in prodiList" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>

        <!-- Select all -->
        <div class="flex items-center gap-2 mb-2 px-1">
          <input type="checkbox" id="selectAllRecipients" :checked="isAllFilteredSelected" @change="toggleSelectAll" class="rounded border-gray-300" />
          <label for="selectAllRecipients" class="text-xs text-gray-600 font-medium">Pilih Semua ({{ filteredDistributeUsers.length }})</label>
          <span class="text-xs text-blue-600 font-semibold ml-auto">{{ internalRecipientIds.length }} dipilih</span>
        </div>

        <!-- User list -->
        <div class="flex-1 overflow-y-auto border border-gray-200 rounded-xl p-2 space-y-0.5 min-h-[200px] max-h-[300px]">
          <label v-for="u in filteredDistributeUsers" :key="u.id" class="flex items-center gap-2.5 text-sm p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
            <input type="checkbox" :value="u.id" v-model="internalRecipientIds" class="rounded border-gray-300" />
            <div class="flex-1 min-w-0">
              <span class="text-gray-800 truncate block font-medium">{{ u.name }}</span>
              <span v-if="u.role_label" class="text-[10px] text-gray-400">{{ u.role_label }}</span>
            </div>
          </label>
          <div v-if="!filteredDistributeUsers.length" class="text-center text-gray-400 text-xs py-8">Tidak ada user yang cocok.</div>
        </div>

        <div class="flex justify-end gap-2 mt-4">
          <button class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" @click="showDistributeModal = false">Batal</button>
          <button :disabled="actionLoading || !internalRecipientIds.length"
            class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg transition-colors"
            @click="handleDistribute">Kirim ({{ internalRecipientIds.length }})</button>
        </div>
      </div>
    </div>

    <!-- PDF Preview Modal -->
    <div v-if="showPdfModal" class="fixed inset-0 z-50 bg-black/80 flex flex-col">
      <div class="flex items-center justify-between px-4 py-3 bg-gray-900/95 backdrop-blur-sm">
        <h3 class="text-white text-sm font-medium">Preview Surat PDF</h3>
        <button class="text-white/80 hover:text-white text-lg p-1 rounded-lg hover:bg-white/10 transition-colors" @click="closePdfModal">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="flex-1 overflow-hidden">
        <iframe :src="pdfBlobUrl" class="w-full h-full border-0" />
      </div>
      <div class="px-4 py-2 bg-gray-900/95 flex items-center justify-center gap-3 lg:hidden">
        <a :href="pdfBlobUrl" target="_blank" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors">Buka di Tab Baru</a>
        <button class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded-lg transition-colors" @click="closePdfModal">Tutup</button>
      </div>
    </div>
  </div>
</template>
