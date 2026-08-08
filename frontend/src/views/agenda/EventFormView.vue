<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const router = useRouter()
const route = useRoute()
const toast = useToast()
const saving = ref(false)
const allUsers = ref<any[]>([])
const prodiList = ref<any[]>([])
const inviteeSearch = ref('')
const inviteeFilter = ref('all')
const inviteeProdi = ref('')

const filteredUsers = computed(() => {
  let users = allUsers.value

  if (inviteeFilter.value === 'dosen') {
    users = users.filter((u: any) => u.role === 'DOSEN' && !u.has_position)
  } else if (inviteeFilter.value === 'mahasiswa') {
    users = users.filter((u: any) => u.role === 'MAHASISWA')
  } else if (inviteeFilter.value === 'struktural') {
    users = users.filter((u: any) => u.has_position)
  }

  if (inviteeProdi.value && (inviteeFilter.value === 'dosen' || inviteeFilter.value === 'mahasiswa')) {
    users = users.filter((u: any) => u.study_program_id == inviteeProdi.value)
  }

  if (inviteeSearch.value.trim()) {
    const q = inviteeSearch.value.toLowerCase()
    users = users.filter((u: any) => u.name.toLowerCase().includes(q))
  }

  return users
})

const isAllFilteredSelected = computed(() => {
  if (!filteredUsers.value.length) return false
  return filteredUsers.value.every((u: any) => form.invitee_ids.includes(u.id))
})

const editId = route.params.id as string | undefined
const isEdit = !!editId

const form = reactive({
  title: '',
  organizer: '',
  category: 'Rapat',
  type: 'Luring',
  location: '',
  meeting_link: '',
  event_date: new Date().toISOString().split('T')[0],
  start_time: '08:00',
  end_time: '10:00',
  description: '',
  invitee_ids: [] as number[],
})

const categories = ['Rapat', 'Seminar', 'Workshop', 'Pelatihan', 'Wisuda', 'Dies Natalis', 'Lainnya']
const types = ['Luring', 'Daring', 'Hibrid']

onMounted(async () => {
  const [usersRes, prodiRes] = await Promise.all([
    api.get('/user-list'),
    api.get('/study-programs/all'),
  ])
  allUsers.value = usersRes.data
  prodiList.value = prodiRes.data

  if (isEdit) {
    const { data: ev } = await api.get(`/events/${editId}`)
    Object.assign(form, {
      title: ev.title, organizer: ev.organizer ?? '', category: ev.category,
      type: ev.type, location: ev.location ?? '', meeting_link: ev.meeting_link ?? '',
      event_date: ev.event_date, start_time: ev.start_time?.slice(0,5) ?? '08:00',
      end_time: ev.end_time?.slice(0,5) ?? '10:00', description: ev.description ?? '',
      invitee_ids: ev.invitees?.map((u: any) => u.id) ?? [],
    })
  }
})

async function handleSave() {
  if (!form.title.trim()) { toast.error('Nama agenda wajib diisi.'); return }
  saving.value = true
  try {
    if (isEdit) {
      await api.put(`/events/${editId}`, form)
      toast.success('Agenda berhasil diupdate.')
    } else {
      await api.post('/events', form)
      toast.success('Agenda berhasil dibuat.')
    }
    router.push('/agenda')
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal menyimpan agenda.')
  } finally { saving.value = false }
}

function toggleAll() {
  const ids = filteredUsers.value.map((u: any) => u.id)
  if (isAllFilteredSelected.value) {
    form.invitee_ids = form.invitee_ids.filter(id => !ids.includes(id))
  } else {
    const merged = new Set([...form.invitee_ids, ...ids])
    form.invitee_ids = [...merged]
  }
}
</script>

