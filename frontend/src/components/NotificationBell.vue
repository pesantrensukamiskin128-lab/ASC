<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { BellIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const count = ref(0)
const dropdownOpen = ref(false)
const notifications = ref<any[]>([])
const loadingList = ref(false)

let pollInterval: any = null

onMounted(() => {
  fetchCount()
  pollInterval = setInterval(fetchCount, 30000) // Poll setiap 30 detik
})

onUnmounted(() => { if (pollInterval) clearInterval(pollInterval) })

async function fetchCount() {
  try { const { data } = await api.get('/notifications/unread-count'); count.value = data.count }
  catch {}
}

async function toggleDropdown() {
  dropdownOpen.value = !dropdownOpen.value
  if (dropdownOpen.value && !notifications.value.length) {
    loadingList.value = true
    try { const { data } = await api.get('/notifications', { params: { per_page: 8 } }); notifications.value = data.data }
    finally { loadingList.value = false }
  }
}

async function markRead(n: any) {
  if (!n.is_read) {
    await api.post(`/notifications/${n.id}/read`)
    n.is_read = true
    count.value = Math.max(0, count.value - 1)
  }
  if (n.link) router.push(n.link)
  dropdownOpen.value = false
}

async function markAllRead() {
  await api.post('/notifications/read-all')
  notifications.value.forEach(n => n.is_read = true)
  count.value = 0
}

function timeAgo(date: string) {
  const diff = (Date.now() - new Date(date).getTime()) / 1000
  if (diff < 60) return 'baru saja'
  if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`
  if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`
  return `${Math.floor(diff / 86400)} hari lalu`
}

const typeIcon: Record<string, string> = { info: '💬', warning: '⚠️', success: '✅', error: '❌' }
</script>

<template>
  <div class="relative">
    <button class="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700" @click="toggleDropdown">
      <BellIcon class="w-5 h-5" />
      <span v-if="count > 0" class="absolute -top-0.5 -right-0.5 w-5 h-5 flex items-center justify-center bg-red-500 text-white text-[10px] font-bold rounded-full">
        {{ count > 9 ? '9+' : count }}
      </span>
    </button>

    <!-- Dropdown -->
    <div v-if="dropdownOpen" class="absolute right-0 mt-2 w-80 bg-white rounded-xl border border-gray-200 shadow-xl z-50 overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <span class="text-sm font-semibold text-gray-800">Notifikasi</span>
        <button v-if="count > 0" class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="markAllRead">Tandai semua dibaca</button>
      </div>

      <div class="max-h-80 overflow-y-auto">
        <div v-if="loadingList" class="p-4 text-center text-gray-400 text-sm">Memuat...</div>
        <div v-else-if="!notifications.length" class="p-6 text-center text-gray-400 text-sm">Tidak ada notifikasi.</div>
        <button v-for="n in notifications" :key="n.id"
          :class="['w-full text-left px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors', !n.is_read ? 'bg-blue-50/50' : '']"
          @click="markRead(n)">
          <div class="flex items-start gap-2.5">
            <span class="text-sm shrink-0 mt-0.5">{{ typeIcon[n.type] ?? '💬' }}</span>
            <div class="min-w-0 flex-1">
              <p :class="['text-sm truncate', !n.is_read ? 'font-semibold text-gray-900' : 'text-gray-700']">{{ n.title }}</p>
              <p v-if="n.message" class="text-xs text-gray-500 truncate mt-0.5">{{ n.message }}</p>
              <p class="text-[10px] text-gray-400 mt-1">{{ timeAgo(n.created_at) }}</p>
            </div>
            <span v-if="!n.is_read" class="w-2 h-2 bg-blue-500 rounded-full shrink-0 mt-1.5" />
          </div>
        </button>
      </div>

      <div class="px-4 py-2.5 border-t border-gray-100 text-center">
        <button class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="router.push('/notifikasi'); dropdownOpen = false">Lihat Semua</button>
      </div>
    </div>

    <!-- Click outside -->
    <div v-if="dropdownOpen" class="fixed inset-0 z-40" @click="dropdownOpen = false" />
  </div>
</template>
