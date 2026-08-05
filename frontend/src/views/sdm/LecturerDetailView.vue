<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ArrowLeftIcon, PlusIcon, TrashIcon, BriefcaseIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route   = useRoute()
const router  = useRouter()
const toast   = useToast()
const loading = ref(true)
const data    = ref<any>(null)

// Jabatan
const positions = ref<any[]>([])
const availablePositions = ref<Record<string, string>>({})
const posModalOpen = ref(false)
const savingPos = ref(false)
const programs = ref<any[]>([])
const faculties = ref<any[]>([])

const posForm = ref({
  position_code: '',
  scope_type: '',
  scope_id: '',
  start_date: '',
  end_date: '',
  decree_number: '',
  is_active: true,
})

// Jabatan yang punya scope (prodi/fakultas)
const scopedPositions = ['KAPRODI', 'SEKPRODI', 'DEKAN', 'WADEK1', 'WADEK2', 'WADEK3']

onMounted(async () => {
  try {
    const [lecRes, posRes, availRes, progRes, facRes] = await Promise.all([
      api.get(`/lecturers/${route.params.id}`),
      api.get(`/lecturers/${route.params.id}/positions`),
      api.get('/lecturer-positions/available'),
      api.get('/study-programs/all'),
      api.get('/faculties/all'),
    ])
    data.value = lecRes.data
    positions.value = posRes.data
    availablePositions.value = availRes.data
    programs.value = progRes.data
    faculties.value = facRes.data
  } finally { loading.value = false }
})

function openAddPosition() {
  posForm.value = { position_code: '', scope_type: '', scope_id: '', start_date: '', end_date: '', decree_number: '', is_active: true }
  posModalOpen.value = true
}

function needsScope(code: string): boolean {
  return scopedPositions.includes(code)
}

function getScopeType(code: string): string {
  if (['KAPRODI', 'SEKPRODI'].includes(code)) return 'study_program'
  if (['DEKAN', 'WADEK1', 'WADEK2', 'WADEK3'].includes(code)) return 'faculty'
  return ''
}

function onPositionCodeChange() {
  const code = posForm.value.position_code
  posForm.value.scope_type = getScopeType(code)
  posForm.value.scope_id = ''
}

