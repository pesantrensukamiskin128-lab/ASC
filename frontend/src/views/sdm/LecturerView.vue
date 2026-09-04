<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, PencilIcon, TrashIcon, CameraIcon, EyeIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import { useExcel } from '@/composables/useExcel'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ExcelButtons from '@/components/ui/ExcelButtons.vue'
import api from '@/services/api'

interface StudyProgram { id: number; name: string; code: string }
interface Lecturer {
  id: number
  nidn: string; nuptk: string; nip: string
  degree_front: string; degree_back: string
  full_name: string
  gender: string; birth_place: string; birth_date: string
  email: string; phone: string; address: string
  photo_path: string | null
  academic_rank: string
  employment_status: string; status: boolean
  study_program?: StudyProgram
}

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()
const canCreate = auth.hasPermission('mahasiswa.create') || auth.hasRole('SUPER_ADMIN')
const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<Lecturer>('/lecturers')
const { exporting, importing, importErrors, exportExcel, importExcel } = useExcel('/lecturers')

// Bulk selection
const selectedIds = ref<number[]>([])
const bulkDeleting = ref(false)
const isAllSelected = computed(() => items.value.length > 0 && items.value.every((i: any) => selectedIds.value.includes(i.id)))
function toggleSelectAll() { if (isAllSelected.value) selectedIds.value = []; else selectedIds.value = items.value.map((i: any) => i.id) }
function toggleSelect(id: number) { const idx = selectedIds.value.indexOf(id); if (idx >= 0) selectedIds.value.splice(idx, 1); else selectedIds.value.push(id) }
async function bulkDelete() {
  if (!selectedIds.value.length) return
  if (!confirm(`Hapus ${selectedIds.value.length} dosen yang dipilih?`)) return
  bulkDeleting.value = true
  try { const { data } = await api.post('/lecturers/bulk-delete', { ids: selectedIds.value }); toast.success(data.message); selectedIds.value = []; load() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { bulkDeleting.value = false }
}

const programs    = ref<StudyProgram[]>([])
const search      = ref('')
const filterProgram = ref('')
const filterStatus = ref('')
const modalOpen   = ref(false)
const editingId   = ref<number | null>(null)
const saving      = ref(false)

const form = reactive({
  study_program_id: '', nidn: '', nuptk: '', nip: '',
  degree_front: '', degree_back: '',
  full_name: '', gender: 'L', birth_place: '', birth_date: '',
  email: '', phone: '', address: '',
  academic_rank: '', employment_status: 'Tetap',
  status: true,
})

const columns = [
  { key: 'select', label: '', class: 'w-8' },
  { key: 'nidn',       label: 'NIDN' },
  { key: 'name',       label: 'Nama Lengkap' },
  { key: 'program',    label: 'Prodi' },
  { key: 'degree',     label: 'Gelar' },
  { key: 'rank',       label: 'Jabatan Akademik' },
  { key: 'employment', label: 'Status Kepegawaian' },
  { key: 'active_status', label: 'Status Dosen' },
  { key: 'aksi',       label: 'Aksi', class: 'text-right' },
]

/** Tampilkan nama beserta gelar: "Dr. Ahmad Fauzi, S.Kom., M.T." */
function displayName(row: Lecturer): string {
  return [row.degree_front, row.full_name, row.degree_back]
    .filter(Boolean).join(' ')
}

onMounted(async () => {
  load()
  const { data } = await api.get('/study-programs/all')
  programs.value = data
})

async function load(page = 1) {
  await fetchAll({
    search: search.value,
    study_program_id: filterProgram.value,
    ...(filterStatus.value !== '' ? { status: filterStatus.value } : {}),
    page,
  })
}

function openCreate() {
  editingId.value = null
  photoPreview.value = null
  Object.assign(form, {
    study_program_id: '', nidn: '', nuptk: '', nip: '',
    degree_front: '', degree_back: '',
    full_name: '', gender: 'L', birth_place: '', birth_date: '',
    email: '', phone: '', address: '',
    academic_rank: '', employment_status: 'Tetap',
    status: true,
  })
  modalOpen.value = true
}

function openEdit(item: Lecturer) {
  editingId.value = item.id
  photoPreview.value = getPhotoUrl(item)
  Object.assign(form, {
    study_program_id: item.study_program?.id ?? '',
    nidn:             item.nidn ?? '',
    nuptk:            item.nuptk ?? '',
    nip:              item.nip ?? '',
    degree_front:     item.degree_front ?? '',
    degree_back:      item.degree_back ?? '',
    full_name:        item.full_name,
    gender:           item.gender ?? 'L',
    birth_place:      item.birth_place ?? '',
    birth_date:       item.birth_date ?? '',
    email:            item.email ?? '',
    phone:            item.phone ?? '',
    address:          item.address ?? '',
    academic_rank:    item.academic_rank ?? '',
    employment_status:item.employment_status ?? 'Tetap',
    status:            item.status ?? true,
  })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false
    load()
  } catch { } finally { saving.value = false }
}

