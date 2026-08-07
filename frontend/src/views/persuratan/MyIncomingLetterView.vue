<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { EyeIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const items = ref<any[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/my-incoming-letters')
    items.value = data.data ?? data
  } catch {} finally { loading.value = false }
})
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Surat Masuk</h1>
      <p class="text-sm text-gray-500 mt-0.5">Surat yang ditujukan kepada Anda</p>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else-if="!items.length" class="text-center text-gray-400 py-12">Belum ada surat masuk.</div>
    <div v-else class="space-y-3">
      <div v-for="item in items" :key="item.id"
        class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-pointer"
        @click="router.push(`/persuratan/surat-keluar/${item.id}`)">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-semibold rounded-full">{{ item.letter_type?.name }}</span>
              <span class="text-xs text-gray-400">{{ item.letter_number }}</span>
            </div>
            <h3 class="text-sm font-semibold text-gray-900">{{ item.subject }}</h3>
            <p class="text-xs text-gray-500 mt-1">Dari: {{ item.creator?.name }} · {{ item.sent_at?.split('T')[0] }}</p>
          </div>
          <EyeIcon class="w-4 h-4 text-gray-400 shrink-0 mt-1" />
        </div>
      </div>
    </div>
  </div>
</template>
