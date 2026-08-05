<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView, RouterLink } from 'vue-router'
import { useInstitutionStore } from '@/stores/institution'
import { useAuthStore } from '@/stores/auth'

const institution = useInstitutionStore()
const auth        = useAuthStore()
onMounted(() => institution.fetch())
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
      <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg overflow-hidden shrink-0"
               :class="institution.logoUrl ? 'bg-transparent' : 'bg-blue-600'">
            <img v-if="institution.logoUrl" :src="institution.logoUrl" class="w-full h-full object-contain" />
            <span v-else class="flex items-center justify-center h-full text-white font-bold text-sm">
              {{ institution.name.charAt(0) }}
            </span>
          </div>
          <div>
            <p class="text-sm font-semibold text-gray-900">{{ institution.name }}</p>
            <p class="text-xs text-gray-500">Penerimaan Mahasiswa Baru</p>
          </div>
        </div>
        <nav class="flex items-center gap-4">
          <RouterLink to="/pmb" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Beranda</RouterLink>
          <template v-if="auth.token">
            <RouterLink to="/pmb/form" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Formulir</RouterLink>
            <RouterLink to="/pmb/status" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Status</RouterLink>
            <span class="text-sm font-medium text-gray-700">{{ auth.user?.name }}</span>
          </template>
          <template v-else>
            <RouterLink to="/pmb/login" class="text-sm px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium">
              Masuk / Daftar
            </RouterLink>
          </template>
        </nav>
      </div>
    </header>

    <!-- Content -->
    <main class="max-w-6xl mx-auto px-4 py-8">
      <RouterView />
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200 bg-white mt-12">
      <div class="max-w-6xl mx-auto px-4 py-6 text-center text-xs text-gray-400">
        © {{ new Date().getFullYear() }} {{ institution.institution?.legal_entity_name || institution.name }}. All rights reserved.
      </div>
    </footer>
  </div>
</template>
