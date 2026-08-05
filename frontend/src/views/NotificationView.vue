<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { TrashIcon, CheckIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const toast = useToast()
const items = ref<any[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const filterUnread = ref(false)

onMounted(() => load())

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/notifications', { params: { unread_only: filterUnread.value ? 1 : '', page } })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

async function markRead(n: any) {
  if (!n.is_read) { await api.post(`/notifications/${n.id}/read`); n.is_read = true }
  if (n.link) router.push(n.link)
}

async function markAllRead() {
  await api.post('/notifications/read-all')
  items.value.forEach(n => n.is_read = true)
  toast.success('Semua ditandai dibaca.')
}

async function remove(n: any) {
  await api.delete(`/notifications/${n.id}`)
  items.value = items.value.filter(x => x.id !== n.id)
}

function timeAgo(date: string) {
  const diff = (Date.now() - new Date(date).getTime()) / 1000
  if (diff < 60) return 'baru saja'
  if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`
  if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`
  return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const typeIcon: Record<string, string> = { info: '💬', warning: '⚠️', success: '✅', error: '❌' }
const typeBg: Record<string, string> = { info: 'border-l-blue-500', warning: 'border-l-yellow-500', success: 'border-l-green-500', error: 'border-l-red-500' }
</script>

<template>
  <div class="space-y-5 max-w-3xl mx-auto">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Notifikasi</h1>
        <p class="text-sm text-gray-500 mt-0.5">Semua pemberitahuan dan aktivitas terbaru</p>
      </div>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm text-gray-600">
          <input v-model="filterUnread" type="checkbox" class="rounded" @change="load()" /> Belum dibaca
        </label>
        <button class="px-3 py-1.5 text-xs text-blue-600 hover:bg-blue-50 border border-blue-200 rounded-lg font-medium" @click="markAllRead">Tandai Semua Dibaca</button>
      </div>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else-if="!items.length" class="text-center text-gray-400 py-12">Tidak ada notifikasi.</div>
    <div v-else class="space-y-2">
      <div v-for="n in items" :key="n.id"
        :class="['flex items-start gap-3 p-4 bg-white rounded-xl border-l-4 border border-gray-200 hover:shadow-sm transition-shadow cursor-pointer',
          typeBg[n.type] ?? 'border-l-gray-300', !n.is_read ? 'bg-blue-50/30' : '']"
        @click="markRead(n)">
        <span class="text-lg shrink-0">{{ typeIcon[n.type] ?? '💬' }}</span>
        <div class="flex-1 min-w-0">
          <p :class="['text-sm', !n.is_read ? 'font-semibold text-gray-900' : 'text-gray-700']">{{ n.title }}</p>
          <p v-if="n.message" class="text-sm text-gray-500 mt-0.5">{{ n.message }}</p>
          <p class="text-xs text-gray-400 mt-1">{{ timeAgo(n.created_at) }}</p>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <button v-if="!n.is_read" class="p-1.5 rounded text-green-600 hover:bg-green-50" title="Tandai dibaca" @click.stop="markRead(n)"><CheckIcon class="w-4 h-4" /></button>
          <button class="p-1.5 rounded text-red-500 hover:bg-red-50" title="Hapus" @click.stop="remove(n)"><TrashIcon class="w-4 h-4" /></button>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.lastPage > 1" class="flex items-center justify-center gap-2 pt-4">
      <button v-for="p in pagination.lastPage" :key="p"
        :class="['w-8 h-8 rounded-lg text-xs font-medium', p === pagination.currentPage ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']"
        @click="load(p)">{{ p }}</button>
    </div>
  </div>
</template>
