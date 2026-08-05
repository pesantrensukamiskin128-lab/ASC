<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { PencilIcon, BuildingOffice2Icon, ArrowUpTrayIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'
import { cleanPayload, extractErrorMessage } from '@/composables/useCrud'

interface Institution {
  id: number; code: string; name: string; short_name: string
  legal_entity_name: string; address: string; phone: string
  email: string; website: string; accreditation: string; logo_path: string
  letterhead_path?: string
}

const toast = useToast()
const institution  = ref<Institution | null>(null)
const modalOpen    = ref(false)
const saving       = ref(false)
const uploading    = ref(false)

// Logo upload state
const fileInput    = ref<HTMLInputElement | null>(null)
const previewUrl   = ref<string | null>(null)
const selectedFile = ref<File | null>(null)

// Letterhead upload state
const letterheadInput = ref<HTMLInputElement | null>(null)
const letterheadPreview = ref<string | null>(null)
const selectedLetterhead = ref<File | null>(null)
const uploadingLetterhead = ref(false)

const form = reactive({
  code: '', name: '', short_name: '', legal_entity_name: '',
  address: '', phone: '', email: '', website: '', accreditation: '',
})

// URL logo — gunakan APP_URL backend
const logoUrl = computed(() => {
  if (!institution.value?.logo_path) return null
  const base = import.meta.env.VITE_API_URL?.replace('/api', '') ?? 'http://localhost:8000'
  return `${base}/storage/${institution.value.logo_path}`
})

const letterheadUrl = computed(() => {
  if (!institution.value?.letterhead_path) return null
  const base = import.meta.env.VITE_API_URL?.replace('/api', '') ?? 'http://localhost:8000'
  return `${base}/storage/${institution.value.letterhead_path}`
})

onMounted(load)

async function load() {
  try {
    const { data } = await api.get('/institutions')
    institution.value = data[0] ?? null
  } catch { toast.error('Gagal memuat data institusi.') }
}

function openEdit() {
  if (institution.value) Object.assign(form, institution.value)
  previewUrl.value   = null
  selectedFile.value = null
  modalOpen.value    = true
}

// Pilih file dari disk
function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return

  // Validasi tipe dan ukuran (maks 2MB)
  if (!['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'].includes(file.type)) {
    toast.error('Format file tidak didukung. Gunakan JPG, PNG, SVG, atau WebP.')
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error('Ukuran file maksimal 2MB.')
    return
  }

  selectedFile.value = file
  previewUrl.value   = URL.createObjectURL(file)
}

function clearSelectedFile() {
  selectedFile.value = null
  previewUrl.value   = null
  if (fileInput.value) fileInput.value.value = ''
}

