<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route = useRoute()
const toast = useToast()
const auth = useAuthStore()
const isLecturer = auth.user?.roles?.includes('DOSEN') || auth.user?.roles?.includes('SUPER_ADMIN')

const items = ref<any[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const filterType = ref('')
const students = ref<any[]>([])

const modalOpen = ref(false)
const saving = ref(false)
const form = reactive({ student_id: (route.query.student_id as string) || '', type: 'UMUM', content: '', is_important: false, semester_id: '' })

const noteTypes = ['UMUM', 'PERINGATAN', 'REKOMENDASI', 'PRESTASI', 'PELANGGARAN']
const typeColor: Record<string, string> = {
  UMUM: 'bg-gray-100 text-gray-600', PERINGATAN: 'bg-red-100 text-red-700',
  REKOMENDASI: 'bg-blue-100 text-blue-700', PRESTASI: 'bg-green-100 text-green-700',
  PELANGGARAN: 'bg-orange-100 text-orange-700',
}

const columns = [
  { key: 'date', label: 'Tanggal' },
  { key: 'student', label: 'Mahasiswa' },
  { key: 'type', label: 'Jenis' },
  { key: 'content', label: 'Catatan' },
  { key: 'aksi', label: '', class: 'text-right' },
]

onMounted(async () => {
  load()
  if (isLecturer) {
    const { data } = await api.get('/guidance/my-students')
    students.value = data
  }
})

async function load(page = 1) {
  loading.value = true
  try {
    const params: any = { page, type: filterType.value }
    if (route.query.student_id) params.student_id = route.query.student_id
    const { data } = await api.get('/guidance/academic-notes', { params })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

function openCreate() {
  Object.assign(form, { student_id: (route.query.student_id as string) || '', type: 'UMUM', content: '', is_important: false, semester_id: '' })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    await api.post('/guidance/academic-notes', form)
    toast.success('Catatan akademik berhasil disimpan.')
    modalOpen.value = false; load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

async function handleDelete(item: any) {
  if (!confirm('Hapus catatan ini?')) return
  try { await api.delete(`/guidance/academic-notes/${item.id}`); toast.success('Dihapus.'); load() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

function formatDate(d: string) { return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Catatan Akademik</h1>
        <p class="text-sm text-gray-500 mt-0.5">Catatan dosen wali terhadap perkembangan akademik mahasiswa</p>
      </div>
      <button v-if="isLecturer" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Catatan
      </button>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterType" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Jenis</option>
        <option v-for="t in noteTypes" :key="t" :value="t">{{ t }}</option>
      </select>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(row.created_at) }}</td>
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.student?.name }}</p>
          <p class="text-xs text-gray-500">{{ row.student?.nim }}</p>
        </td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', typeColor[row.type] ?? '']">{{ row.type }}</span>
          <span v-if="row.is_important" class="ml-1 text-xs text-red-500">⚡</span>
        </td>
        <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">{{ row.content }}</td>
        <td class="px-4 py-3">
          <button v-if="isLecturer" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" title="Tambah Catatan Akademik" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mahasiswa <span class="text-red-500">*</span></label>
          <select v-model="form.student_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">-- Pilih --</option>
            <option v-for="s in students" :key="s.id" :value="s.id">{{ s.nim }} - {{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenis</label>
          <select v-model="form.type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option v-for="t in noteTypes" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan <span class="text-red-500">*</span></label>
        <textarea v-model="form.content" required rows="4" placeholder="Isi catatan akademik..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-700">
        <input v-model="form.is_important" type="checkbox" class="rounded" /> Tandai sebagai penting
      </label>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