async function handleDelete(item: Lecturer) {
  if (!confirm(`Hapus dosen "${item.full_name}"?`)) return
  await remove(item.id)
  load()
}

async function toggleStatus(item: Lecturer) {
  const nextStatus = !item.status
  const action = nextStatus ? 'mengaktifkan' : 'menonaktifkan'
  if (!confirm(`Yakin ingin ${action} dosen "${item.full_name}"?`)) return
  try {
    await api.put(`/lecturers/${item.id}`, { status: nextStatus })
    toast.success(`Dosen berhasil ${nextStatus ? 'diaktifkan' : 'dinonaktifkan'}.`)
    load(pagination.currentPage)
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal mengubah status dosen.')
  }
}

// --- Foto upload ---
const photoInput     = ref<HTMLInputElement | null>(null)
const photoPreview   = ref<string | null>(null)
const uploadingPhoto = ref(false)

function getPhotoUrl(row: Lecturer): string | null {
  if (!row.photo_path) return null
  const base = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/api\/?$/, '')
  return `${base}/storage/${row.photo_path}`
}

function triggerPhotoInput() {
  photoInput.value?.click()
}

function onPhotoSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return

  // Preview local
  const reader = new FileReader()
  reader.onload = () => { photoPreview.value = reader.result as string }
  reader.readAsDataURL(file)

  // Upload langsung
  uploadPhoto(file)
}

