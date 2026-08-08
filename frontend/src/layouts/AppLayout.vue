<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterView, useRouter, useRoute } from 'vue-router'
import AppSidebar from '@/components/AppSidebar.vue'
import AppHeader from '@/components/AppHeader.vue'
import { useInstitutionStore } from '@/stores/institution'

const router = useRouter()
const route = useRoute()
const sidebarOpen   = ref(true)
const institutionStore = useInstitutionStore()

onMounted(() => institutionStore.fetch())
</script>

<template>
  <div class="flex h-screen bg-gray-50 overflow-hidden">
    <!-- Sidebar -->
    <AppSidebar :open="sidebarOpen" @close="sidebarOpen = false" />

    <!-- Main content -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">
      <AppHeader @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="flex-1 overflow-y-auto p-6">
        <RouterView />
      </main>
    </div>

    <!-- Floating Scan QR Button -->
    <button
      v-if="route.path !== '/scan-qr'"
      @click="router.push('/scan-qr')"
      class="fixed bottom-6 right-6 z-40 w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg hover:shadow-xl flex items-center justify-center transition-all duration-200 hover:scale-105 active:scale-95 group"
      title="Scan QR Code"
    >
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h4V3H2v5h1V4zM20 4h-4V3h5v5h-1V4zM4 20v-4H3v5h5v-1H4zM20 20v-4h1v5h-5v-1h4z"/>
        <rect x="7" y="7" width="4" height="4" rx="0.5" stroke-linecap="round" stroke-linejoin="round"/>
        <rect x="13" y="7" width="4" height="4" rx="0.5" stroke-linecap="round" stroke-linejoin="round"/>
        <rect x="7" y="13" width="4" height="4" rx="0.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M14 14h1.5m1.5 0h1m-4 3h4"/>
      </svg>
      <!-- Tooltip -->
      <span class="absolute bottom-full right-0 mb-2 px-3 py-1.5 bg-gray-900 text-white text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-md">
        Scan QR Code
      </span>
    </button>
  </div>
</template>
