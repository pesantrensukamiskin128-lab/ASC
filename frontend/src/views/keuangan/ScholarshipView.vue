<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface ScholarshipItem {
  id: number; code: string; name: string; provider: string
  description: string; requirements: string; amount: number
  type: string; is_active: boolean; students_count: number
}

const toast = useToast()
const items = ref<ScholarshipItem[]>([])
const loading = ref(true)
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)

const form = reactive({
  code: '', name: '', provider: '', description: '', requirements: '',
  amount: 0, type: 'PARTIAL', is_active: true,
})

const typeLabels: Record<string, string> = {
  FULL: 'Penuh', PARTIAL: 'Sebagian', TUITION_ONLY: 'Hanya UKT', LIVING_COST: 'Biaya Hidup',
}

onMounted(() => load())

async function load() {
  loading.value = true
  try { const { data } = await api.get('/finance/scholarships'); items.value = data }
  finally { loading.value = false }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, { code: '', name: '', provider: '', description: '', requirements: '', amount: 0, type: 'PARTIAL', is_active: true })
  modalOpen.value = true
}

function openEdit(item: ScholarshipItem) {
  editingId.value = item.id
  Object.assign(form, { code: item.code, name: item.name, provider: item.provider ?? '', description: item.description ?? '', requirements: item.requirements ?? '', amount: item.amount ?? 0, type: item.type, is_active: item.is_active })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    if (editingId.value) {
      await api.put(`/finance/scholarships/${editingId.value}`, form)
      toast.success('Beasiswa berhasil diupdate.')
    } else {
      await api.post('/finance/scholarships', form)
      toast.success('Beasiswa berhasil ditambahkan.')
    }
    modalOpen.value = false; load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

async function handleDelete(item: ScholarshipItem) {
  if (!confirm(`Hapus "${item.name}"?`)) return
  try { await api.delete(`/finance/scholarships/${item.id}`); toast.success('Berhasil dihapus.'); load() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

function formatCurrency(n: number) {
  return n ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n) : '-'
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Beasiswa</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola data beasiswa dan penerima</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Beasiswa
      </button>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="item in items" :key="item.id" class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ item.code }}</span>
              <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">{{ item.is_active ? 'Aktif' : 'Nonaktif' }}</span>
              <span class="inline-flex px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">{{ typeLabels[item.type] ?? item.type }}</span>
            </div>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ item.name }}</h3>
            <p v-if="item.provider" class="text-xs text-gray-500">{{ item.provider }}</p>
          </div>
          <div class="flex items-center gap-1 shrink-0">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(item)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(item)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </div>
        <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
          <span class="text-xs text-gray-400">Nominal/semester</span>
          <span class="text-sm font-semibold text-gray-800">{{ formatCurrency(item.amount) }}</span>
        </div>
        <div class="flex items-center justify-between mt-1">
          <span class="text-xs text-gray-400">Penerima aktif</span>
          <span class="text-sm font-medium text-blue-600">{{ item.students_count ?? 0 }}</span>
        </div>
      </div>
    </div>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Beasiswa' : 'Tambah Beasiswa'" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.code" required placeholder="BID" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama <span class="text-red-500">*</span></label>
          <input v-model="form.name" required placeholder="Bidikmisi" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Penyedia</label>
          <input v-model="form.provider" placeholder="Kemendikbud / Internal / Swasta" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenis <span class="text-red-500">*</span></label>
          <select v-model="form.type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="FULL">Penuh (Full)</option>
            <option value="PARTIAL">Sebagian (Partial)</option>
            <option value="TUITION_ONLY">Hanya UKT</option>
            <option value="LIVING_COST">Biaya Hidup</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nominal per Semester (Rp)</label>
        <input v-model.number="form.amount" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
        <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Persyaratan</label>
        <textarea v-model="form.requirements" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-700">
        <input v-model="form.is_active" type="checkbox" class="rounded" /> Aktif
      </label>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
