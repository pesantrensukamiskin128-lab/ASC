<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import { PlusIcon, PencilIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const toast = useToast()

const periods  = ref<any[]>([])
const loading  = ref(true)
const showForm = ref(false)
const saving   = ref(false)
const editId   = ref<number | null>(null)

const empty = () => ({
  name: '', type: 'penelitian', year: new Date().getFullYear(),
  open_date: '', close_date: '', is_active: false, description: '',
})
const form = ref(empty())

onMounted(load)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/penelitian-periods')
    periods.value = data
  } finally { loading.value = false }
}

function openCreate() {
  editId.value = null
  form.value = empty()
  showForm.value = true
}

function openEdit(p: any) {
  editId.value = p.id
  form.value = {
    name: p.name, type: p.type, year: p.year,
    open_date: p.open_date ?? '', close_date: p.close_date ?? '',
    is_active: p.is_active, description: p.description ?? '',
  }
  showForm.value = true
}

async function save() {
  if (!form.value.name.trim()) { toast.error('Nama periode wajib diisi.'); return }
  saving.value = true
  try {
    if (editId.value) {
      await api.put(`/penelitian-periods/${editId.value}`, form.value)
      toast.success('Periode diperbarui.')
    } else {
      await api.post('/penelitian-periods', form.value)
      toast.success('Periode berhasil dibuat.')
    }
    showForm.value = false
    load()
  } catch (e: any) {
    const errs = e?.response?.data?.errors
    toast.error(errs ? Object.values(errs).flat().join(', ') : (e?.response?.data?.message ?? 'Gagal menyimpan.'))
  } finally { saving.value = false }
}

async function del(p: any) {
  if (!confirm(`Hapus periode "${p.name}"? Semua proposal terkait akan kehilangan referensi periode.`)) return
  try {
    await api.delete(`/penelitian-periods/${p.id}`)
    toast.success('Periode dihapus.')
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal menghapus.') }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Periode Hibah</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola periode hibah penelitian dan pengabdian kepada masyarakat</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg"
        @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Periode
      </button>
    </div>

    <!-- List -->
    <div v-if="loading" class="text-center py-10 text-gray-400">Memuat...</div>
    <div v-else-if="!periods.length" class="text-center py-10 text-gray-400 bg-white rounded-xl border border-gray-200">
      <p class="text-4xl mb-2">📅</p>
      <p>Belum ada periode hibah.</p>
      <button class="mt-3 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg" @click="openCreate">
        Buat Periode Pertama
      </button>
    </div>
    <div v-else class="grid gap-3">
      <div v-for="p in periods" :key="p.id"
        class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-4">
        <!-- Icon -->
        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-lg shrink-0"
          :class="p.type === 'penelitian' ? 'bg-blue-50' : 'bg-green-50'">
          {{ p.type === 'penelitian' ? '🔬' : '🤝' }}
        </div>
        <!-- Info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <p class="font-semibold text-gray-900">{{ p.name }}</p>
            <span v-if="p.is_active"
              class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Aktif</span>
            <span v-else class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Tidak Aktif</span>
          </div>
          <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 flex-wrap">
            <span>{{ p.type === 'penelitian' ? 'Penelitian' : 'Pengabdian' }}</span>
            <span>· {{ p.year }}</span>
            <span v-if="p.open_date">· Buka: {{ p.open_date }}</span>
            <span v-if="p.close_date">· Tutup: {{ p.close_date }}</span>
          </div>
          <p v-if="p.description" class="text-xs text-gray-400 mt-1 truncate">{{ p.description }}</p>
        </div>
        <!-- Actions -->
        <div class="flex items-center gap-1 shrink-0">
          <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" title="Edit" @click="openEdit(p)">
            <PencilIcon class="w-4 h-4" />
          </button>
          <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" title="Hapus" @click="del(p)">
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
          <h2 class="font-semibold text-gray-900">{{ editId ? 'Edit Periode' : 'Tambah Periode Hibah' }}</h2>
          <button class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100" @click="showForm = false">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <div class="px-5 py-4 space-y-4">
          <!-- Nama -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Nama Periode <span class="text-red-500">*</span>
            </label>
            <input v-model="form.name" type="text" placeholder="Contoh: Hibah Penelitian 2026"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <!-- Jenis & Tahun -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenis <span class="text-red-500">*</span></label>
              <select v-model="form.type"
                class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="penelitian">Penelitian</option>
                <option value="pengabdian">Pengabdian</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Tahun <span class="text-red-500">*</span></label>
              <input v-model.number="form.year" type="number" min="2000" :max="new Date().getFullYear() + 5"
                class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>

          <!-- Tanggal buka & tutup -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Tgl. Buka</label>
              <input v-model="form.open_date" type="date"
                class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Tgl. Tutup</label>
              <input v-model="form.close_date" type="date"
                class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>

          <!-- Deskripsi -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
            <textarea v-model="form.description" rows="2" placeholder="Keterangan tambahan (opsional)"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
          </div>

          <!-- Status aktif -->
          <label class="flex items-center gap-3 cursor-pointer">
            <div class="relative">
              <input type="checkbox" v-model="form.is_active" class="sr-only" />
              <div :class="['w-10 h-6 rounded-full transition-colors', form.is_active ? 'bg-blue-600' : 'bg-gray-300']" />
              <div :class="['absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform', form.is_active ? 'translate-x-5' : 'translate-x-1']" />
            </div>
            <span class="text-sm font-medium text-gray-700">Periode Aktif</span>
            <span class="text-xs text-gray-400">(dosen dapat memilih periode ini saat membuat proposal)</span>
          </label>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-gray-200">
          <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="showForm = false">
            Batal
          </button>
          <button :disabled="saving" @click="save"
            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
            {{ saving ? 'Menyimpan...' : (editId ? 'Simpan Perubahan' : 'Buat Periode') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
