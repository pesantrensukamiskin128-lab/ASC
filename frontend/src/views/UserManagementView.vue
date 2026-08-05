<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface Role { id: number; name: string }
interface User {
  id: number; name: string; email: string; username: string
  is_active: boolean; last_login_at: string
  roles: { name: string }[]
}

const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<User>('/users')
const rolesList = ref<Role[]>([])
const search = ref(''); const filterRole = ref('')
const modalOpen = ref(false); const editingId = ref<number | null>(null); const saving = ref(false)

const form = reactive({ name: '', email: '', username: '', password: '', role: '', is_active: true })

const columns = [
  { key: 'name', label: 'Nama' }, { key: 'email', label: 'Email' },
  { key: 'username', label: 'Username' }, { key: 'role', label: 'Role' },
  { key: 'login', label: 'Login Terakhir' }, { key: 'status', label: 'Status' },
  { key: 'aksi', label: 'Aksi', class: 'text-right' },
]

onMounted(async () => {
  load()
  const { data } = await api.get('/roles')
  rolesList.value = data
})

async function load(page = 1) { await fetchAll({ search: search.value, role: filterRole.value, page }) }

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', email: '', username: '', password: '', role: '', is_active: true })
  modalOpen.value = true
}

function openEdit(item: User) {
  editingId.value = item.id
  Object.assign(form, { name: item.name, email: item.email, username: item.username, password: '', role: item.roles[0]?.name ?? '', is_active: item.is_active })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    const payload: any = { ...form }
    if (!payload.password) delete payload.password
    editingId.value ? await update(editingId.value, payload) : await create(payload)
    modalOpen.value = false; load()
  } catch { } finally { saving.value = false }
}

async function handleDelete(item: User) {
  if (!confirm(`Hapus user "${item.name}"?`)) return
  await remove(item.id); load()
}

function formatDate(d: string) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Manajemen Pengguna</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola akun dan hak akses pengguna sistem</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Pengguna
      </button>
    </div>
    <div class="flex flex-wrap gap-3">
      <input v-model="search" type="text" placeholder="Cari nama atau email..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64" @input="load()" />
      <select v-model="filterRole" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Role</option>
        <option v-for="r in rolesList" :key="r.id" :value="r.name">{{ r.name }}</option>
      </select>
    </div>
    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
        <td class="px-4 py-3 text-gray-600 text-sm">{{ row.email }}</td>
        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ row.username ?? '-' }}</td>
        <td class="px-4 py-3">
          <span v-for="role in row.roles" :key="role.name" class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 mr-1">{{ role.name }}</span>
        </td>
        <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(row.last_login_at) }}</td>
        <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', row.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600']">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition-colors" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Pengguna' : 'Tambah Pengguna'" size="lg" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
          <input v-model="form.email" type="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <input v-model="form.username" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password {{ editingId ? '(kosongkan jika tidak diubah)' : '*' }}</label>
          <input v-model="form.password" type="password" :required="!editingId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
          <select v-model="form.role" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih Role --</option>
            <option v-for="r in rolesList" :key="r.id" :value="r.name">{{ r.name }}</option>
          </select>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <input v-model="form.is_active" type="checkbox" id="user_active" class="rounded" />
        <label for="user_active" class="text-sm text-gray-700">Akun aktif</label>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg transition-colors" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
