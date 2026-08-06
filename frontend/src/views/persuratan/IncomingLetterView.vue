<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, EyeIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useCrud } from '@/composables/useCrud'
import { useToast } from 'vue-toastification'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const auth = useAuthStore()
const toast = useToast()
const canCreate = auth.hasPermission('surat-masuk.create')
const storageBaseUrl = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/api\/?$/, '')

const { items, pagination, loading, fetchAll, remove } = useCrud<any>('/incoming-letters')
const search = ref('')
const modalOpen = ref(false)
const saving = ref(false)
const selectedLetter = ref<any>(null)

// Disposisi
const showDispoModal = ref(false)
const dispoForm = reactive({ instruction: '', notes: '', recipient_ids: [] as number[] })
const allUsers = ref<any[]>([])
const savingDispo = ref(false)

const form = reactive({ letter_number: '', sender: '', subject: '', letter_date: '', received_date: new Date().toISOString().split('T')[0], notes: '' })
const fileInput = ref<File | null>(null)

onMounted(async () => {
  load()
  const { data } = await api.get('/users/list')
  allUsers.value = data
})

function load(page = 1) { fetchAll({ search: search.value, page }) }

function openCreate() {
  Object.assign(form, { letter_number: '', sender: '', subject: '', letter_date: '', received_date: new Date().toISOString().split('T')[0], notes: '' })
  fileInput.value = null
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    const fd = new FormData()
    Object.entries(form).forEach(([k, v]) => { if (v) fd.append(k, v) })
    if (fileInput.value) fd.append('file', fileInput.value)
    await api.post('/incoming-letters', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success('Surat masuk berhasil dicatat.')
    modalOpen.value = false
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

async function openDetail(item: any) {
  const { data } = await api.get(`/incoming-letters/${item.id}`)
  selectedLetter.value = data
}

function openDisposition() {
  Object.assign(dispoForm, { instruction: '', notes: '', recipient_ids: [] })
  showDispoModal.value = true
}

async function handleCreateDisposition() {
  if (!dispoForm.instruction || !dispoForm.recipient_ids.length) { toast.error('Instruksi dan penerima wajib diisi.'); return }
  savingDispo.value = true
  try {
    await api.post(`/incoming-letters/${selectedLetter.value.id}/disposition`, dispoForm)
    toast.success('Disposisi berhasil dibuat.')
    showDispoModal.value = false
    const { data } = await api.get(`/incoming-letters/${selectedLetter.value.id}`)
    selectedLetter.value = data
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { savingDispo.value = false }
}

async function handleDelete(item: any) {
  if (!confirm(`Hapus surat masuk "${item.subject}"?`)) return
  await remove(item.id); load()
}

const statusColor: Record<string, string> = { BARU: 'bg-blue-100 text-blue-700', DIBACA: 'bg-gray-100 text-gray-600', DIDISPOSISI: 'bg-green-100 text-green-700' }
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Surat Masuk</h1>
        <p class="text-sm text-gray-500 mt-0.5">Arsip surat masuk dari pihak eksternal</p>
      </div>
      <button v-if="canCreate" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Surat Masuk
      </button>
    </div>

    <input v-model="search" type="text" placeholder="Cari perihal atau pengirim..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64" @input="load()" />

    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else-if="items.length === 0" class="text-center text-gray-400 py-12">Belum ada surat masuk.</div>
    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wide">
            <th class="px-4 py-3">Pengirim</th>
            <th class="px-4 py-3">Perihal</th>
            <th class="px-4 py-3">Tgl Diterima</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" class="border-t border-gray-100 hover:bg-gray-50 cursor-pointer" @click="openDetail(item)">
            <td class="px-4 py-3 font-medium text-gray-900">{{ item.sender }}</td>
            <td class="px-4 py-3 text-gray-700">{{ item.subject }}</td>
            <td class="px-4 py-3 text-xs text-gray-500">{{ item.received_date }}</td>
            <td class="px-4 py-3 text-center"><span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusColor[item.status]]">{{ item.status }}</span></td>
            <td class="px-4 py-3 text-right" @click.stop>
              <button v-if="canCreate" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(item)"><TrashIcon class="w-4 h-4" /></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Detail Panel -->
    <div v-if="selectedLetter" class="fixed inset-0 z-40 flex items-start justify-end bg-black/30" @click.self="selectedLetter = null">
      <div class="w-full max-w-lg h-full bg-white shadow-xl overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold text-gray-900">Detail Surat Masuk</h2>
          <button class="text-gray-400 hover:text-gray-600" @click="selectedLetter = null">✕</button>
        </div>
        <div class="space-y-3 text-sm">
          <div><span class="text-gray-400">Pengirim:</span> <strong>{{ selectedLetter.sender }}</strong></div>
          <div><span class="text-gray-400">Nomor Surat:</span> {{ selectedLetter.letter_number || '-' }}</div>
          <div><span class="text-gray-400">Perihal:</span> {{ selectedLetter.subject }}</div>
          <div><span class="text-gray-400">Tanggal Surat:</span> {{ selectedLetter.letter_date || '-' }}</div>
          <div><span class="text-gray-400">Tanggal Diterima:</span> {{ selectedLetter.received_date }}</div>
          <div v-if="selectedLetter.notes"><span class="text-gray-400">Catatan:</span> {{ selectedLetter.notes }}</div>
          <div v-if="selectedLetter.file_path">
            <a :href="`${storageBaseUrl}/storage/${selectedLetter.file_path}`" target="_blank" class="text-blue-600 hover:underline text-sm">📎 Lihat File Surat</a>
          </div>
        </div>

        <!-- Disposisi -->
        <div class="mt-6 border-t pt-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">Disposisi</h3>
            <button v-if="auth.hasPermission('disposisi.create')" class="text-xs text-blue-600 hover:underline" @click="openDisposition">+ Buat Disposisi</button>
          </div>
          <div v-if="!selectedLetter.dispositions?.length" class="text-xs text-gray-400">Belum ada disposisi.</div>
          <div v-else class="space-y-3">
            <div v-for="d in selectedLetter.dispositions" :key="d.id" class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-500">Dari: <strong>{{ d.creator?.name }}</strong></p>
              <p class="text-sm text-gray-800 mt-1">{{ d.instruction }}</p>
              <div class="mt-2 flex flex-wrap gap-1">
                <span v-for="r in d.recipients" :key="r.id" class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] rounded-full">{{ r.name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Tambah Surat Masuk -->
  <BaseModal :open="modalOpen" title="Tambah Surat Masuk" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Nomor Surat</label><input v-model="form.letter_number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Pengirim <span class="text-red-500">*</span></label><input v-model="form.sender" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Perihal <span class="text-red-500">*</span></label><input v-model="form.subject" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Surat</label><input v-model="form.letter_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Diterima <span class="text-red-500">*</span></label><input v-model="form.received_date" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label><textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">File Surat</label><input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" @change="fileInput = ($event.target as any)?.files?.[0]" class="text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>

  <!-- Modal Disposisi -->
  <BaseModal :open="showDispoModal" title="Buat Disposisi" @close="showDispoModal = false">
    <div class="space-y-4">
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Instruksi <span class="text-red-500">*</span></label><textarea v-model="dispoForm.instruction" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Instruksi disposisi..." /></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label><textarea v-model="dispoForm.notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Penerima Disposisi <span class="text-red-500">*</span></label>
        <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3 space-y-1">
          <label v-for="u in allUsers" :key="u.id" class="flex items-center gap-2 text-sm"><input type="checkbox" :value="u.id" v-model="dispoForm.recipient_ids" class="rounded" />{{ u.name }}</label>
        </div>
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="showDispoModal = false">Batal</button>
      <button :disabled="savingDispo" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleCreateDisposition">{{ savingDispo ? 'Menyimpan...' : 'Buat Disposisi' }}</button>
    </template>
  </BaseModal>
</template>