async function savePosition() {
  savingPos.value = true
  try {
    const payload: any = { ...posForm.value }
    if (!payload.scope_type) { delete payload.scope_type; delete payload.scope_id }
    await api.post(`/lecturers/${route.params.id}/positions`, payload)
    toast.success('Jabatan berhasil ditambahkan.')
    posModalOpen.value = false
    const { data: posData } = await api.get(`/lecturers/${route.params.id}/positions`)
    positions.value = posData
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { savingPos.value = false }
}

async function deletePosition(posId: number) {
  if (!confirm('Hapus jabatan ini?')) return
  try {
    await api.delete(`/lecturers/${route.params.id}/positions/${posId}`)
    toast.success('Jabatan berhasil dihapus.')
    positions.value = positions.value.filter(p => p.id !== posId)
  } catch { toast.error('Gagal menghapus.') }
}

function displayName(): string {
  if (!data.value) return ''
  return [data.value.degree_front, data.value.full_name, data.value.degree_back].filter(Boolean).join(' ')
}
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

  <div v-else-if="data" class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()">
        <ArrowLeftIcon class="w-5 h-5 text-gray-500" />
      </button>
      <div>
        <h1 class="text-xl font-bold text-gray-900">{{ displayName() }}</h1>
        <p class="text-sm text-gray-500">NIDN: {{ data.nidn ?? '-' }} · {{ data.study_program?.name ?? '-' }}</p>
      </div>
    </div>

    <!-- Info Dosen -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-3">Informasi Dosen</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
        <div><span class="text-gray-400 text-xs">NIDN</span><p class="text-gray-800 font-mono">{{ data.nidn ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">NUPTK</span><p class="text-gray-800 font-mono">{{ data.nuptk ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">NIP</span><p class="text-gray-800 font-mono">{{ data.nip ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Program Studi</span><p class="text-gray-800">{{ data.study_program?.name ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Jabatan Akademik</span><p class="text-gray-800">{{ data.academic_rank ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Status</span><p class="text-gray-800">{{ data.employment_status ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Email</span><p class="text-gray-800">{{ data.email ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Telepon</span><p class="text-gray-800">{{ data.phone ?? '-' }}</p></div>
      </div>
    </div>

    <!-- Jabatan Struktural -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide flex items-center gap-2">
            <BriefcaseIcon class="w-4 h-4" /> Jabatan Struktural
          </h2>
          <p class="text-xs text-gray-500 mt-0.5">Jabatan yang diemban oleh dosen ini (bisa lebih dari satu)</p>
        </div>
        <button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openAddPosition">
          <PlusIcon class="w-3.5 h-3.5" /> Tambah Jabatan
        </button>
      </div>

      <div v-if="!positions.length" class="text-center py-6 text-gray-400 text-sm">
        Belum ada jabatan struktural. Klik "Tambah Jabatan" untuk menambahkan.
      </div>

      <div v-else class="space-y-2">
        <div v-for="pos in positions" :key="pos.id"
          :class="['flex items-center gap-3 p-3 rounded-lg border group', pos.is_active ? 'border-green-200 bg-green-50/50' : 'border-gray-200 bg-gray-50']">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <p class="text-sm font-medium text-gray-900">{{ pos.position_name }}</p>
              <span :class="['px-1.5 py-0.5 rounded text-[10px] font-medium', pos.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500']">
                {{ pos.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </div>
            <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
              <span v-if="pos.scope_type === 'study_program'">Prodi: {{ programs.find((p: any) => p.id === pos.scope_id)?.name ?? pos.scope_id }}</span>
              <span v-if="pos.scope_type === 'faculty'">Fakultas: {{ faculties.find((f: any) => f.id === pos.scope_id)?.name ?? pos.scope_id }}</span>
              <span v-if="pos.decree_number">SK: {{ pos.decree_number }}</span>
              <span v-if="pos.start_date">Sejak: {{ new Date(pos.start_date).toLocaleDateString('id-ID') }}</span>
            </div>
          </div>
          <button class="opacity-0 group-hover:opacity-100 p-1.5 text-red-400 hover:text-red-600 transition-opacity" @click="deletePosition(pos.id)">
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Tambah Jabatan -->
  <BaseModal :open="posModalOpen" title="Tambah Jabatan Struktural" @close="posModalOpen = false">
    <form class="space-y-4" @submit.prevent="savePosition">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan <span class="text-red-500">*</span></label>
        <select v-model="posForm.position_code" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="onPositionCodeChange">
          <option value="">-- Pilih Jabatan --</option>
          <option v-for="(name, code) in availablePositions" :key="code" :value="code">{{ name }}</option>
        </select>
      </div>

      <!-- Scope: Program Studi (untuk Kaprodi/Sekprodi) -->
      <div v-if="posForm.scope_type === 'study_program'">
        <label class="block text-sm font-medium text-gray-700 mb-1">Program Studi <span class="text-red-500">*</span></label>
        <select v-model="posForm.scope_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Pilih Prodi --</option>
          <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
        </select>
        <p class="text-xs text-gray-400 mt-1">Jabatan ini berlaku untuk program studi yang dipilih.</p>
      </div>

      <!-- Scope: Fakultas (untuk Dekan/Wadek) -->
      <div v-if="posForm.scope_type === 'faculty'">
        <label class="block text-sm font-medium text-gray-700 mb-1">Fakultas <span class="text-red-500">*</span></label>
        <select v-model="posForm.scope_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Pilih Fakultas --</option>
          <option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option>
        </select>
        <p class="text-xs text-gray-400 mt-1">Jabatan ini berlaku untuk fakultas yang dipilih.</p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
          <input v-model="posForm.start_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
          <input v-model="posForm.end_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor SK</label>
        <input v-model="posForm.decree_number" placeholder="Opsional" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="posModalOpen = false">Batal</button>
      <button :disabled="savingPos" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="savePosition">
        {{ savingPos ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </template>
  </BaseModal>
</template>
