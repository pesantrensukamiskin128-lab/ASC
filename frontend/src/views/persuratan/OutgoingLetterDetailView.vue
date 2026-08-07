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

const apiBaseUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

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

onMounted(async () => {
  try {
    const { data } = await api.get(`/outgoing-letters/${route.params.id}`)
    letter.value = data
    if (data.status === 'DITANDATANGANI') {
      const { data: users } = await api.get('/user-list')
      allUsers.value = users
    }
  } catch { toast.error('Gagal memuat data surat.') }
  finally { loading.value = false }
})

const canReview = computed(() => letter.value?.status === 'MENUNGGU_PEMERIKSA' && letter.value?.reviewer_id === auth.user?.id)
const canSign = computed(() => letter.value?.status === 'MENUNGGU_PENANDATANGAN' && letter.value?.signer_id === auth.user?.id)
const canDistribute = computed(() => letter.value?.status === 'DITANDATANGANI' && auth.hasPermission('surat-keluar.send'))

const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600', MENUNGGU_PEMERIKSA: 'bg-yellow-100 text-yellow-700',
  MENUNGGU_PENANDATANGAN: 'bg-blue-100 text-blue-700', REVISI_PEMERIKSA: 'bg-red-100 text-red-600',
  REVISI_PENANDATANGAN: 'bg-red-100 text-red-600', DITANDATANGANI: 'bg-green-100 text-green-700',
  TERKIRIM: 'bg-emerald-100 text-emerald-700',
}
const statusLabel: Record<string, string> = {
  DRAFT: 'Draft', MENUNGGU_PEMERIKSA: 'Menunggu Pemeriksa', MENUNGGU_PENANDATANGAN: 'Menunggu Tanda Tangan',
  REVISI_PEMERIKSA: 'Revisi dari Pemeriksa', REVISI_PENANDATANGAN: 'Revisi dari Penandatangan',
  DITANDATANGANI: 'Ditandatangani', TERKIRIM: 'Terkirim',
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
  <div class="space-y-5 max-w-4xl">
    <div v-if="loading" class="text-center py-12 text-gray-400">Memuat...</div>
    <template v-else-if="letter">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-xl font-bold text-gray-900">{{ letter.subject }}</h1>
          <p class="text-sm text-gray-500 mt-0.5">{{ letter.letter_type?.name }} · {{ letter.letter_number || 'Belum ada nomor' }}</p>
        </div>
        <span :class="['px-3 py-1 rounded-full text-xs font-semibold', statusColor[letter.status]]">
          {{ statusLabel[letter.status] }}
        </span>
      </div>

      <!-- Revisi Note -->
      <div v-if="letter.revision_note && ['REVISI_PEMERIKSA','REVISI_PENANDATANGAN'].includes(letter.status)" class="bg-red-50 border border-red-200 rounded-xl p-4">
        <p class="text-sm font-semibold text-red-700">Catatan Revisi:</p>
        <p class="text-sm text-red-600 mt-1">{{ letter.revision_note }}</p>
      </div>

      <!-- Info Card -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div><p class="text-xs text-gray-400">Kepada</p><p class="text-gray-800 whitespace-pre-line">{{ letter.recipient }}</p></div>
          <div><p class="text-xs text-gray-400">Tanggal Surat</p><p class="text-gray-800">{{ letter.letter_date }}</p></div>
          <div><p class="text-xs text-gray-400">Penandatangan</p><p class="text-gray-800">{{ letter.signer?.name ?? '-' }}</p></div>
          <div><p class="text-xs text-gray-400">Pemeriksa</p><p class="text-gray-800">{{ letter.reviewer?.name ?? 'Tidak ada (langsung TTD)' }}</p></div>
          <div><p class="text-xs text-gray-400">Dibuat oleh</p><p class="text-gray-800">{{ letter.creator?.name }}</p></div>
          <div v-if="letter.signed_at"><p class="text-xs text-gray-400">Ditandatangani</p><p class="text-gray-800">{{ letter.signed_at }}</p></div>
        </div>
      </div>

      <!-- Body -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Isi Surat</h2>
        <div class="prose prose-sm max-w-none" v-html="letter.body"></div>
      </div>

      <!-- Penerima Internal -->
      <div v-if="letter.internal_recipients?.length" class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Penerima Internal</h2>
        <div class="flex flex-wrap gap-2">
          <span v-for="r in letter.internal_recipients" :key="r.id" class="px-2.5 py-1 bg-blue-50 text-blue-700 text-xs rounded-full font-medium">
            {{ r.name }}
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap gap-3">
        <a :href="`${apiBaseUrl}/outgoing-letters/${letter.id}/preview-pdf`"
          target="_blank"
          class="px-4 py-2.5 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg inline-flex items-center gap-2">
          👁 Preview PDF
        </a>
        <a v-if="['DITANDATANGANI','TERKIRIM'].includes(letter.status)"
          :href="`${apiBaseUrl}/outgoing-letters/${letter.id}/pdf`"
          target="_blank"
          class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg inline-flex items-center gap-2">
          📄 Download PDF
        </a>
        <button v-if="canReview" :disabled="actionLoading" class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg" @click="handleReview('approve')">
          ✓ Periksa & Teruskan
        </button>
        <button v-if="canReview" :disabled="actionLoading" class="px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-medium rounded-lg" @click="handleReview('revise')">
          ↩ Revisi
        </button>
        <button v-if="canSign" :disabled="actionLoading" class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg" @click="handleSign('sign')">
          ✍ Tandatangani
        </button>
        <button v-if="canSign" :disabled="actionLoading" class="px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-medium rounded-lg" @click="handleSign('revise')">
          ↩ Revisi
        </button>
        <button v-if="canDistribute" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="showDistributeModal = true">
          📨 Distribusikan Surat
        </button>
      </div>

      <!-- Revision Modal -->
      <div v-if="showRevisionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showRevisionModal = false">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
          <h3 class="text-lg font-bold text-gray-900 mb-4">Catatan Revisi</h3>
          <textarea v-model="revisionNote" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Tuliskan catatan perbaikan..." />
          <div class="flex justify-end gap-2 mt-4">
            <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="showRevisionModal = false">Batal</button>
            <button :disabled="actionLoading" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg" @click="submitRevision">Kirim Revisi</button>
          </div>
        </div>
      </div>

      <!-- Distribute Modal -->
      <div v-if="showDistributeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showDistributeModal = false">
        <div class="bg-white rounded-xl p-6 w-full max-w-md max-h-[80vh] overflow-y-auto">
          <h3 class="text-lg font-bold text-gray-900 mb-4">Distribusi Surat</h3>
          <p class="text-sm text-gray-500 mb-3">Pilih penerima internal:</p>
          <div class="space-y-2 max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3">
            <label v-for="u in allUsers" :key="u.id" class="flex items-center gap-2 text-sm">
              <input type="checkbox" :value="u.id" v-model="internalRecipientIds" class="rounded" />
              {{ u.name }}
            </label>
          </div>
          <div class="flex justify-end gap-2 mt-4">
            <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="showDistributeModal = false">Batal</button>
            <button :disabled="actionLoading" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="handleDistribute">Kirim</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
