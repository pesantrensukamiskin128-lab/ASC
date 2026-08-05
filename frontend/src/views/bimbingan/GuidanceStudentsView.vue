<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ChatBubbleLeftRightIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const toast = useToast()
const loading = ref(true)
const students = ref<any[]>([])
const search = ref('')

onMounted(() => load())

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/guidance/my-students', { params: { search: search.value } })
    students.value = data
  } finally { loading.value = false }
}
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Mahasiswa Bimbingan Saya</h1>
      <p class="text-sm text-gray-500 mt-0.5">Daftar mahasiswa yang menjadi tanggung jawab perwalian Anda</p>
    </div>

    <input v-model="search" type="text" placeholder="Cari NIM atau nama..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64" @input="load" />

    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else-if="!students.length" class="text-center text-gray-400 py-12">Belum ada mahasiswa bimbingan.</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="s in students" :key="s.id" class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm shrink-0">
            {{ s.name?.charAt(0) }}
          </div>
          <div class="min-w-0">
            <p class="font-semibold text-gray-900 text-sm truncate">{{ s.name }}</p>
            <p class="text-xs text-gray-500">{{ s.nim }} · {{ s.study_program?.code ?? '' }}</p>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center mb-3">
          <div class="bg-gray-50 rounded-lg p-2">
            <p class="text-xs text-gray-400">Semester</p>
            <p class="font-bold text-gray-800">{{ s.current_semester }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-2">
            <p class="text-xs text-gray-400">Status</p>
            <p class="font-bold text-sm" :class="s.status === 'Aktif' ? 'text-green-600' : 'text-red-600'">{{ s.status }}</p>
          </div>
          <div class="bg-gray-50 rounded-lg p-2">
            <p class="text-xs text-gray-400">Masuk</p>
            <p class="font-bold text-gray-800 text-sm">{{ s.entry_year }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
          <button class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg" @click="router.push(`/bimbingan/sessions?action=new`)">
            <ChatBubbleLeftRightIcon class="w-3.5 h-3.5" /> Bimbingan
          </button>
          <button class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-medium text-orange-600 bg-orange-50 hover:bg-orange-100 rounded-lg" @click="router.push(`/bimbingan/catatan?student_id=${s.id}`)">
            Catatan
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
