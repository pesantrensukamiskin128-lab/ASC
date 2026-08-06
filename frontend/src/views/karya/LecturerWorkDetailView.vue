<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { ArrowLeftIcon, PencilIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()

const isAdmin = auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK') || auth.hasPermission('karya.verify')
const isDosen = auth.hasRole('DOSEN') && !isAdmin

const loading = ref(true)
const work    = ref<any>(null)

// Verify modal
const verifyModal  = ref(false)
const verifyForm   = ref({ action: 'verify', revision_note: '' })
const verifying    = ref(false)

// Publish modal
const publishModal   = ref(false)
const publishForm    = ref({ repository_url: '' })
const coverFile      = ref<File | null>(null)
const publishing     = ref(false)

const submitting = ref(false)

const BASE_STORAGE = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace('/api', '') + '/storage/'

const TYPE_LABELS: Record<string, string> = {
  buku: 'Buku', modul_ajar: 'Modul Ajar', hki_paten: 'HKI / Paten',
  penelitian_mandiri: 'Penelitian Mandiri', pengabdian_mandiri: 'Pengabdian Mandiri',
}
const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-600', diajukan: 'bg-blue-100 text-blue-700',
  revisi: 'bg-yellow-100 text-yellow-700', diverifikasi: 'bg-indigo-100 text-indigo-700',
  dipublikasikan: 'bg-green-100 text-green-700',
}
const STATUS_LABELS: Record<string, string> = {
  draft: 'Draft', diajukan: 'Diajukan ke LP2M', revisi: 'Perlu Revisi',
  diverifikasi: 'Diverifikasi', dipublikasikan: 'Dipublikasikan',
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/lecturer-works/${route.params.id}`)
    work.value = data
  } finally { loading.value = false }
})

async function submitWork() {
  if (!confirm('Ajukan karya ini ke LP2M untuk diverifikasi?')) return
  submitting.value = true
  try {
    await api.post(`/lecturer-works/${work.value.id}/submit`)
    toast.success('Karya berhasil diajukan ke LP2M.')
    work.value.status = 'diajukan'
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { submitting.value = false }
}

async function saveVerify() {
  if (verifyForm.value.action === 'revision' && !verifyForm.value.revision_note.trim()) {
    toast.warning('Catatan revisi wajib diisi.')
    return
  }
  verifying.value = true
  try {
    await api.post(`/lecturer-works/${work.value.id}/verify`, {
      action: verifyForm.value.action,
      revision_note: verifyForm.value.revision_note,
    })
    toast.success(verifyForm.value.action === 'verify' ? 'Karya diverifikasi.' : 'Revisi diminta.')
    verifyModal.value = false
    const { data } = await api.get(`/lecturer-works/${route.params.id}`)
    work.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { verifying.value = false }
}

async function savePublish() {
  publishing.value = true
  try {
    const fd = new FormData()
    if (publishForm.value.repository_url) fd.append('repository_url', publishForm.value.repository_url)
    if (coverFile.value) fd.append('cover_image', coverFile.value)
    await api.post(`/lecturer-works/${work.value.id}/publish`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success('Karya berhasil dipublikasikan!')
    publishModal.value = false
    const { data } = await api.get(`/lecturer-works/${route.params.id}`)
    work.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { publishing.value = false }
}

function fileUrl(path: string | null): string {
  return path ? BASE_STORAGE + path : ''
}
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="work" class="space-y-5 max-w-4xl mx-auto">

    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div class="flex-1">
        <div class="flex items-center gap-2 mb-1">
          <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold', STATUS_COLORS[work.status] ?? 'bg-gray-100 text-gray-600']">
            {{ STATUS_LABELS[work.status] ?? work.status }}
          </span>
          <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ TYPE_LABELS[work.type] ?? work.type }}</span>
        </div>
        <h1 class="text-xl font-bold text-gray-900">{{ work.title }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ work.lecturer?.name }} · {{ work.year }}</p>
      </div>
      <!-- Edit button (dosen, draft/revisi) -->
      <button v-if="isDosen && ['draft','revisi'].includes(work.status)" class="flex items-center gap-1.5 px-3 py-2 border border-gray-300 hover:bg-gray-50 rounded-lg text-sm text-gray-700" @click="router.push(`/karya-dosen/${work.id}/edit`)">
        <PencilIcon class="w-4 h-4" /> Edit
      </button>
    </div>

    <!-- Action banner per status -->
    <!-- Draft: tombol ajukan -->
    <div v-if="isDosen && work.status === 'draft'" class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
      <p class="text-sm text-blue-800">Draft siap? Ajukan ke LP2M untuk diverifikasi.</p>
      <button :disabled="submitting" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="submitWork">
        {{ submitting ? 'Mengajukan...' : '📤 Ajukan ke LP2M' }}
      </button>
    </div>

    <!-- Revisi: catatan + tombol ajukan ulang -->
    <div v-if="isDosen && work.status === 'revisi'" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 space-y-3">
      <p class="text-sm font-semibold text-yellow-800">✏️ Revisi Diperlukan</p>
      <p class="text-sm text-yellow-700">{{ work.revision_note }}</p>
      <button :disabled="submitting" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 disabled:bg-yellow-400 text-white text-sm font-medium rounded-lg" @click="submitWork">
        {{ submitting ? 'Mengajukan...' : '📤 Ajukan Ulang ke LP2M' }}
      </button>
    </div>

    <!-- Admin: verifikasi -->
    <div v-if="isAdmin && work.status === 'diajukan'" class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 flex items-center justify-between">
      <p class="text-sm font-semibold text-indigo-800">📋 Menunggu Verifikasi LP2M</p>
      <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg" @click="verifyForm.action='verify'; verifyForm.revision_note=''; verifyModal=true">Verifikasi</button>
    </div>

    <!-- Admin: publikasi -->
    <div v-if="isAdmin && work.status === 'diverifikasi'" class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center justify-between">
      <p class="text-sm font-semibold text-green-800">✅ Terverifikasi — Siap Dipublikasikan</p>
      <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg" @click="publishForm.repository_url=''; coverFile=null; publishModal=true">🌐 Publikasikan</button>
    </div>

    <!-- Published info -->
    <div v-if="work.status === 'dipublikasikan'" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
      <p class="text-sm font-semibold text-emerald-800">🌐 Dipublikasikan ke Repository</p>
      <p class="text-xs text-emerald-600 mt-0.5">{{ work.published_at ? new Date(work.published_at).toLocaleDateString('id-ID', {day:'numeric',month:'long',year:'numeric'}) : '' }}</p>
      <a v-if="work.repository_url" :href="work.repository_url" target="_blank" class="text-sm text-blue-600 underline mt-1 block">Lihat di Repository →</a>
    </div>

    <!-- Detail karya -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div><span class="text-xs text-gray-400">Jenis Karya</span><p class="font-medium text-gray-900">{{ TYPE_LABELS[work.type] ?? work.type }}</p></div>
        <div><span class="text-xs text-gray-400">Tahun</span><p class="font-medium text-gray-900">{{ work.year }}</p></div>
        <div v-if="work.publisher"><span class="text-xs text-gray-400">Penerbit</span><p class="text-gray-800">{{ work.publisher }}</p></div>
        <div v-if="work.isbn_issn"><span class="text-xs text-gray-400">ISBN / ISSN</span><p class="text-gray-800">{{ work.isbn_issn }}</p></div>
        <div v-if="work.hki_number"><span class="text-xs text-gray-400">No. HKI / Paten</span><p class="text-gray-800">{{ work.hki_number }}</p></div>
        <div v-if="work.published_date"><span class="text-xs text-gray-400">Tanggal Terbit</span><p class="text-gray-800">{{ new Date(work.published_date).toLocaleDateString('id-ID') }}</p></div>
      </div>
      <div v-if="work.description" class="pt-3 border-t">
        <p class="text-xs text-gray-400 mb-1">Deskripsi</p>
        <p class="text-sm text-gray-700 whitespace-pre-line">{{ work.description }}</p>
      </div>
      <div v-if="work.keywords" class="pt-3 border-t">
        <p class="text-xs text-gray-400 mb-2">Kata Kunci</p>
        <div class="flex flex-wrap gap-1.5">
          <span v-for="kw in work.keywords.split(',')" :key="kw" class="text-xs px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full">{{ kw.trim() }}</span>
        </div>
      </div>
    </div>

    <!-- Files -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h3 class="text-sm font-semibold text-gray-800">File Dokumen</h3>
      <div v-if="work.main_file_path" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
        <span class="text-2xl">📄</span>
        <div class="flex-1"><p class="text-sm font-medium text-gray-800">File Utama</p></div>
        <a :href="fileUrl(work.main_file_path)" target="_blank" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg">Unduh</a>
      </div>
      <div v-if="work.support_file_path" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
        <span class="text-2xl">📎</span>
        <div class="flex-1"><p class="text-sm font-medium text-gray-800">File Pendukung</p></div>
        <a :href="fileUrl(work.support_file_path)" target="_blank" class="px-3 py-1.5 bg-gray-600 text-white text-xs rounded-lg">Unduh</a>
      </div>
      <p v-if="!work.main_file_path && !work.support_file_path" class="text-sm text-gray-400">Belum ada file.</p>
    </div>

    <!-- Cover (jika dipublikasikan) -->
    <div v-if="work.cover_image_path" class="bg-white rounded-xl border border-gray-200 p-5">
      <h3 class="text-sm font-semibold text-gray-800 mb-3">Cover</h3>
      <img :src="fileUrl(work.cover_image_path)" alt="Cover" class="h-40 w-auto rounded-lg border object-cover" />
    </div>
  </div>

  <!-- Modal Verifikasi -->
  <BaseModal :open="verifyModal" title="Verifikasi Karya Dosen" @close="verifyModal = false">
    <div class="space-y-4">
      <div>
        <label class="text-xs font-medium text-gray-700">Hasil Verifikasi</label>
        <div class="mt-2 space-y-2">
          <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer" :class="verifyForm.action === 'verify' ? 'border-green-400 bg-green-50' : 'border-gray-200'">
            <input type="radio" v-model="verifyForm.action" value="verify" class="text-green-600" />
            <div><p class="text-sm font-medium text-gray-800">✅ Verifikasi</p><p class="text-xs text-gray-500">Karya dinyatakan valid, siap dipublikasikan</p></div>
          </label>
          <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer" :class="verifyForm.action === 'revision' ? 'border-yellow-400 bg-yellow-50' : 'border-gray-200'">
            <input type="radio" v-model="verifyForm.action" value="revision" class="text-yellow-600" />
            <div><p class="text-sm font-medium text-gray-800">✏️ Perlu Revisi</p><p class="text-xs text-gray-500">Dosen perlu memperbaiki karya sebelum diverifikasi</p></div>
          </label>
        </div>
      </div>
      <div v-if="verifyForm.action === 'revision'">
        <label class="text-xs font-medium text-gray-700">Catatan Revisi <span class="text-red-500">*</span></label>
        <textarea v-model="verifyForm.revision_note" rows="3" placeholder="Tuliskan apa yang perlu diperbaiki..." class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="verifyModal = false">Batal</button>
      <button :disabled="verifying" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white text-sm font-medium rounded-lg" @click="saveVerify">
        {{ verifying ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </template>
  </BaseModal>

  <!-- Modal Publikasi -->
  <BaseModal :open="publishModal" title="Publikasikan ke Repository" @close="publishModal = false">
    <div class="space-y-4">
      <div>
        <label class="text-xs font-medium text-gray-700">URL Repository (opsional)</label>
        <input v-model="publishForm.repository_url" type="url" placeholder="https://repository.kampus.ac.id/..." class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
      </div>
      <div>
        <label class="text-xs font-medium text-gray-700">Foto Cover (opsional, JPG/PNG, maks 2MB)</label>
        <input type="file" accept=".jpg,.jpeg,.png,.webp" class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-green-50 file:text-green-700 border border-gray-200 rounded-lg cursor-pointer" @change="(e) => coverFile = (e.target as HTMLInputElement).files?.[0] ?? null" />
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="publishModal = false">Batal</button>
      <button :disabled="publishing" class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-sm font-medium rounded-lg" @click="savePublish">
        {{ publishing ? 'Mempublikasikan...' : '🌐 Publikasikan' }}
      </button>
    </template>
  </BaseModal>
</template>