<template>
  <div class="space-y-5 max-w-3xl">
    <div>
      <h1 class="text-xl font-bold text-gray-900">{{ isEdit ? 'Edit Agenda' : 'Buat Agenda Kegiatan' }}</h1>
      <p class="text-sm text-gray-500 mt-0.5">Isi informasi kegiatan dan undang peserta</p>
    </div>

    <form class="space-y-5" @submit.prevent="handleSave">
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Informasi Kegiatan</h2>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama Agenda <span class="text-red-500">*</span></label>
          <input v-model="form.title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="cth: Rapat Koordinasi Dosen" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Penyelenggara</label>
            <input v-model="form.organizer" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="cth: Bagian Akademik" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
            <select v-model="form.category" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
            <select v-model="form.type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
              <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal <span class="text-red-500">*</span></label>
            <input v-model="form.event_date" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Mulai</label>
              <input v-model="form.start_time" type="time" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Selesai</label>
              <input v-model="form.end_time" type="time" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tempat</label>
          <input v-model="form.location" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="cth: Aula Kampus Lt. 2" />
        </div>

        <div v-if="form.type !== 'Luring'">
          <label class="block text-sm font-medium text-gray-700 mb-1">Link Meeting</label>
          <input v-model="form.meeting_link" type="url" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="https://meet.google.com/..." />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
          <textarea v-model="form.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Keterangan tambahan..." />
        </div>
      </div>

      <!-- Peserta -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-3">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Peserta Diundang</h2>

        <!-- Search -->
        <input v-model="inviteeSearch" type="text" placeholder="Cari nama..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />

        <!-- Filter buttons -->
        <div class="flex flex-wrap gap-2">
          <button type="button" :class="['px-2.5 py-1 rounded-full text-xs font-medium border', inviteeFilter === 'all' ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:bg-gray-100']" @click="inviteeFilter = 'all'">Semua</button>
          <button type="button" :class="['px-2.5 py-1 rounded-full text-xs font-medium border', inviteeFilter === 'dosen' ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:bg-gray-100']" @click="inviteeFilter = 'dosen'">Dosen</button>
          <button type="button" :class="['px-2.5 py-1 rounded-full text-xs font-medium border', inviteeFilter === 'mahasiswa' ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:bg-gray-100']" @click="inviteeFilter = 'mahasiswa'">Mahasiswa</button>
          <button type="button" :class="['px-2.5 py-1 rounded-full text-xs font-medium border', inviteeFilter === 'struktural' ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:bg-gray-100']" @click="inviteeFilter = 'struktural'">Struktural</button>
        </div>

        <!-- Filter prodi -->
        <div v-if="inviteeFilter === 'dosen' || inviteeFilter === 'mahasiswa'">
          <select v-model="inviteeProdi" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg text-xs">
            <option value="">Semua Program Studi</option>
            <option v-for="p in prodiList" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>

        <!-- Select all + counter -->
        <div class="flex items-center gap-2">
          <input type="checkbox" id="selectAllInvitees" :checked="isAllFilteredSelected" @change="toggleAll" class="rounded" />
          <label for="selectAllInvitees" class="text-xs text-gray-600 font-medium">Pilih Semua ({{ filteredUsers.length }})</label>
          <span class="text-xs text-gray-400 ml-auto">{{ form.invitee_ids.length }} dipilih</span>
        </div>

        <!-- User list -->
        <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
          <label v-for="u in filteredUsers" :key="u.id" class="flex items-center gap-2 text-sm p-1.5 rounded hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" :value="u.id" v-model="form.invitee_ids" class="rounded" />
            <span class="text-gray-800 flex-1 truncate">{{ u.name }}</span>
            <span v-if="u.role_label" class="text-[10px] text-gray-400">{{ u.role_label }}</span>
          </label>
          <div v-if="!filteredUsers.length" class="text-center text-gray-400 text-xs py-3">Tidak ada user yang cocok.</div>
        </div>
        <p class="text-xs text-gray-400">Opsional — presensi tetap bisa dilakukan siapa saja via QR</p>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3">
        <button type="button" class="px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100 rounded-lg border border-gray-300" @click="router.back()">Batal</button>
        <button type="submit" :disabled="saving" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg">
          {{ saving ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Buat Agenda') }}
        </button>
      </div>
    </form>
  </div>
</template>
