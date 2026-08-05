<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { ArrowLeftIcon, CheckCircleIcon, XCircleIcon, DocumentArrowUpIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()
const loading = ref(true)
const data = ref<any>(null)
const isAdmin = auth.user?.roles?.includes('SUPER_ADMIN') || auth.user?.roles?.includes('ADMIN_AKADEMIK')
const isDosen = auth.user?.roles?.includes('DOSEN')

// Upload
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)

// Approve
const approving = ref(false)
const approveNotes = ref('')

const statusColor: Record<string, string> = {
  DIAJUKAN: 'bg-blue-100 text-blue-700', DOSEN_WALI_APPROVED: 'bg-cyan-100 text-cyan-700',
  KAPRODI_APPROVED: 'bg-indigo-100 text-indigo-700', APPROVED: 'bg-green-100 text-green-700',
  AKTIF: 'bg-purple-100 text-purple-700', SELESAI: 'bg-green-100 text-green-700',
  DIBATALKAN: 'bg-gray-100 text-gray-500', DOSEN_WALI_REJECTED: 'bg-red-100 text-red-600',
  KAPRODI_REJECTED: 'bg-red-100 text-red-600', REJECTED: 'bg-red-100 text-red-600',
}

onMounted(async () => {
  try {
    const { data: res } = await api.get(`/academic-leaves/${route.params.id}`)
    data.value = res
  } finally { loading.value = false }
})

const documentUrl = computed(() => {
  if (!data.value?.document_path) return null
  const base = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/api\/?$/, '')
  return `${base}/storage/${data.value.document_path}`
})

const nextApprovalRole = computed(() => {
  if (!data.value?.approvals) return null
  const pending = data.value.approvals.find((a: any) => a.status === 'PENDING')
  if (!pending) return null
  // Check if previous are all approved
  const prevAll = data.value.approvals.filter((a: any) => a.order < pending.order)
  if (prevAll.some((a: any) => a.status !== 'APPROVED')) return null
  return pending.role
})

const canApprove = computed(() => {
  if (!nextApprovalRole.value) return false
  if (isAdmin && nextApprovalRole.value === 'ADMIN_AKADEMIK') return true
  if (isDosen && ['DOSEN_WALI', 'KAPRODI'].includes(nextApprovalRole.value)) return true
  if (auth.user?.roles?.includes('SUPER_ADMIN')) return true
  return false
})

