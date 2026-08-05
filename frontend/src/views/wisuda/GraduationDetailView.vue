<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { ArrowLeftIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()
const loading = ref(true)
const data = ref<any>(null)
const isAdmin = auth.user?.roles?.includes('SUPER_ADMIN') || auth.user?.roles?.includes('ADMIN_AKADEMIK')

const statusColor: Record<string, string> = {
  SUBMITTED: 'bg-blue-100 text-blue-700', VERIFIKASI_AKADEMIK: 'bg-indigo-100 text-indigo-700',
  VERIFIKASI_KEUANGAN: 'bg-yellow-100 text-yellow-700', VERIFIKASI_PERPUSTAKAAN: 'bg-purple-100 text-purple-700',
  APPROVED: 'bg-green-100 text-green-700', REJECTED: 'bg-red-100 text-red-600', WISUDA: 'bg-emerald-100 text-emerald-700',
}

onMounted(async () => {
  try { const { data: res } = await api.get(`/graduation/registrations/${route.params.id}`); data.value = res }
  finally { loading.value = false }
})

async function reload() { const { data: res } = await api.get(`/graduation/registrations/${route.params.id}`); data.value = res }

const verificationProgress = computed(() => {
  if (!data.value?.verifications?.length) return 0
  const fulfilled = data.value.verifications.filter((v: any) => v.is_fulfilled).length
  return Math.round((fulfilled / data.value.verifications.length) * 100)
})

async function toggleVerification(v: any) {
  try {
    await api.post(`/graduation/verifications/${v.id}`, { is_fulfilled: !v.is_fulfilled })
    toast.success('Verifikasi diupdate.'); reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function autoVerifyFinance() {
  try {
    const { data: res } = await api.post(`/graduation/registrations/${data.value.id}/auto-verify-finance`)
    toast.success(res.message); reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function updateStatus(status: string) {
  if (!confirm(`Ubah status ke ${status.replace(/_/g, ' ')}?`)) return
  try { await api.post(`/graduation/registrations/${data.value.id}/status`, { status }); toast.success('Status diupdate.'); reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function setPredicate() {
  const predicate = prompt('Predikat (Cum Laude / Sangat Memuaskan / Memuaskan):')
  if (!predicate) return
  try { await api.post(`/graduation/registrations/${data.value.id}/predicate`, { predicate }); toast.success('Predikat ditetapkan.'); reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-' }
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="data" class="space-y-6 max-w-3xl mx-auto">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div class="flex-1">
        <h1 class="text-xl font-bold text-gray-900">Pendaftaran Wisuda</h1>
        <p class="text-sm text-gray-500">{{ data.student?.name }} · {{ data.student?.nim }}</p>
      </div>
      <span :class="['px-3 py-1 rounded-full text-sm font-medium', statusColor[data.status]]">{{ data.status.replace(/_/g, ' ') }}</span>
    </div>

    <!-- Info -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
      <div><span class="text-xs text-gray-400">Periode</span><p class="text-gray-800 font-medium">{{ data.period?.name }}</p></div>
      <div><span class="text-xs text-gray-400">IPK</span><p class="text-xl font-bold text-blue-700">{{ data.gpa ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">Total SKS</span><p class="text-gray-800 font-bold">{{ data.total_credits ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">Predikat</span><p class="text-gray-800 font-medium">{{ data.predicate ?? '-' }}</p></div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 grid grid-cols-2 gap-4 text-sm">
      <div><span class="text-xs text-gray-400">Judul Skripsi</span><p class="text-gray-800">{{ data.thesis_title ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">Program Studi</span><p class="text-gray-800">{{ data.student?.study_program?.name }}</p></div>
      <div><span class="text-xs text-gray-400">Ukuran Toga</span><p class="text-gray-800">{{ data.toga_size ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">No. HP</span><p class="text-gray-800">{{ data.phone ?? '-' }}</p></div>
    </div>

    <!-- Verifikasi Checklist -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-800">Verifikasi Syarat Wisuda</h2>
        <span class="text-xs font-bold" :class="verificationProgress === 100 ? 'text-green-600' : 'text-gray-500'">{{ verificationProgress }}%</span>
      </div>
      <!-- Progress bar -->
      <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
        <div class="bg-green-500 h-2 rounded-full transition-all" :style="{ width: verificationProgress + '%' }" />
      </div>
      <div class="space-y-2">
        <div v-for="v in data.verifications" :key="v.id" class="flex items-center gap-3 p-3 rounded-lg" :class="v.is_fulfilled ? 'bg-green-50' : 'bg-gray-50'">
          <button v-if="isAdmin" :class="['w-6 h-6 rounded-full border-2 flex items-center justify-center shrink-0 transition-colors', v.is_fulfilled ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-blue-400']" @click="toggleVerification(v)">
            <CheckCircleIcon v-if="v.is_fulfilled" class="w-4 h-4" />
          </button>
          <CheckCircleIcon v-else-if="v.is_fulfilled" class="w-5 h-5 text-green-500 shrink-0" />
          <XCircleIcon v-else class="w-5 h-5 text-gray-300 shrink-0" />
          <div class="flex-1">
            <p :class="['text-sm', v.is_fulfilled ? 'text-green-800' : 'text-gray-700']">{{ v.requirement }}</p>
            <p class="text-xs text-gray-400">{{ v.category }}</p>
          </div>
          <span v-if="v.verified_at" class="text-xs text-gray-400">{{ formatDate(v.verified_at) }}</span>
        </div>
      </div>
      <button v-if="isAdmin" class="mt-3 text-xs text-blue-600 hover:text-blue-700 font-medium" @click="autoVerifyFinance">⚡ Auto-Verifikasi Keuangan</button>
    </div>

    <!-- Admin Actions -->
    <div v-if="isAdmin" class="flex flex-wrap gap-2">
      <button v-if="data.status === 'APPROVED'" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg" @click="updateStatus('WISUDA')">✓ Tetapkan Wisuda</button>
      <button v-if="['SUBMITTED','VERIFIKASI_AKADEMIK','VERIFIKASI_KEUANGAN'].includes(data.status)" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg" @click="updateStatus('REJECTED')">✗ Tolak</button>
      <button v-if="!data.predicate && data.status === 'APPROVED'" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm rounded-lg" @click="setPredicate">🎓 Set Predikat</button>
    </div>
  </div>
</template>
