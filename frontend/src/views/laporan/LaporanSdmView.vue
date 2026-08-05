<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const loading = ref(true)
const data = ref<any>(null)

onMounted(async () => {
  try {
    const { data: res } = await api.get('/reports/human-resources')
    data.value = res
  } finally { loading.value = false }
})

const rankColors: Record<string, string> = {
  'Guru Besar': 'bg-purple-500', 'Lektor Kepala': 'bg-blue-500',
  'Lektor': 'bg-green-500', 'Asisten Ahli': 'bg-yellow-500', 'Tenaga Pengajar': 'bg-gray-400',
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div>
        <h1 class="text-xl font-bold text-gray-900">Statistik SDM</h1>
        <p class="text-sm text-gray-500 mt-0.5">Analisis data dosen dan tenaga kependidikan</p>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

    <template v-else-if="data">
      <!-- Summary -->
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
          <p class="text-3xl font-bold text-blue-700">{{ data.total_lecturers }}</p>
          <p class="text-xs text-gray-500 mt-1">Total Dosen Aktif</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
          <p class="text-3xl font-bold text-green-700">{{ data.total_with_nidn }}</p>
          <p class="text-xs text-gray-500 mt-1">Dosen Ber-NIDN</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
          <p class="text-3xl font-bold text-orange-700">{{ data.total_lecturers - data.total_with_nidn }}</p>
          <p class="text-xs text-gray-500 mt-1">Tanpa NIDN</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Per Jabatan Akademik -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h2 class="text-sm font-semibold text-gray-800 mb-4">Per Jabatan Akademik</h2>
          <div class="space-y-3">
            <div v-for="item in data.by_rank" :key="item.academic_rank" class="flex items-center gap-3">
              <div :class="['w-3 h-3 rounded-full shrink-0', rankColors[item.academic_rank] ?? 'bg-gray-400']" />
              <span class="text-sm text-gray-700 flex-1">{{ item.academic_rank }}</span>
              <span class="text-sm font-bold text-gray-900">{{ item.count }}</span>
            </div>
          </div>
        </div>

        <!-- Per Status Kepegawaian -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h2 class="text-sm font-semibold text-gray-800 mb-4">Per Status Kepegawaian</h2>
          <div class="space-y-3">
            <div v-for="item in data.by_employment" :key="item.employment_status" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <span class="text-sm text-gray-700">{{ item.employment_status }}</span>
              <span class="inline-flex px-2.5 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">{{ item.count }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Per Gender -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Per Jenis Kelamin</h2>
        <div class="flex items-center gap-8 justify-center">
          <div v-for="item in data.by_gender" :key="item.gender" class="text-center">
            <div :class="['w-20 h-20 rounded-full flex items-center justify-center text-white font-bold text-xl', item.gender === 'L' ? 'bg-blue-500' : 'bg-pink-500']">
              {{ item.count }}
            </div>
            <p class="text-sm text-gray-600 mt-2 font-medium">{{ item.gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</p>
            <p class="text-xs text-gray-400">{{ data.total_lecturers > 0 ? Math.round((item.count / data.total_lecturers) * 100) : 0 }}%</p>
          </div>
        </div>
      </div>

      <!-- Per Program Studi -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Dosen Per Program Studi</h2>
        <div class="space-y-2">
          <div v-for="item in data.by_program" :key="item.study_program_id" class="flex items-center gap-3">
            <span class="text-xs text-gray-500 w-32 text-right truncate">{{ item.study_program?.name ?? '-' }}</span>
            <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
              <div class="bg-purple-500 h-full rounded-full flex items-center justify-end pr-2 transition-all" :style="{ width: `${Math.max((item.count / Math.max(...data.by_program.map((d: any) => d.count))) * 100, 10)}%` }">
                <span class="text-[10px] text-white font-medium">{{ item.count }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