async function uploadDocument(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('document', file)
    await api.post(`/academic-leaves/${data.value.id}/document`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success('Dokumen berhasil diupload.')
    const { data: res } = await api.get(`/academic-leaves/${route.params.id}`)
    data.value = res
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal upload.') }
  finally { uploading.value = false; if (fileInput.value) fileInput.value.value = '' }
}

async function handleApprove(action: 'approve' | 'reject') {
  if (action === 'reject' && !approveNotes.value.trim()) {
    toast.error('Masukkan alasan penolakan.'); return
  }
  approving.value = true
  try {
    await api.post(`/academic-leaves/${data.value.id}/approve`, {
      role: nextApprovalRole.value, action, notes: approveNotes.value || null,
    })
    toast.success(action === 'approve' ? 'Berhasil disetujui.' : 'Pengajuan ditolak.')
    const { data: res } = await api.get(`/academic-leaves/${route.params.id}`)
    data.value = res
    approveNotes.value = ''
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { approving.value = false }
}

async function handleActivate() {
  if (!confirm('Aktifkan kembali mahasiswa ini? Status akan berubah dari CUTI ke AKTIF.')) return
  try {
    await api.post(`/academic-leaves/${data.value.id}/activate`)
    toast.success('Mahasiswa berhasil diaktifkan kembali.')
    const { data: res } = await api.get(`/academic-leaves/${route.params.id}`)
    data.value = res
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-' }
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="data" class="space-y-6 max-w-3xl mx-auto">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div class="flex-1">
        <h1 class="text-xl font-bold text-gray-900">Detail Cuti Akademik</h1>
        <p class="text-sm text-gray-500">{{ data.student?.name }} · {{ data.student?.nim }}</p>
      </div>
      <span :class="['px-3 py-1 rounded-full text-sm font-medium', statusColor[data.status] ?? 'bg-gray-100']">
        {{ data.status.replace(/_/g, ' ') }}
      </span>
    </div>

    <!-- Info -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
      <div><span class="text-xs text-gray-400">Semester</span><p class="text-gray-800 font-medium">{{ data.semester?.name ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">Jenis</span><p class="text-gray-800 font-medium">{{ data.type }}</p></div>
      <div><span class="text-xs text-gray-400">Durasi</span><p class="text-gray-800 font-medium">{{ data.leave_semester_count }} semester</p></div>
      <div><span class="text-xs text-gray-400">Diajukan</span><p class="text-gray-800">{{ formatDate(data.submitted_at) }}</p></div>
    </div>

    <!-- Alasan -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <p class="text-xs text-gray-400 mb-1">Alasan Cuti</p>
      <p class="text-sm text-gray-800 whitespace-pre-line">{{ data.reason }}</p>
    </div>

    <!-- Dokumen -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-3">Dokumen Pendukung</h2>
      <div v-if="documentUrl" class="flex items-center gap-3">
        <DocumentArrowUpIcon class="w-5 h-5 text-green-600" />
        <a :href="documentUrl" target="_blank" class="text-sm text-blue-600 hover:underline">Lihat Dokumen</a>
      </div>
      <p v-else class="text-sm text-gray-400 italic">Belum ada dokumen.</p>
      <div v-if="['DIAJUKAN', 'DRAFT'].includes(data.status)" class="mt-3">
        <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="uploadDocument" />
        <button :disabled="uploading" class="text-sm text-blue-600 hover:text-blue-700 font-medium" @click="fileInput?.click()">
          {{ uploading ? 'Mengupload...' : (data.document_path ? 'Ganti Dokumen' : 'Upload Dokumen') }}
        </button>
      </div>
    </div>

    <!-- Approval Timeline -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-4">Alur Persetujuan</h2>
      <div class="space-y-4">
        <div v-for="a in data.approvals" :key="a.id" class="flex items-start gap-3">
          <div :class="['w-8 h-8 rounded-full flex items-center justify-center shrink-0',
            a.status === 'APPROVED' ? 'bg-green-100 text-green-600' :
            a.status === 'REJECTED' ? 'bg-red-100 text-red-600' :
            'bg-gray-100 text-gray-400']">
            <CheckCircleIcon v-if="a.status === 'APPROVED'" class="w-4 h-4" />
            <XCircleIcon v-else-if="a.status === 'REJECTED'" class="w-4 h-4" />
            <span v-else class="text-xs font-bold">{{ a.order }}</span>
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <p class="text-sm font-medium text-gray-800">{{ a.role.replace(/_/g, ' ') }}</p>
              <span :class="['text-xs px-2 py-0.5 rounded-full font-medium',
                a.status === 'APPROVED' ? 'bg-green-100 text-green-700' :
                a.status === 'REJECTED' ? 'bg-red-100 text-red-600' :
                'bg-gray-100 text-gray-500']">
                {{ a.status }}
              </span>
            </div>
            <p v-if="a.status !== 'PENDING'" class="text-xs text-gray-500 mt-0.5">
              {{ a.approver?.name ?? '-' }} · {{ formatDate(a.approved_at) }}
            </p>
            <p v-if="a.notes" class="text-xs text-gray-600 mt-1 italic">"{{ a.notes }}"</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Approve/Reject (dosen/admin) -->
    <div v-if="canApprove" class="bg-white rounded-xl border border-blue-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-3">Persetujuan ({{ nextApprovalRole?.replace(/_/g, ' ') }})</h2>
      <div class="space-y-3">
        <textarea v-model="approveNotes" rows="2" placeholder="Catatan (opsional, wajib jika ditolak)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        <div class="flex items-center gap-2">
          <button :disabled="approving" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg flex items-center gap-1.5" @click="handleApprove('approve')">
            <CheckCircleIcon class="w-4 h-4" /> Setujui
          </button>
          <button :disabled="approving" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg flex items-center gap-1.5" @click="handleApprove('reject')">
            <XCircleIcon class="w-4 h-4" /> Tolak
          </button>
        </div>
      </div>
    </div>

    <!-- Aktivasi kembali (admin only, saat AKTIF) -->
    <div v-if="isAdmin && data.status === 'AKTIF'" class="bg-white rounded-xl border border-green-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-2">Aktivasi Kembali</h2>
      <p class="text-sm text-gray-500 mb-3">Aktifkan mahasiswa yang telah selesai masa cutinya.</p>
      <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg flex items-center gap-1.5" @click="handleActivate">
        <ArrowPathIcon class="w-4 h-4" /> Aktifkan Kembali
      </button>
    </div>
  </div>
</template>
