<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import { useExcel } from '@/composables/useExcel'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ExcelButtons from '@/components/ui/ExcelButtons.vue'

interface Staff {
  id: number; nip: string; name: string; gender: string
  position: string; department: string; employment_status: string; status: boolean
}

const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<Staff>('/staff')
const { exporting, importing, importErrors, exportExcel, importExcel } = useExcel('/staff')
const search = ref(''); const filterDept = ref('')
const modalOpen = ref(false); const editingId = ref<number | null>(null); const saving = ref(false)

const form = reactive({
  nip: '', name: '', gender: 'L', birth_place: '', birth_date: '',
  email: '', phone: '', address: '', position: '', department: '',
  employment_status: 'Tetap',
})

const departments = ['Akademik', 'Keuangan', 'SDM', 'IT', 'Umum', 'Perpustakaan', 'Laboratorium', 'Kemahasiswaan', 'Lainnya']

const columns = [
  { key: 'nip', label: 'NIP' }, { key: 'name', label: 'Nama' },
  { key: 'position', label: 'Jabatan' }, { key: 'department', label: 'Unit/Bagian' },
  { key: 'employment', label: 'Status Kepegawaian' }, { key: 'aksi', label: 'Aksi', class: 'text-right' },
]

onMounted(() => load())

async function load(page = 1) {
  await fetchAll({ search: search.value, department: filterDept.value, page })
}

function openCreate() {
  editingId.value = null
  Object.assign(form, { nip: '', name: '', gender: 'L', birth_place: '', birth_date: '', email: '', phone: '', address: '', position: '', department: '', employment_status: 'Tetap' })
  modalOpen.value = true
}

function openEdit(item: Staff) {
  editingId.value = item.id
  Object.assign(form, { nip: item.nip ?? '', name: item.name, gender: item.gender ?? 'L', position: item.position ?? '', department: item.department ?? '', employment_status: item.employment_status })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false; load()
  } catch { } finally { saving.value = false }
}

async function handleDelete(item: Staff) {
  if (!confirm(`Hapus data "${item.name}"?`)) return
  await remove(item.id); load()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Tenaga Kependidikan</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola data staf dan tenaga kependidikan</p>
      </div>
      <div class="flex items-center gap-2">
        <ExcelButtons
          :exporting="exporting"
          :importing="importing"
          :import-errors="importErrors"
          :export-params="{ department: filterDept }"
          template-type="staff"
          @export="exportExcel($event, 'tenaga-kependidikan.xlsx')"
          @import="importExcel($event, () => load())"
        />
        <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors" @click="openCreate">
          <PlusIcon class="w-4 h-4" /> Tambah Staff
        </button>
      </div>
    </div>
    <div class="flex flex-wrap gap-3">
      <input v-model="search" type="text" placeholder="Cari NIP atau nama..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64" @input="load()" />
      <select v-model="filterDept" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Unit</option>
        <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
      </select>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ row.nip ?? '-' }}</td>
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
        <td class="px-4 py-3 text-gray-600 text-sm">{{ row.position ?? '-' }}</td>
        <td class="px-4 py-3">
          <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ row.department ?? '-' }}</span>
        </td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', row.employment_status === 'Tetap' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">{{ row.employment_status }}</span>
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

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Tenaga Kependidikan' : 'Tambah Tenaga Kependidikan'" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">NIP</label>
          <input v-model="form.nip" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin</label>
          <select v-model="form.gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="L">Laki-laki</option><option value="P">Perempuan</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
          <input v-model="form.phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan</label>
          <input v-model="form.position" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Unit / Bagian</label>
          <select v-model="form.department" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih --</option>
            <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status Kepegawaian</label>
          <select v-model="form.employment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="s in ['Tetap','Tidak Tetap','Honorer','Kontrak']" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tempat Lahir</label>
          <input v-model="form.birth_place" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Lahir</label>
        <input v-model="form.birth_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