// Upload logo setelah simpan data (atau langsung jika institusi sudah ada)
async function uploadLogo(institutionId: number) {
  if (!selectedFile.value) return
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('logo', selectedFile.value)
    const { data } = await api.post(`/institutions/${institutionId}/logo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    toast.success(data.message)
    // Refresh data institusi
    await load()
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  } finally {
    uploading.value    = false
    selectedFile.value = null
    previewUrl.value   = null
  }
}

async function handleSave() {
  saving.value = true
  try {
    let id: number
    if (institution.value) {
      const { data } = await api.put(`/institutions/${institution.value.id}`, cleanPayload(form))
      institution.value = data.data
      toast.success(data.message)
      id = institution.value.id
    } else {
      const { data } = await api.post('/institutions', cleanPayload(form))
      institution.value = data.data
      toast.success(data.message)
      id = data.data.id
    }

    // Upload logo jika ada file yang dipilih
    if (selectedFile.value) {
      await uploadLogo(id)
    }

    modalOpen.value = false
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}

// Hapus logo
async function handleDeleteLogo() {
  if (!institution.value || !confirm('Hapus logo institusi?')) return
  try {
    const { data } = await api.put(`/institutions/${institution.value.id}`, { logo_path: null })
    institution.value = data.data
    toast.success('Logo berhasil dihapus.')
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  }
}

function triggerFileInput() {
  fileInput.value?.click()
}

// Letterhead
function onLetterheadChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    toast.error('Format file kop surat: JPG, PNG, atau WebP.')
    return
  }
  if (file.size > 4 * 1024 * 1024) {
    toast.error('Ukuran file kop surat maksimal 4MB.')
    return
  }
  selectedLetterhead.value = file
  letterheadPreview.value = URL.createObjectURL(file)
}

async function uploadLetterhead() {
  if (!selectedLetterhead.value || !institution.value) return
  uploadingLetterhead.value = true
  try {
    const fd = new FormData()
    fd.append('letterhead', selectedLetterhead.value)
    const { data } = await api.post(`/institutions/${institution.value.id}/letterhead`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success(data.message)
    await load()
  } catch (err: any) { toast.error(extractErrorMessage(err)) }
  finally {
    uploadingLetterhead.value = false
    selectedLetterhead.value = null
    letterheadPreview.value = null
    if (letterheadInput.value) letterheadInput.value.value = ''
  }
}

async function deleteLetterhead() {
  if (!institution.value || !confirm('Hapus gambar kop surat?')) return
  try {
    await api.put(`/institutions/${institution.value.id}`, { letterhead_path: null })
    toast.success('Kop surat berhasil dihapus.')
    await load()
  } catch (err: any) { toast.error(extractErrorMessage(err)) }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Data Institusi</h1>
        <p class="text-sm text-gray-500 mt-0.5">Profil dan informasi perguruan tinggi</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
        @click="openEdit"
      >
        <PencilIcon class="w-4 h-4" />
        {{ institution ? 'Edit Institusi' : 'Tambah Institusi' }}
      </button>
    </div>

    <!-- Profile Card -->
    <div v-if="institution" class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="flex items-start gap-6">
        <!-- Logo -->
        <div class="shrink-0">
          <div class="w-24 h-24 rounded-xl border-2 border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden relative group">
            <img v-if="logoUrl" :src="logoUrl" :alt="institution.name" class="w-full h-full object-contain p-1" />
            <BuildingOffice2Icon v-else class="w-12 h-12 text-gray-300" />

            <!-- Hover overlay hapus logo -->
            <button
              v-if="logoUrl"
              class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-xl"
              title="Hapus logo"
              @click="handleDeleteLogo"
            >
              <TrashIcon class="w-6 h-6 text-white" />
            </button>
          </div>
          <p class="text-xs text-gray-400 text-center mt-1.5">Logo</p>
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <h2 class="text-xl font-bold text-gray-900">{{ institution.name }}</h2>
          <p class="text-sm text-gray-500 mt-0.5">{{ institution.legal_entity_name }}</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <span class="inline-flex px-2.5 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">
              {{ institution.code }}
            </span>
            <span v-if="institution.accreditation" class="inline-flex px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">
              Akreditasi {{ institution.accreditation }}
            </span>
          </div>

          <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="item in [
              { label: 'Nama Singkat', value: institution.short_name },
              { label: 'Alamat', value: institution.address },
              { label: 'Telepon', value: institution.phone },
              { label: 'Email', value: institution.email },
              { label: 'Website', value: institution.website },
            ]" :key="item.label">
              <p class="text-xs text-gray-400 font-medium">{{ item.label }}</p>
              <p class="text-sm text-gray-700 mt-0.5">{{ item.value || '-' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-400">
      <BuildingOffice2Icon class="w-12 h-12 mx-auto mb-3 text-gray-300" />
      <p class="text-sm">Belum ada data institusi. Klik tombol untuk menambahkan.</p>
    </div>

    <!-- Section Kop Surat -->
    <div v-if="institution" class="bg-white rounded-xl border border-gray-200 p-6">
      <h3 class="text-sm font-semibold text-gray-800 mb-3">Kop Surat (Letterhead)</h3>
      <p class="text-xs text-gray-500 mb-4">Gambar kop surat akan digunakan di semua dokumen PDF yang dihasilkan sistem (Kalender Akademik, Kartu Ujian, Surat, dll)</p>

      <!-- Preview kop surat -->
      <div v-if="letterheadUrl" class="mb-4">
        <div class="border border-gray-200 rounded-lg overflow-hidden bg-gray-50 p-2">
          <img :src="letterheadUrl" alt="Kop Surat" class="w-full max-h-32 object-contain" />
        </div>
        <div class="flex items-center gap-2 mt-2">
          <button class="px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 border border-red-200 rounded-lg" @click="deleteLetterhead">Hapus Kop Surat</button>
        </div>
      </div>

      <!-- Upload area -->
      <div class="flex items-center gap-4">
        <div v-if="letterheadPreview" class="border border-blue-200 rounded-lg overflow-hidden bg-blue-50 p-2 flex-1">
          <img :src="letterheadPreview" alt="Preview" class="w-full max-h-24 object-contain" />
        </div>
        <div>
          <input ref="letterheadInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onLetterheadChange" />
          <button class="flex items-center gap-2 px-3 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg" @click="letterheadInput?.click()">
            <ArrowUpTrayIcon class="w-4 h-4" />
            {{ letterheadUrl ? 'Ganti Kop Surat' : 'Upload Kop Surat' }}
          </button>
          <p class="text-xs text-gray-400 mt-1">JPG / PNG / WebP · Maks 4MB · Lebar disarankan 800px+</p>
        </div>
        <button v-if="selectedLetterhead" :disabled="uploadingLetterhead" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="uploadLetterhead">
          {{ uploadingLetterhead ? 'Mengupload...' : 'Simpan Kop Surat' }}
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Edit -->
  <BaseModal :open="modalOpen" title="Data Institusi" size="xl" @close="modalOpen = false">
    <form class="space-y-5" @submit.prevent="handleSave">

      <!-- Upload Logo Section -->
      <div class="border border-dashed border-gray-300 rounded-xl p-4 bg-gray-50">
        <p class="text-sm font-medium text-gray-700 mb-3">Logo Institusi</p>
        <div class="flex items-center gap-4">
          <!-- Preview -->
          <div class="w-20 h-20 rounded-xl border-2 border-gray-200 bg-white flex items-center justify-center overflow-hidden shrink-0">
            <img
              v-if="previewUrl"
              :src="previewUrl"
              alt="Preview"
              class="w-full h-full object-contain p-1"
            />
            <img
              v-else-if="logoUrl"
              :src="logoUrl"
              alt="Logo"
              class="w-full h-full object-contain p-1"
            />
            <BuildingOffice2Icon v-else class="w-10 h-10 text-gray-300" />
          </div>

          <!-- Upload controls -->
          <div class="flex-1">
            <div class="flex gap-2">
              <button
                type="button"
                class="flex items-center gap-2 px-3 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition-colors"
                @click="triggerFileInput"
              >
                <ArrowUpTrayIcon class="w-4 h-4" />
                {{ selectedFile ? 'Ganti File' : 'Pilih Logo' }}
              </button>
              <button
                v-if="selectedFile"
                type="button"
                class="px-3 py-2 text-sm text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                @click="clearSelectedFile"
              >
                Batal
              </button>
            </div>
            <p class="text-xs text-gray-400 mt-1.5">JPG, PNG, SVG, WebP · Maks 2MB</p>
            <p v-if="selectedFile" class="text-xs text-blue-600 mt-1 font-medium">
              ✓ {{ selectedFile.name }} ({{ (selectedFile.size / 1024).toFixed(0) }} KB)
            </p>
          </div>
        </div>

        <!-- Hidden file input -->
        <input
          ref="fileInput"
          type="file"
          accept="image/jpeg,image/png,image/svg+xml,image/webp"
          class="hidden"
          @change="onFileChange"
        />
      </div>

      <!-- Form Fields -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.code" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama Singkat</label>
          <input v-model="form.short_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Badan Hukum / Yayasan</label>
        <input v-model="form.legal_entity_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
        <textarea v-model="form.address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Telepon</label>
          <input v-model="form.phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Akreditasi</label>
          <select v-model="form.accreditation" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih --</option>
            <option v-for="a in ['Unggul','A','Baik Sekali','B','Baik','C']" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
          <input v-model="form.website" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
    </form>

    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" @click="modalOpen = false">
        Batal
      </button>
      <button
        :disabled="saving || uploading"
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg transition-colors"
        @click="handleSave"
      >
        <span v-if="saving || uploading" class="flex items-center gap-2">
          <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
          {{ uploading ? 'Mengupload logo...' : 'Menyimpan...' }}
        </span>
        <span v-else>Simpan</span>
      </button>
    </template>
  </BaseModal>
</template>
