<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface FeeType {
  id: number; code: string; name: string; description: string
  is_active: boolean; is_mandatory: boolean; is_recurring: boolean
}

const toast = useToast()
const items = ref<FeeType[]>([])
const loading = ref(true)
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)

const form = reactive({
  code: '', name: '', description: '',
  is_active: true, is_mandatory: true, is_recurring: false,
})

onMounted(() => load())

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/finance/fee-types')
    items.value = data
  } finally { loading.value = false }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, { code: '', name: '', description: '', is_active: true, is_mandatory: true, is_recurring: false })
  modalOpen.value = true
}

function openEdit(item: FeeType) {
  editingId.value = item.id
  Object.assign(form, { code: item.code, name: item.name, description: item.description ?? '', is_active: item.is_active, is_mandatory: item.is_mandatory, is_recurring: item.is_recurring })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    if (editingId.value) {
      await api.put(`/finance/fee-types/${editingId.value}`, form)
      toast.success('Jenis tagihan berhasil diupdate.')
    } else {
      await api.post('/finance/fee-types', form)
      toast.success('Jenis tagihan berhasil ditambahkan.')
    }
    modalOpen.value = false; load()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal menyimpan.')
  } finally { saving.value = false }
}

async function handleDelete(item: FeeType) {
  if (!confirm(`Hapus "${item.name}"?`)) return
  try {
    await api.delete(`/finance/fee-types/${item.id}`)
    toast.success('Berhasil dihapus.'); load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal menghapus.') }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Jenis Tagihan</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola jenis tagihan mahasiswa (SPP, UKT, Ujian, Wisuda, dll)</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Jenis
      </button>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wide">
            <th class="px-4 py-3">Kode</th>
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3 text-center">Wajib</th>
            <th class="px-4 py-3 text-center">Berulang</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ item.code }}</td>
            <td class="px-4 py-3">
              <p class="font-medium text-gray-900">{{ item.name }}</p>
              <p v-if="item.description" class="text-xs text-gray-500 truncate max-w-xs">{{ item.description }}</p>
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="['text-xs font-medium', item.is_mandatory ? 'text-red-600' : 'text-gray-400']">{{ item.is_mandatory ? 'Ya' : 'Tidak' }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="['text-xs font-medium', item.is_recurring ? 'text-blue-600' : 'text-gray-400']">{{ item.is_recurring ? 'Per Semester' : 'Sekali' }}</span>
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">{{ item.is_active ? 'Aktif' : 'Nonaktif' }}</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-1">
                <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(item)"><PencilIcon class="w-4 h-4" /></button>
                <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(item)"><TrashIcon class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Jenis Tagihan' : 'Tambah Jenis Tagihan'" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.code" required placeholder="SPP" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama <span class="text-red-500">*</span></label>
          <input v-model="form.name" required placeholder="Sumbangan Pembinaan Pendidikan" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
        <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="flex items-center gap-6">
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.is_mandatory" type="checkbox" class="rounded" /> Wajib Bayar
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.is_recurring" type="checkbox" class="rounded" /> Berulang Per Semester
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.is_active" type="checkbox" class="rounded" /> Aktif
        </label>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
