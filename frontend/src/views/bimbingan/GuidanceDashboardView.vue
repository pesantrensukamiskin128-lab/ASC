<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  UsersIcon, ExclamationTriangleIcon, ChatBubbleLeftRightIcon,
  CheckCircleIcon, ClockIcon, PlusIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const auth = useAuthStore()
const loading = ref(true)
const dashboard = ref<any>(null)
const isLecturer = auth.user?.roles?.includes('DOSEN') || auth.user?.roles?.includes('SUPER_ADMIN')

onMounted(async () => {
  try {
    if (isLecturer) {
      const { data } = await api.get('/guidance/advisor-dashboard')
      dashboard.value = data
    }
  } finally { loading.value = false }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Bimbingan Akademik</h1>
        <p class="text-sm text-gray-500 mt-0.5">Perwalian dan bimbingan mahasiswa</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="router.push('/bimbingan/sessions?action=new')">
        <PlusIcon class="w-4 h-4" /> {{ isLecturer ? 'Buat Sesi' : 'Ajukan Bimbingan' }}
      </button>
    </div>

    <!-- Dosen Dashboard -->
    <div v-if="isLecturer && dashboard" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 cursor-pointer hover:shadow-md" @click="router.push('/bimbingan/mahasiswa')">
        <UsersIcon class="w-5 h-5 text-blue-500 mb-2" />
        <p class="text-2xl font-bold text-blue-700">{{ dashboard.total_students }}</p>
        <p class="text-xs text-blue-600">Mahasiswa Bimbingan</p>
      </div>
      <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 cursor-pointer hover:shadow-md" @click="router.push('/bimbingan/sessions?status=DIAJUKAN')">
        <ClockIcon class="w-5 h-5 text-yellow-500 mb-2" />
        <p class="text-2xl font-bold text-yellow-700">{{ dashboard.pending_sessions }}</p>
        <p class="text-xs text-yellow-600">Pengajuan Baru</p>
      </div>
      <div class="rounded-xl border border-red-200 bg-red-50 p-4 cursor-pointer hover:shadow-md" @click="router.push('/bimbingan/warnings')">
        <ExclamationTriangleIcon class="w-5 h-5 text-red-500 mb-2" />
        <p class="text-2xl font-bold text-red-700">{{ dashboard.active_warnings }}</p>
        <p class="text-xs text-red-600">Peringatan Aktif</p>
      </div>
      <div class="rounded-xl border border-green-200 bg-green-50 p-4">
        <CheckCircleIcon class="w-5 h-5 text-green-500 mb-2" />
        <p class="text-2xl font-bold text-green-700">{{ dashboard.total_sessions }}</p>
        <p class="text-xs text-green-600">Sesi Selesai</p>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-4">Menu Bimbingan</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <button class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition-colors" @click="router.push('/bimbingan/sessions')">
          <ChatBubbleLeftRightIcon class="w-6 h-6 text-blue-600" />
          <span class="text-xs font-medium text-gray-700 text-center">Sesi Bimbingan</span>
        </button>
        <button v-if="isLecturer" class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-purple-50 hover:border-purple-300 transition-colors" @click="router.push('/bimbingan/mahasiswa')">
          <UsersIcon class="w-6 h-6 text-purple-600" />
          <span class="text-xs font-medium text-gray-700 text-center">Mahasiswa Saya</span>
        </button>
        <button class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-orange-50 hover:border-orange-300 transition-colors" @click="router.push('/bimbingan/catatan')">
          <CheckCircleIcon class="w-6 h-6 text-orange-600" />
          <span class="text-xs font-medium text-gray-700 text-center">Catatan Akademik</span>
        </button>
        <button class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-red-50 hover:border-red-300 transition-colors" @click="router.push('/bimbingan/warnings')">
          <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
          <span class="text-xs font-medium text-gray-700 text-center">Peringatan Akademik</span>
        </button>
      </div>
    </div>

    <!-- Mahasiswa bimbingan (preview) -->
    <div v-if="isLecturer && dashboard?.students?.length" class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-800">Mahasiswa Bimbingan</h2>
        <button class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="router.push('/bimbingan/mahasiswa')">Lihat Semua →</button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <div v-for="s in dashboard.students.slice(0, 6)" :key="s.id" class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
          <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0">
            {{ s.name?.charAt(0) }}
          </div>
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ s.name }}</p>
            <p class="text-xs text-gray-500">{{ s.nim }} · {{ s.study_program?.code ?? '' }} · Sem {{ s.current_semester }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
