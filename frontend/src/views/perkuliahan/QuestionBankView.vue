<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import { PlusIcon, EyeIcon, TrashIcon, PencilIcon, ShareIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()
const isAdmin = computed(() => auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK'))

const items = ref<any[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const courses = ref<any[]>([])
const filterCourse = ref('')
const search = ref('')

const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const form = reactive({ course_id: '', title: '', description: '', is_shared: false })

const columns = [
  { key: 'title', label: 'Judul Bank Soal' },
  { key: 'course', label: 'Mata Kuliah' },
  { key: 'items', label: 'Jumlah Soal', class: 'text-center' },
  { key: 'shared', label: 'Dibagikan', class: 'text-center' },
  { key: 'creator', label: 'Pembuat' },
  { key: 'aksi', label: '', class: 'text-right' },
]

onMounted(async () => {
  load()
  if (isAdmin.value) {
    const { data } = await api.get('/courses/all')
    courses.value = data
  } else {
    // Dosen: hanya MK yang ditugaskan
    try {
      const { data } = await api.get('/rpkps/my-courses')
      courses.value = data.courses ?? []
    } catch {
      courses.value = []
    }
  }
})

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/question-banks', { params: { course_id: filterCourse.value, search: search.value, page } })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, { course_id: '', title: '', description: '', is_shared: false })
  modalOpen.value = true
}

function openEdit(item: any) {
  editingId.value = item.id
  Object.assign(form, { course_id: item.course_id, title: item.title, description: item.description ?? '', is_shared: item.is_shared })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    if (editingId.value) {
      await api.put(`/question-banks/${editingId.value}`, form)
      toast.success('Bank soal diupdate.')
    } else {
      await api.post('/question-banks', form)
      toast.success('Bank soal berhasil dibuat.')
    }
    modalOpen.value = false; load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

async function handleDelete(item: any) {
  if (!confirm(`Hapus bank soal "${item.title}"? Semua soal di dalamnya akan dihapus.`)) return
  try { await api.delete(`/question-banks/${item.id}`); toast.success('Dihapus.'); load() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Bank Soal</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola kumpulan soal per mata kuliah untuk digunakan di ujian</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Buat Bank Soal
      </button>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterCourse" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Mata Kuliah</option>
        <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }}</option>
      </select>
      <input v-model="search" type="text" placeholder="Cari judul..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm w-52" @input="load()" />
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.title }}</p>
          <p v-if="row.description" class="text-xs text-gray-500 truncate max-w-xs">{{ row.description }}</p>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600">{{ row.course?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-center"><span class="inline-flex px-2.5 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">{{ row.items_count }}</span></td>
        <td class="px-4 py-3 text-center">
          <ShareIcon v-if="row.is_shared" class="w-4 h-4 text-green-600 mx-auto" />
          <span v-else class="text-xs text-gray-400">Privat</span>
        </td>
        <td class="px-4 py-3 text-sm text-gray-500">{{ row.creator?.name ?? '-' }}</td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/bank-soal/${row.id}`)"><EyeIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-50" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Bank Soal' : 'Buat Bank Soal'" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Mata Kuliah <span class="text-red-500">*</span></label>
        <select v-model="form.course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="">-- Pilih --</option>
          <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Judul <span class="text-red-500">*</span></label>
        <input v-model="form.title" required placeholder="Bank Soal UTS Hukum Perbankan 2026" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
        <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-700">
        <input v-model="form.is_shared" type="checkbox" class="rounded" /> Bagikan ke dosen lain (shared)
      </label>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
