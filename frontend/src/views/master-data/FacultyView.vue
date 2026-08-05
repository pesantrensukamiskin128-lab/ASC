<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface Institution { id: number; name: string }
interface Faculty {
  id: number; code: string; name: string; dean_name: string
  status: boolean; institution?: Institution; study_programs_count?: number
}

const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<Faculty>('/faculties')
const institutions = ref<Institution[]>([])
const search = ref(''); const modalOpen = ref(false); const editingId = ref<number | null>(null); const saving = ref(false)
const form = reactive({ institution_id: '', code: '', name: '', dean_name: '', status: true })

const columns = [
  { key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama Fakultas' },
  { key: 'institution', label: 'Institusi' }, { key: 'dean', label: 'Dekan' },
  { key: 'prodi', label: 'Prodi' }, { key: 'status', label: 'Status' },
  { key: 'aksi', label: 'Aksi', class: 'text-right' },
]

onMounted(async () => {
  load()
  const { data } = await api.get('/institutions')
  institutions.value = data
})

async function load(page = 1) { await fetchAll({ search: search.value, page }) }

function openCreate() {
  editingId.value = null
  Object.assign(form, { institution_id: institutions.value[0]?.id ?? '', code: '', name: '', dean_name: '', status: true })
  modalOpen.value = true
}

function openEdit(item: Faculty) {
  editingId.value = item.id
  Object.assign(form, { institution_id: item.institution?.id ?? '', code: item.code, name: item.name, dean_name: item.dean_name, status: item.status })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false; load()
  } catch { } finally { saving.value = false }
}

async function handleDelete(item: Faculty) {
  if (!confirm(`Hapus fakultas "${item.name}"?`)) return
  await remove(item.id); load()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Fakultas</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola data fakultas</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Fakultas
      </button>
    </div>
    <input v-model="search" type="text" placeholder="Cari nama atau kode..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64" @input="load()" />

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ row.code }}</td>
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.institution?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-gray-600 text-sm">{{ row.dean_name || '-' }}</td>
        <td class="px-4 py-3 text-gray-600">{{ row.study_programs_count ?? 0 }} prodi</td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', row.status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
            {{ row.status ? 'Aktif' : 'Nonaktif' }}
          </span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Fakultas' : 'Tambah Fakultas'" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Institusi <span class="text-red-500">*</span></label>
        <select v-model="form.institution_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option v-for="i in institutions" :key="i.id" :value="i.id">{{ i.name }}</option>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.code" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option :value="true">Aktif</option><option :value="false">Nonaktif</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Fakultas <span class="text-red-500">*</span></label>
        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Dekan</label>
        <input v-model="form.dean_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
