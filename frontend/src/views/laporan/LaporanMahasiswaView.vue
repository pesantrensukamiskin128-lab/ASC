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
    const { data: res } = await api.get('/reports/students')
    data.value = res
  } finally { loading.value = false }
})

const statusColors: Record<string, string> = {
  Aktif: 'bg-green-500', Cuti: 'bg-yellow-500', Lulus: 'bg-blue-500',
  Nonaktif: 'bg-slate-500', DO: 'bg-red-500', 'Mengundurkan Diri': 'bg-gray-500',
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div>
        <h1 class="text-xl font-bold text-gray-900">Statistik Mahasiswa</h1>
        <p class="text-sm text-gray-500 mt-0.5">Distribusi dan analisis data mahasiswa</p>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

    <template v-else-if="data">
      <!-- Per Status -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Mahasiswa Per Status</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <div v-for="item in data.by_status" :key="item.status" class="text-center p-3 bg-gray-50 rounded-lg">
            <div :class="['w-3 h-3 rounded-full mx-auto mb-2', statusColors[item.status] ?? 'bg-gray-400']" />
            <p class="text-xl font-bold text-gray-900">{{ item.count }}</p>
            <p class="text-xs text-gray-500">{{ item.status }}</p>
          </div>
        </div>
      </div>

      <!-- Per Gender -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h2 class="text-sm font-semibold text-gray-800 mb-4">Per Jenis Kelamin</h2>
          <div class="flex items-center gap-6 justify-center">
            <div v-for="item in data.by_gender" :key="item.gender" class="text-center">
              <div :class="['w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-lg', item.gender === 'L' ? 'bg-blue-500' : 'bg-pink-500']">
                {{ item.count }}
              </div>
              <p class="text-xs text-gray-500 mt-2">{{ item.gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</p>
            </div>
          </div>
        </div>

        <!-- Per Angkatan -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h2 class="text-sm font-semibold text-gray-800 mb-4">Per Angkatan (10 Terakhir)</h2>
          <div class="space-y-2">
            <div v-for="item in data.by_entry_year" :key="item.entry_year" class="flex items-center gap-3">
              <span class="text-xs text-gray-500 w-10 text-right font-mono">{{ item.entry_year }}</span>
              <div class="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
                <div class="bg-blue-500 h-full rounded-full flex items-center justify-end pr-2 transition-all" :style="{ width: `${Math.max((item.count / (data.by_entry_year[0]?.count || 1)) * 100, 15)}%` }">
                  <span class="text-[10px] text-white font-medium">{{ item.count }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Per Program Studi -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Mahasiswa Per Program Studi</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase"><th class="px-4 py-2">Kode</th><th class="px-4 py-2">Program Studi</th><th class="px-4 py-2 text-center">Jumlah</th></tr></thead>
            <tbody>
              <tr v-for="item in data.by_program" :key="item.study_program_id" class="border-t border-gray-100">
                <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ item.study_program?.code }}</td>
                <td class="px-4 py-2 text-gray-800">{{ item.study_program?.name }}</td>
                <td class="px-4 py-2 text-center"><span class="inline-flex px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">{{ item.count }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Rasio Dosen:Mahasiswa -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Rasio Dosen : Mahasiswa</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase"><th class="px-4 py-2">Kode</th><th class="px-4 py-2">Program Studi</th><th class="px-4 py-2 text-center">Dosen</th><th class="px-4 py-2 text-center">Mahasiswa</th><th class="px-4 py-2 text-center">Rasio</th></tr></thead>
            <tbody>
              <tr v-for="item in data.ratios" :key="item.code" class="border-t border-gray-100">
                <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ item.code }}</td>
                <td class="px-4 py-2 text-gray-800">{{ item.program }}</td>
                <td class="px-4 py-2 text-center text-green-700 font-medium">{{ item.lecturers }}</td>
                <td class="px-4 py-2 text-center text-blue-700 font-medium">{{ item.students }}</td>
                <td class="px-4 py-2 text-center">
                  <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-bold', item.ratio <= 25 ? 'bg-green-100 text-green-700' : item.ratio <= 40 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700']">
                    1 : {{ item.ratio }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="text-xs text-gray-400 mt-3">* Standar ideal rasio dosen:mahasiswa ≤ 1:25 (hijau), 1:26-40 (kuning), >1:40 (merah)</p>
      </div>
    </template>
  </div>
</template>
