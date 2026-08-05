<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  UsersIcon, BriefcaseIcon, AcademicCapIcon, ChartBarIcon,
  DocumentCheckIcon, ArrowTrendingUpIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const loading = ref(true)
const stats = ref<any>(null)

onMounted(async () => {
  try {
    const { data } = await api.get('/alumni/dashboard')
    stats.value = data
  } finally { loading.value = false }
})

const colorMap: Record<string, string> = {
  blue: 'bg-blue-50 text-blue-700 border-blue-200',
  green: 'bg-green-50 text-green-700 border-green-200',
  purple: 'bg-purple-50 text-purple-700 border-purple-200',
  orange: 'bg-orange-50 text-orange-700 border-orange-200',
  cyan: 'bg-cyan-50 text-cyan-700 border-cyan-200',
  indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200',
}
const iconColorMap: Record<string, string> = {
  blue: 'text-blue-500', green: 'text-green-500', purple: 'text-purple-500',
  orange: 'text-orange-500', cyan: 'text-cyan-500', indigo: 'text-indigo-500',
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Dashboard Alumni</h1>
        <p class="text-sm text-gray-500 mt-0.5">Ringkasan data alumni dan tracer study</p>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

    <template v-else-if="stats">
      <!-- Stats Cards -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div :class="['rounded-xl border p-4 cursor-pointer hover:shadow-md transition-shadow', colorMap.blue]" @click="router.push('/alumni/data')">
          <UsersIcon :class="['w-5 h-5 mb-2', iconColorMap.blue]" />
          <p class="text-2xl font-bold">{{ stats.total_alumni }}</p>
          <p class="text-xs mt-1 opacity-75">Total Alumni</p>
        </div>
        <div :class="['rounded-xl border p-4', colorMap.indigo]">
          <DocumentCheckIcon :class="['w-5 h-5 mb-2', iconColorMap.indigo]" />
          <p class="text-2xl font-bold">{{ stats.tracer_completed }}</p>
          <p class="text-xs mt-1 opacity-75">Tracer Study</p>
        </div>
        <div :class="['rounded-xl border p-4', colorMap.green]">
          <BriefcaseIcon :class="['w-5 h-5 mb-2', iconColorMap.green]" />
          <p class="text-2xl font-bold">{{ stats.employed }}</p>
          <p class="text-xs mt-1 opacity-75">Bekerja</p>
        </div>
        <div :class="['rounded-xl border p-4', colorMap.orange]">
          <ArrowTrendingUpIcon :class="['w-5 h-5 mb-2', iconColorMap.orange]" />
          <p class="text-2xl font-bold">{{ stats.entrepreneur }}</p>
          <p class="text-xs mt-1 opacity-75">Wirausaha</p>
        </div>
        <div :class="['rounded-xl border p-4', colorMap.purple]">
          <AcademicCapIcon :class="['w-5 h-5 mb-2', iconColorMap.purple]" />
          <p class="text-2xl font-bold">{{ stats.further_study }}</p>
          <p class="text-xs mt-1 opacity-75">Studi Lanjut</p>
        </div>
        <div :class="['rounded-xl border p-4', colorMap.cyan]">
          <ChartBarIcon :class="['w-5 h-5 mb-2', iconColorMap.cyan]" />
          <p class="text-2xl font-bold">{{ stats.tracer_completed > 0 ? Math.round(((stats.employed + stats.entrepreneur) / stats.tracer_completed) * 100) : 0 }}%</p>
          <p class="text-xs mt-1 opacity-75">Terserap Kerja</p>
        </div>
      </div>

      <!-- Alumni Per Tahun -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Alumni Per Tahun Lulus</h2>
        <div v-if="stats.by_year?.length" class="space-y-2">
          <div v-for="item in stats.by_year" :key="item.graduation_year" class="flex items-center gap-3">
            <span class="text-xs text-gray-500 w-12 text-right font-mono">{{ item.graduation_year }}</span>
            <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
              <div
                class="bg-blue-500 h-full rounded-full flex items-center justify-end pr-2 transition-all duration-500"
                :style="{ width: `${Math.max((item.count / (stats.by_year[0]?.count || 1)) * 100, 10)}%` }"
              >
                <span class="text-xs text-white font-medium">{{ item.count }}</span>
              </div>
            </div>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400">Belum ada data.</p>
      </div>

      <!-- Alumni Per Prodi -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Alumni Per Program Studi</h2>
        <div v-if="stats.by_prodi?.length" class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div v-for="item in stats.by_prodi" :key="item.study_program_id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">{{ item.study_program?.name ?? '-' }}</p>
              <p class="text-xs text-gray-500">{{ item.study_program?.code ?? '-' }}</p>
            </div>
            <span class="inline-flex px-2.5 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold shrink-0">
              {{ item.count }}
            </span>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400">Belum ada data.</p>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Aksi Cepat</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <button class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition-colors" @click="router.push('/alumni/data')">
            <UsersIcon class="w-6 h-6 text-blue-600" />
            <span class="text-xs font-medium text-gray-700">Data Alumni</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-purple-50 hover:border-purple-300 transition-colors" @click="router.push('/alumni/tracer-study')">
            <DocumentCheckIcon class="w-6 h-6 text-purple-600" />
            <span class="text-xs font-medium text-gray-700">Tracer Study</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-green-50 hover:border-green-300 transition-colors" @click="router.push('/alumni/data')">
            <BriefcaseIcon class="w-6 h-6 text-green-600" />
            <span class="text-xs font-medium text-gray-700">Karir Alumni</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-4 rounded-lg border border-gray-200 hover:bg-orange-50 hover:border-orange-300 transition-colors" @click="router.push('/alumni/data')">
            <AcademicCapIcon class="w-6 h-6 text-orange-600" />
            <span class="text-xs font-medium text-gray-700">Studi Lanjut</span>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
