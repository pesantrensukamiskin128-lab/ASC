<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const router = useRouter()
const route  = useRoute()
const toast  = useToast()

const isEdit    = !!route.params.id
const loading   = ref(false)
const periods   = ref<any[]>([])
const lecturers = ref<any[]>([])
const students  = ref<any[]>([])

const form = ref({
  type: 'penelitian',
  title: '',
  period_id: '',
  abstract: '',
  keywords: '',
  proposal_link: '',
  dosen_members: [] as number[],
  mahasiswa_members: [] as number[],
})

onMounted(async () => {
  const [p, l, s] = await Promise.all([
    api.get('/penelitian-periods'),
    api.get('/lecturers/all'),
    api.get('/students/all').catch(() => ({ data: [] })),
  ])
  periods.value   = p.data
  lecturers.value = l.data
  students.value  = Array.isArray(s.data) ? s.data : (s.data?.data ?? [])

  if (isEdit) {
    const { data } = await api.get(`/penelitian/${route.params.id}`)
    form.value.type          = data.type
    form.value.title         = data.title
    form.value.period_id     = data.period_id ?? ''
    form.value.abstract      = data.abstract ?? ''
    form.value.keywords      = data.keywords ?? ''
    form.value.proposal_link = data.proposal_link ?? ''
    form.value.dosen_members = data.members
      ?.filter((m: any) => m.member_type === 'dosen').map((m: any) => m.lecturer_id) ?? []
    form.value.mahasiswa_members = data.members
      ?.filter((m: any) => m.member_type === 'mahasiswa').map((m: any) => m.student_id) ?? []
  }
})

async function submit() {
  if (!form.value.title.trim()) { toast.error('Judul wajib diisi.'); return }
  loading.value = true
  try {
    if (isEdit) {
      await api.put(`/penelitian/${route.params.id}`, form.value)
      toast.success('Proposal diperbarui.')
    } else {
      const { data } = await api.post('/penelitian', form.value)
      toast.success('Proposal berhasil dibuat.')
      router.push(`/penelitian/${data.data.id}`)
      return
    }
    router.push(`/penelitian/${route.params.id}`)
  } catch (e: any) {
    const errs = e?.response?.data?.errors
    if (errs) {
      toast.error(Object.values(errs).flat().join(', '))
    } else {
      toast.error(e?.response?.data?.message ?? 'Gagal menyimpan.')
    }
  } finally { loading.value = false }
}

function toggleMember(arr: number[], id: number) {
  const idx = arr.indexOf(id)
  if (idx === -1) arr.push(id)
  else arr.splice(idx, 1)
}
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <div>
      <h1 class="text-xl font-bold text-gray-900">{{ isEdit ? 'Edit Proposal' : 'Ajukan Proposal Hibah' }}</h1>
      <p class="text-sm text-gray-500 mt-0.5">Isi informasi proposal penelitian atau pengabdian kepada masyarakat</p>
    </div>

    <form class="bg-white rounded-xl border border-gray-200 p-6 space-y-5" @submit.prevent="submit">
      <!-- Jenis -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenis <span class="text-red-500">*</span></label>
        <div class="flex gap-3">
          <label v-for="opt in [{ val: 'penelitian', label: 'Penelitian', icon: '🔬' }, { val: 'pengabdian', label: 'Pengabdian kepada Masyarakat', icon: '🤝' }]"
            :key="opt.val"
            :class="['flex items-center gap-2 px-4 py-2.5 rounded-lg border cursor-pointer transition-colors text-sm',
              form.type === opt.val ? 'border-blue-500 bg-blue-50 text-blue-700 font-medium' : 'border-gray-300 hover:border-gray-400']">
            <input type="radio" v-model="form.type" :value="opt.val" class="hidden" />
            <span>{{ opt.icon }}</span> {{ opt.label }}
          </label>
        </div>
      </div>

      <!-- Judul -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Judul <span class="text-red-500">*</span></label>
        <input v-model="form.title" type="text" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Judul proposal penelitian/pengabdian" />
      </div>

      <!-- Periode -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Periode Hibah <span class="text-red-500">*</span></label>
        <select v-model="form.period_id" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Pilih Periode --</option>
          <option v-for="p in periods.filter(p => p.type === form.type || !form.type)" :key="p.id" :value="p.id">
            {{ p.name }} ({{ p.year }})
          </option>
        </select>
      </div>

      <!-- Abstrak & Kata Kunci -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Abstrak <span class="text-gray-400 font-normal">(opsional)</span></label>
        <textarea v-model="form.abstract" rows="4" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Abstrak singkat penelitian..." />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kata Kunci <span class="text-gray-400 font-normal">(opsional)</span></label>
        <input v-model="form.keywords" type="text" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Pisahkan dengan koma, contoh: pendidikan, teknologi" />
      </div>

      <!-- Link Proposal -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Link Proposal (Google Drive) <span class="text-gray-400 font-normal">(opsional saat draft)</span></label>
        <input v-model="form.proposal_link" type="url" class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="https://drive.google.com/..." />
      </div>

      <!-- Anggota Dosen -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Anggota Dosen <span class="text-gray-400 font-normal">(opsional)</span></label>
        <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
          <label v-for="lec in lecturers" :key="lec.id"
            class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" :checked="form.dosen_members.includes(lec.id)"
              @change="toggleMember(form.dosen_members, lec.id)"
              class="rounded border-gray-300 text-blue-600" />
            <span class="text-sm text-gray-700">{{ lec.full_name }}</span>
          </label>
        </div>
        <p v-if="form.dosen_members.length" class="text-xs text-blue-600 mt-1">{{ form.dosen_members.length }} dosen dipilih</p>
      </div>

      <!-- Anggota Mahasiswa -->
      <div v-if="students.length">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Anggota Mahasiswa <span class="text-gray-400 font-normal">(opsional)</span></label>
        <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
          <label v-for="std in students" :key="std.id"
            class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" :checked="form.mahasiswa_members.includes(std.id)"
              @change="toggleMember(form.mahasiswa_members, std.id)"
              class="rounded border-gray-300 text-blue-600" />
            <span class="text-sm text-gray-700">{{ std.name }} <span class="text-gray-400">({{ std.nim }})</span></span>
          </label>
        </div>
        <p v-if="form.mahasiswa_members.length" class="text-xs text-blue-600 mt-1">{{ form.mahasiswa_members.length }} mahasiswa dipilih</p>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3 pt-2">
        <button type="submit" :disabled="loading"
          class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
          {{ loading ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Simpan sebagai Draft') }}
        </button>
        <button type="button" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg"
          @click="router.back()">Batal</button>
      </div>
    </form>
  </div>
</template>
