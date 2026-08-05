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
    const { data: res } = await api.get('/reports/academic')
    data.value = res
  } finally { loading.value = false }
})

const gradeColors: Record<string, string> = {
  'A': 'bg-green-500', 'A-': 'bg-green-400', 'B+': 'bg-blue-500', 'B': 'bg-blue-400',
  'B-': 'bg-blue-300', 'C+': 'bg-yellow-500', 'C': 'bg-yellow-400', 'D': 'bg-orange-500', 'E': 'bg-red-500',
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div>
        <h1 class="text-xl font-bold text-gray-900">Statistik Akademik</h1>
        <p class="text-sm text-gray-500 mt-0.5">Analisis nilai, IPK, dan kelulusan</p>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

    <template v-else-if="data">
      <!-- Distribusi Huruf Mutu -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Distribusi Huruf Mutu</h2>
        <div class="flex items-end gap-2 h-40">
          <div v-for="item in data.grade_distribution" :key="item.letter_grade" class="flex-1 flex flex-col items-center justify-end">
            <span class="text-xs font-bold text-gray-700 mb-1">{{ item.count }}</span>
            <div :class="['w-full rounded-t-lg transition-all', gradeColors[item.letter_grade] ?? 'bg-gray-400']" :style="{ height: `${Math.max((item.count / Math.max(...data.grade_distribution.map((d: any) => d.count))) * 100, 5)}%` }" />
            <span class="text-xs text-gray-600 mt-1 font-medium">{{ item.letter_grade }}</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Distribusi IPK -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h2 class="text-sm font-semibold text-gray-800 mb-4">Distribusi Range IPK</h2>
          <div class="space-y-3">
            <div v-for="item in data.gpa_distribution" :key="item.grade_range" class="flex items-center gap-3">
              <span class="text-xs text-gray-500 w-24 text-right">{{ item.grade_range }}</span>
              <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                <div class="bg-indigo-500 h-full rounded-full flex items-center justify-end pr-2" :style="{ width: `${Math.max((item.count / Math.max(...data.gpa_distribution.map((d: any) => d.count))) * 100, 10)}%` }">
                  <span class="text-[10px] text-white font-medium">{{ item.count }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Kelulusan Per Tahun -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h2 class="text-sm font-semibold text-gray-800 mb-4">Kelulusan Per Tahun</h2>
          <div class="space-y-2">
            <div v-for="item in data.graduation_by_year" :key="item.graduation_year" class="flex items-center gap-3">
              <span class="text-xs text-gray-500 w-10 text-right font-mono">{{ item.graduation_year }}</span>
              <div class="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
                <div class="bg-green-500 h-full rounded-full flex items-center justify-end pr-2" :style="{ width: `${Math.max((item.count / Math.max(...data.graduation_by_year.map((d: any) => d.count))) * 100, 15)}%` }">
                  <span class="text-[10px] text-white font-medium">{{ item.count }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Rata-rata IPK per Prodi -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Rata-rata IPK Per Program Studi</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase"><th class="px-4 py-2">Kode</th><th class="px-4 py-2">Program Studi</th><th class="px-4 py-2 text-center">Rata-rata IPK</th><th class="px-4 py-2">Visualisasi</th></tr></thead>
            <tbody>
              <tr v-for="item in data.avg_gpa_by_program" :key="item.code" class="border-t border-gray-100">
                <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ item.code }}</td>
                <td class="px-4 py-2 text-gray-800">{{ item.name }}</td>
                <td class="px-4 py-2 text-center">
                  <span :class="['inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold', Number(item.avg_gpa) >= 3.5 ? 'bg-green-100 text-green-700' : Number(item.avg_gpa) >= 3.0 ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700']">
                    {{ Number(item.avg_gpa).toFixed(2) }}
                  </span>
                </td>
                <td class="px-4 py-2">
                  <div class="w-full bg-gray-100 rounded-full h-2.5">
                    <div class="bg-indigo-500 h-2.5 rounded-full" :style="{ width: `${(Number(item.avg_gpa) / 4) * 100}%` }" />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
