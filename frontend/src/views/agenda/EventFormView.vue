<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const router = useRouter()
const toast = useToast()
const saving = ref(false)
const allUsers = ref<any[]>([])

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
  const { data } = await api.get('/user-list')
  allUsers.value = data
})

async function handleSave() {
  if (!form.title.trim()) { toast.error('Nama agenda wajib diisi.'); return }
  saving.value = true
  try {
    await api.post('/events', form)
    toast.success('Agenda berhasil dibuat.')
    router.push('/agenda')
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal membuat agenda.')
  } finally { saving.value = false }
}

function toggleAll() {
  if (form.invitee_ids.length === allUsers.value.length) {
    form.invitee_ids = []
  } else {
    form.invitee_ids = allUsers.value.map((u: any) => u.id)
  }
}
</script>

<template>
  <div class="space-y-5 max-w-3xl">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Buat Agenda Kegiatan</h1>
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
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Peserta Diundang</h2>
          <button type="button" class="text-xs text-blue-600 hover:underline" @click="toggleAll">
            {{ form.invitee_ids.length === allUsers.length ? 'Batal Pilih Semua' : 'Pilih Semua' }}
          </button>
        </div>
        <p class="text-xs text-gray-400">{{ form.invitee_ids.length }} peserta dipilih (opsional — presensi tetap bisa dilakukan siapa saja via QR)</p>
        <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3 space-y-1">
          <label v-for="u in allUsers" :key="u.id" class="flex items-center gap-2 text-sm p-1 rounded hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" :value="u.id" v-model="form.invitee_ids" class="rounded" />
            <span class="text-gray-800">{{ u.name }}</span>
            <span v-if="u.role_label" class="text-[10px] text-gray-400 ml-auto">{{ u.role_label }}</span>
          </label>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3">
        <button type="button" class="px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100 rounded-lg border border-gray-300" @click="router.back()">Batal</button>
        <button type="submit" :disabled="saving" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg">
          {{ saving ? 'Menyimpan...' : 'Buat Agenda' }}
        </button>
      </div>
    </form>
  </div>
</template>
