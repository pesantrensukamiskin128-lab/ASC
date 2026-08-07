<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const auth = useAuthStore()
const toast = useToast()
const isAdmin = auth.hasPermission('surat-keluar.create')

const items = ref<any[]>([])
const loading = ref(true)
const modalOpen = ref(false)
const saving = ref(false)
const editingId = ref<number | null>(null)
const letterTypes = ref<any[]>([])

const form = reactive({ letter_type_id: '', purpose: '', description: '' })

const statusColor: Record<string, string> = {
  DIAJUKAN: 'bg-blue-100 text-blue-700',
  DIPROSES: 'bg-yellow-100 text-yellow-700',
  SELESAI: 'bg-green-100 text-green-700',
  DITOLAK: 'bg-red-100 text-red-700',
}

onMounted(async () => {
  try {
    const [res, typesRes] = await Promise.all([
      api.get('/letter-requests'),
      api.get('/outgoing-letters/letter-types'),
    ])
    items.value = res.data.data ?? res.data
    letterTypes.value = typesRes.data
  } catch {} finally { loading.value = false }
})

function openCreate() {
  editingId.value = null
  Object.assign(form, { letter_type_id: '', purpose: '', description: '' })
  modalOpen.value = true
}

function openEdit(item: any) {
  editingId.value = item.id
  Object.assign(form, { letter_type_id: item.letter_type_id ?? '', purpose: item.purpose, description: item.description ?? '' })
  modalOpen.value = true
}

async function handleSubmit() {
  if (!form.purpose.trim()) { toast.error('Keperluan wajib diisi.'); return }
  saving.value = true
  try {
    const payload = { ...form }
    if (!payload.letter_type_id) delete (payload as any).letter_type_id

    if (editingId.value) {
      const { data } = await api.put(`/letter-requests/${editingId.value}`, payload)
      toast.success(data.message)
    } else {
      const { data } = await api.post('/letter-requests', payload)
      toast.success(data.message)
    }
    modalOpen.value = false
    await reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

async function handleDelete(item: any) {
  if (!confirm(`Hapus pengajuan "${item.purpose}"?`)) return
  try {
    await api.delete(`/letter-requests/${item.id}`)
    toast.success('Pengajuan berhasil dihapus.')
    await reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal menghapus.') }
}

async function reload() {
  const res = await api.get('/letter-requests')
  items.value = res.data.data ?? res.data
}

async function handleProcess(id: number, status: string) {
  const note = status === 'DITOLAK' ? prompt('Alasan penolakan:') : null
  if (status === 'DITOLAK' && !note) return
  try {
    await api.post(`/letter-requests/${id}/process`, { status, admin_note: note })
    toast.success('Pengajuan berhasil diproses.')
    const { data } = await api.get('/letter-requests')
    items.value = data.data ?? data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">{{ isAdmin ? 'Pengajuan Surat Masuk' : 'Pengajuan Pembuatan Surat' }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ isAdmin ? 'Pengajuan dari dosen dan mahasiswa' : 'Ajukan pembuatan surat ke bagian administrasi' }}</p>
      </div>
      <button v-if="!isAdmin" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Ajukan Surat
      </button>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else-if="!items.length" class="text-center text-gray-400 py-12">Belum ada pengajuan.</div>
    <div v-else class="space-y-3">
      <div v-for="item in items" :key="item.id" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span :class="['px-2 py-0.5 text-[10px] font-semibold rounded-full', statusColor[item.status]]">{{ item.status }}</span>
              <span v-if="item.letter_type" class="text-xs text-gray-400">{{ item.letter_type.name }}</span>
            </div>
            <h3 class="text-sm font-semibold text-gray-900">{{ item.purpose }}</h3>
            <p v-if="item.description" class="text-xs text-gray-500 mt-1">{{ item.description }}</p>
            <p class="text-xs text-gray-400 mt-1">Oleh: {{ item.requester?.name }} · {{ item.created_at?.split('T')[0] }}</p>
            <p v-if="item.admin_note" class="text-xs text-amber-600 mt-1">Catatan admin: {{ item.admin_note }}</p>
          </div>
          <!-- Admin actions -->
          <div v-if="isAdmin && item.status === 'DIAJUKAN'" class="flex items-center gap-1 shrink-0">
            <button class="px-2 py-1 text-[10px] font-medium bg-green-100 text-green-700 rounded hover:bg-green-200" @click="handleProcess(item.id, 'DIPROSES')">Proses</button>
            <button class="px-2 py-1 text-[10px] font-medium bg-red-100 text-red-700 rounded hover:bg-red-200" @click="handleProcess(item.id, 'DITOLAK')">Tolak</button>
            <button class="p-1 text-red-400 hover:text-red-600" @click="handleDelete(item)" title="Hapus"><TrashIcon class="w-3.5 h-3.5" /></button>
          </div>
          <div v-if="isAdmin && item.status === 'DIPROSES'" class="flex items-center gap-1 shrink-0">
            <button class="px-2 py-1 text-[10px] font-medium bg-green-100 text-green-700 rounded hover:bg-green-200" @click="handleProcess(item.id, 'SELESAI')">Selesai</button>
            <button class="p-1 text-red-400 hover:text-red-600" @click="handleDelete(item)" title="Hapus"><TrashIcon class="w-3.5 h-3.5" /></button>
          </div>
          <div v-if="isAdmin && ['SELESAI','DITOLAK'].includes(item.status)" class="shrink-0">
            <button class="p-1 text-red-400 hover:text-red-600" @click="handleDelete(item)" title="Hapus"><TrashIcon class="w-3.5 h-3.5" /></button>
          </div>
          <!-- User actions (dosen/mahasiswa) -->
          <div v-if="!isAdmin && item.status === 'DIAJUKAN'" class="flex items-center gap-1 shrink-0">
            <button class="p-1 text-blue-600 hover:text-blue-800" @click="openEdit(item)" title="Edit"><PencilIcon class="w-3.5 h-3.5" /></button>
            <button class="p-1 text-red-400 hover:text-red-600" @click="handleDelete(item)" title="Hapus"><TrashIcon class="w-3.5 h-3.5" /></button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Pengajuan -->
  <BaseModal :open="modalOpen" title="Ajukan Pembuatan Surat" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Surat (opsional)</label>
        <select v-model="form.letter_type_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="">-- Pilih Jenis --</option>
          <option v-for="t in letterTypes" :key="t.id" :value="t.id">{{ t.code }} - {{ t.name }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Keperluan Surat <span class="text-red-500">*</span></label>
        <input v-model="form.purpose" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="cth: Surat keterangan aktif kuliah" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan Tambahan</label>
        <textarea v-model="form.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Detail kebutuhan surat..." />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSubmit">{{ saving ? 'Mengirim...' : 'Ajukan' }}</button>
    </template>
  </BaseModal>
</template>