async function uploadPhoto(file: File) {
  if (!editingId.value) {
    toast.info('Simpan data dosen terlebih dahulu sebelum upload foto.')
    return
  }
  uploadingPhoto.value = true
  try {
    const formData = new FormData()
    formData.append('photo', file)
    const { data } = await api.post(`/lecturers/${editingId.value}/photo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    toast.success(data.message)
    photoPreview.value = data.photo_url
    load() // refresh table
  } catch {
    toast.error('Gagal mengupload foto.')
    photoPreview.value = null
  } finally {
    uploadingPhoto.value = false
    if (photoInput.value) photoInput.value.value = ''
  }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Data Dosen</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola data dosen dan tenaga pengajar</p>
      </div>
      <div class="flex items-center gap-2">
        <ExcelButtons
          :exporting="exporting"
          :importing="canCreate ? importing : false"
          :import-errors="importErrors"
          :export-params="{ study_program_id: filterProgram }"
          template-type="lecturers"
          :hide-import="!canCreate"
          @export="exportExcel($event, 'dosen.xlsx')"
          @import="importExcel($event, () => load())"
        />
        <button
          v-if="canCreate"
          class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
          @click="openCreate"
        >
          <PlusIcon class="w-4 h-4" /> Tambah Dosen
        </button>
      </div>
    </div>

    <!-- Filter -->
    <div class="flex flex-wrap items-center gap-3">
      <input
        v-model="search" type="text" placeholder="Cari NIDN, NUPTK atau nama..."
        class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64"
        @input="load()"
      />
      <select
        v-model="filterProgram"
        class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        @change="load()"
      >
        <option value="">Semua Prodi</option>
        <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
      </select>
      <select
        v-model="filterStatus"
        class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        @change="load()"
      >
        <option value="">Semua Status Dosen</option>
        <option value="1">Aktif</option>
        <option value="0">Nonaktif</option>
      </select>
      <button v-if="selectedIds.length" :disabled="bulkDeleting" class="ml-auto px-3 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white text-xs font-medium rounded-lg inline-flex items-center gap-1.5" @click="bulkDelete">
        <TrashIcon class="w-3.5 h-3.5" /> Hapus {{ selectedIds.length }} dipilih
      </button>
    </div>

    <!-- Tabel -->
    <DataTable
      :columns="columns" :rows="items" :loading="loading"
      :total="pagination.total" :current-page="pagination.currentPage"
      :last-page="pagination.lastPage" @page-change="load"
    >
      <template #header-select>
        <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded border-gray-300" />
      </template>
      <template #default="{ row }">
        <td class="px-4 py-3"><input type="checkbox" :checked="selectedIds.includes(row.id)" @change="toggleSelect(row.id)" class="rounded border-gray-300" /></td>
        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ row.nidn ?? '-' }}</td>
        <td class="px-4 py-3">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-100 shrink-0 flex items-center justify-center">
              <img
                v-if="getPhotoUrl(row)"
                :src="getPhotoUrl(row)!"
                class="w-full h-full object-cover"
                alt=""
              />
              <span v-else class="text-xs font-bold text-gray-400">
                {{ row.full_name?.charAt(0).toUpperCase() }}
              </span>
            </div>
            <div class="min-w-0">
              <p class="font-medium text-gray-900 text-sm truncate">{{ displayName(row) }}</p>
              <p v-if="row.email" class="text-xs text-gray-400 truncate">{{ row.email }}</p>
            </div>
          </div>
        </td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.study_program?.code ?? '-' }}</td>
        <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
          <span v-if="row.degree_front" class="font-medium text-blue-700">{{ row.degree_front }}</span>
          <span v-if="row.degree_front && row.degree_back"> · </span>
          <span v-if="row.degree_back">{{ row.degree_back }}</span>
          <span v-if="!row.degree_front && !row.degree_back" class="text-gray-400">-</span>
        </td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.academic_rank ?? '-' }}</td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
            row.employment_status === 'Tetap' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
            {{ row.employment_status }}
          </span>
        </td>
        <td class="px-4 py-3">
          <button
            type="button"
            :class="['inline-flex px-2 py-1 rounded-full text-xs font-semibold transition-colors', row.status ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300']"
            :title="row.status ? 'Klik untuk menonaktifkan dosen' : 'Klik untuk mengaktifkan dosen'"
            @click="toggleStatus(row)"
          >
            {{ row.status ? 'Aktif' : 'Nonaktif' }}
          </button>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" title="Detail & Jabatan" @click="router.push(`/sdm/lecturers/${row.id}`)">
              <EyeIcon class="w-4 h-4" />
            </button>
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(row)">
              <PencilIcon class="w-4 h-4" />
            </button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <!-- Modal -->
  <BaseModal
    :open="modalOpen"
    :title="editingId ? 'Edit Dosen' : 'Tambah Dosen'"
    size="xl"
    @close="modalOpen = false"
  >
    <form class="space-y-4" @submit.prevent="handleSave">
      <!-- Upload Foto -->
      <div class="flex items-center gap-4">
        <div class="relative">
          <div class="w-20 h-20 rounded-xl overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center">
            <img
              v-if="photoPreview"
              :src="photoPreview"
              class="w-full h-full object-cover"
              alt="Foto dosen"
            />
            <span v-else class="text-2xl font-bold text-gray-300">
              {{ form.full_name?.charAt(0)?.toUpperCase() || '?' }}
            </span>
          </div>
          <button
            v-if="editingId"
            type="button"
            :disabled="uploadingPhoto"
            class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-blue-600 hover:bg-blue-700 text-white flex items-center justify-center shadow-md transition-colors disabled:bg-blue-400"
            title="Upload foto"
            @click="triggerPhotoInput"
          >
            <CameraIcon class="w-4 h-4" />
          </button>
        </div>
        <div class="text-sm text-gray-500">
          <p v-if="editingId" class="text-gray-600">Klik ikon kamera untuk upload foto</p>
          <p v-else class="text-gray-400 italic">Upload foto tersedia setelah data disimpan</p>
          <p class="text-xs text-gray-400 mt-0.5">Format: JPG, PNG, WebP. Maks 2MB</p>
        </div>
        <input
          ref="photoInput"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="hidden"
          @change="onPhotoSelected"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Program Studi</label>
        <select v-model="form.study_program_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Pilih Program Studi --</option>
          <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
        <input v-model="form.full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">NIDN</label>
          <input v-model="form.nidn" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">NUPTK</label>
          <input v-model="form.nuptk" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">NIP</label>
          <input v-model="form.nip" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin</label>
          <select v-model="form.gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="L">Laki-laki</option>
            <option value="P">Perempuan</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tempat Lahir</label>
          <input v-model="form.birth_place" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Lahir</label>
          <input v-model="form.birth_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
          <input v-model="form.phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <!-- Gelar depan (sebelum nama): Dr., Prof., H., Ir. -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Gelar Depan
            <span class="font-normal text-gray-400 text-xs ml-1">sebelum nama — contoh: Dr., Prof., H., Ir.</span>
          </label>
          <input
            v-model="form.degree_front"
            placeholder="Dr."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <!-- Gelar belakang (setelah nama): S.Kom., M.T., Ph.D. -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Gelar Belakang
            <span class="font-normal text-gray-400 text-xs ml-1">setelah nama — contoh: S.Kom., M.T., Ph.D.</span>
          </label>
          <input
            v-model="form.degree_back"
            placeholder="S.Kom., M.T."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <!-- Preview nama lengkap dengan gelar -->
        <div v-if="form.degree_front || form.full_name || form.degree_back" class="col-span-2">
          <label class="block text-xs font-medium text-gray-500 mb-1">Preview nama dengan gelar</label>
          <p class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700">
            {{ [form.degree_front, form.full_name, form.degree_back].filter(Boolean).join(' ') || '—' }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan Akademik</label>
          <select v-model="form.academic_rank" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih --</option>
            <option v-for="j in ['Asisten Ahli','Lektor','Lektor Kepala','Guru Besar','Tenaga Pengajar']" :key="j" :value="j">{{ j }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status Kepegawaian</label>
          <select v-model="form.employment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="s in ['Tetap','Tidak Tetap','DPK','Honorer']" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status Dosen</label>
          <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option :value="true">Aktif</option>
            <option :value="false">Nonaktif</option>
          </select>
          <p class="text-xs text-gray-400 mt-1">Dosen nonaktif tidak muncul pada pilihan penugasan baru, tetapi riwayat datanya tetap tersimpan.</p>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
        <textarea v-model="form.address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
    </form>

    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" @click="modalOpen = false">Batal</button>
      <button
        :disabled="saving"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg transition-colors"
        @click="handleSave"
      >
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </template>
  </BaseModal>
</template>
