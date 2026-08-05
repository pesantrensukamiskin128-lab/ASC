<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeftIcon, PlusIcon, TrashIcon, PaperAirplaneIcon, CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'
import { extractErrorMessage } from '@/composables/useCrud'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()

const krs       = ref<any>(null)
const loading   = ref(false)
const addModal  = ref(false)
const rejectModal = ref(false)
const rejectionNote = ref('')
const submitting = ref(false)
const approving  = ref(false)

// Untuk tambah MK
const availableCourses = ref<any[]>([])
const loadingCourses   = ref(false)

const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600', SUBMITTED: 'bg-yellow-100 text-yellow-700',
  APPROVED: 'bg-green-100 text-green-700', REJECTED: 'bg-red-100 text-red-600',
}

const canEdit = computed(() =>
  krs.value && ['DRAFT', 'REJECTED'].includes(krs.value.status) &&
  (auth.hasRole('MAHASISWA') || auth.hasRole('ADMIN_AKADEMIK') || auth.hasRole('SUPER_ADMIN'))
)
const canApprove = computed(() =>
  krs.value?.status === 'SUBMITTED' &&
  (auth.hasPermission('krs.approve') || auth.hasRole('DOSEN') || auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK'))
)

const canSignKaprodi = computed(() =>
  krs.value?.status === 'APPROVED' &&
  !krs.value?.signed_by_kaprodi_at &&
  (auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK') || auth.hasRole('DOSEN') || auth.hasPermission('krs.approve'))
)

onMounted(load)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/krs/${route.params.id}`)
    krs.value = data
  } catch { toast.error('Gagal memuat KRS.') }
  finally { loading.value = false }
}

async function loadAvailableCourses() {
  loadingCourses.value = true
  try {
    const { data } = await api.get('/courses/all', {
      params: { study_program_id: krs.value?.student?.study_program?.id },
    })
    availableCourses.value = data
  } finally { loadingCourses.value = false }
}

function openAddModal() {
  loadAvailableCourses()
  addModal.value = true
}

function isEnrolled(courseId: number) {
  return krs.value?.details?.some((d: any) => d.course_id === courseId && d.status === 'AKTIF')
}

async function addCourse(courseId: number) {
  try {
    await api.post(`/krs/${krs.value.id}/courses`, { course_id: courseId })
    toast.success('Mata kuliah ditambahkan.')
    load()
  } catch (err: any) { toast.error(extractErrorMessage(err)) }
}

async function removeCourse(detailId: number, name: string) {
  if (!confirm(`Hapus "${name}" dari KRS?`)) return
  try {
    await api.delete(`/krs/${krs.value.id}/courses/${detailId}`)
    toast.success('Mata kuliah dihapus.')
    load()
  } catch (err: any) { toast.error(extractErrorMessage(err)) }
}

async function handleSubmit() {
  if (!confirm('Submit KRS ke dosen wali?')) return
  submitting.value = true
  try {
    await api.post(`/krs/${krs.value.id}/submit`)
    toast.success('KRS berhasil disubmit.')
    load()
  } catch (err: any) { toast.error(extractErrorMessage(err)) }
  finally { submitting.value = false }
}

async function handleApprove() {
  if (!confirm('Setujui KRS ini?')) return
  approving.value = true
  try {
    await api.post(`/krs/${krs.value.id}/approve`)
    toast.success('KRS disetujui.')
    load()
  } catch (err: any) { toast.error(extractErrorMessage(err)) }
  finally { approving.value = false }
}

async function handleReject() {
  if (!rejectionNote.value.trim()) { toast.warning('Catatan wajib diisi.'); return }
  try {
    await api.post(`/krs/${krs.value.id}/reject`, { note: rejectionNote.value })
    toast.success('KRS ditolak.')
    rejectModal.value = false; rejectionNote.value = ''
    load()
  } catch (err: any) { toast.error(extractErrorMessage(err)) }
}

async function handleSignKaprodi() {
  if (!confirm('Tandatangani KRS ini sebagai Kaprodi?')) return
  try {
    await api.post(`/krs/${krs.value.id}/sign-kaprodi`)
    toast.success('KRS berhasil ditandatangani Kaprodi.')
    load()
  } catch (err: any) { toast.error(err?.response?.data?.message ?? 'Gagal.') }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 mt-0.5" @click="router.back()">
        <ArrowLeftIcon class="w-5 h-5" />
      </button>
      <div class="flex-1">
        <div class="flex items-center gap-3 flex-wrap">
          <h1 class="text-xl font-bold text-gray-900">KRS — {{ krs?.student?.name }}</h1>
          <span v-if="krs" :class="['px-2.5 py-1 rounded-full text-xs font-medium', statusColor[krs.status]]">{{ krs.status }}</span>
        </div>
        <p class="text-sm text-gray-500 mt-0.5">{{ krs?.student?.nim }} · {{ krs?.student?.study_program?.name }} · {{ krs?.semester?.name }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button v-if="canEdit" class="flex items-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openAddModal">
          <PlusIcon class="w-4 h-4" /> Tambah MK
        </button>
        <button v-if="canEdit && krs?.details?.length" :disabled="submitting" class="flex items-center gap-1.5 px-3 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium rounded-lg" @click="handleSubmit">
          <PaperAirplaneIcon class="w-4 h-4" /> {{ submitting ? 'Submitting...' : 'Submit KRS' }}
        </button>
        <template v-if="canApprove">
          <button :disabled="approving" class="flex items-center gap-1.5 px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg" @click="handleApprove">
            <CheckIcon class="w-4 h-4" /> Setujui
          </button>
          <button class="flex items-center gap-1.5 px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg" @click="rejectModal = true">
            <XMarkIcon class="w-4 h-4" /> Tolak
          </button>
        </template>
        <button v-if="canSignKaprodi" class="flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg" @click="handleSignKaprodi">
          ✍ Tandatangan Kaprodi
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">Memuat KRS...</div>

    <template v-else-if="krs">
      <!-- Info cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
          <p class="text-3xl font-bold text-blue-600">{{ krs.total_credits }}</p>
          <p class="text-xs text-gray-500 mt-1">Total SKS</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
          <p class="text-3xl font-bold text-gray-700">{{ krs.details?.filter((d: any) => d.status === 'AKTIF').length ?? 0 }}</p>
          <p class="text-xs text-gray-500 mt-1">Mata Kuliah</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
          <p class="text-sm font-semibold text-gray-700 mt-1">{{ krs.advisor?.full_name ?? krs.advisor?.name ?? '-' }}</p>
          <p class="text-xs text-gray-500 mt-1">Dosen Wali</p>
        </div>
        <div v-if="krs.advisor_note" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
          <p class="text-xs font-semibold text-amber-700">Catatan Dosen Wali:</p>
          <p class="text-sm text-amber-800 mt-1">{{ krs.advisor_note }}</p>
        </div>
      </div>

      <!-- Daftar MK -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
          <h2 class="font-semibold text-gray-900">Mata Kuliah yang Diambil</h2>
        </div>
        <div v-if="!krs.details?.length" class="text-center py-10 text-gray-400 text-sm">Belum ada mata kuliah.</div>
        <div v-else class="divide-y divide-gray-100">
          <div v-for="d in krs.details" :key="d.id"
            :class="['flex items-center gap-4 px-5 py-3 group', d.status === 'DIBATALKAN' ? 'opacity-50' : 'hover:bg-gray-50']">
            <div class="flex-1 min-w-0">
              <p class="font-medium text-gray-900 text-sm">{{ d.course?.name }}</p>
              <p class="text-xs text-gray-500 mt-0.5">
                {{ d.course?.code }}
                <template v-if="d.class_">· {{ d.class_.name }} · {{ d.class_.lecturer?.full_name ?? d.class_.lecturer?.name }}</template>
              </p>
            </div>
            <span class="text-sm text-gray-600 shrink-0">{{ d.course?.credits }} SKS</span>
            <button v-if="canEdit && d.status === 'AKTIF'"
              class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 opacity-0 group-hover:opacity-100 shrink-0"
              @click="removeCourse(d.id, d.course?.name)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
          <p class="text-sm text-gray-500">Batas: <span class="font-semibold">24 SKS</span></p>
          <p class="text-sm font-semibold text-gray-900">Total: {{ krs.total_credits }} SKS</p>
        </div>
      </div>
    </template>
  </div>

  <!-- Modal Tambah MK -->
  <BaseModal :open="addModal" title="Tambah Mata Kuliah" size="xl" @close="addModal = false">
    <div v-if="loadingCourses" class="text-center py-6 text-gray-400 text-sm">Memuat...</div>
    <div v-else-if="!availableCourses.length" class="text-center py-6 text-gray-400 text-sm">Tidak ada MK tersedia.</div>
    <div v-else class="max-h-96 overflow-y-auto divide-y divide-gray-100 border border-gray-200 rounded-lg">
      <div v-for="c in availableCourses" :key="c.id" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50">
        <div class="flex-1 min-w-0">
          <p class="font-medium text-gray-900 text-sm">{{ c.name }}</p>
          <p class="text-xs text-gray-500">{{ c.code }} · {{ c.credits }} SKS · Smt {{ c.semester }}</p>
        </div>
        <button :disabled="isEnrolled(c.id)"
          :class="['px-3 py-1.5 rounded-lg text-xs font-medium shrink-0',
            isEnrolled(c.id) ? 'bg-gray-100 text-gray-400' : 'bg-blue-600 hover:bg-blue-700 text-white']"
          @click="addCourse(c.id)">
          {{ isEnrolled(c.id) ? 'Terdaftar' : 'Pilih' }}
        </button>
      </div>
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="addModal = false">Selesai</button>
    </template>
  </BaseModal>

  <!-- Modal Tolak -->
  <BaseModal :open="rejectModal" title="Tolak KRS" @close="rejectModal = false">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Catatan <span class="text-red-500">*</span></label>
      <textarea v-model="rejectionNote" rows="4" placeholder="Alasan penolakan..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="rejectModal = false">Batal</button>
      <button class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg" @click="handleReject">Tolak</button>
    </template>
  </BaseModal>
</template>
