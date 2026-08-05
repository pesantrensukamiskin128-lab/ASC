<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView, RouterLink, useRouter } from 'vue-router'
import { useInstitutionStore } from '@/stores/institution'
import { useAuthStore } from '@/stores/auth'
import { BookOpenIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const institution = useInstitutionStore()
const auth        = useAuthStore()
const router      = useRouter()

onMounted(() => {
  institution.fetch()
  if (auth.token && !auth.user) auth.fetchMe()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
        <!-- Logo & Nama -->
        <RouterLink to="/repository" class="flex items-center gap-3 shrink-0">
          <div class="w-9 h-9 rounded-lg overflow-hidden shrink-0"
               :class="institution.logoUrl ? 'bg-transparent' : 'bg-blue-600'">
            <img v-if="institution.logoUrl" :src="institution.logoUrl" class="w-full h-full object-contain" />
            <span v-else class="flex items-center justify-center h-full text-white font-bold text-sm">
              {{ institution.name.charAt(0) }}
            </span>
          </div>
          <div class="hidden sm:block">
            <p class="text-sm font-semibold text-gray-900 leading-tight">{{ institution.name }}</p>
            <p class="text-xs text-gray-500 leading-tight">Repository Karya Ilmiah</p>
          </div>
        </RouterLink>

        <!-- Icon Repository -->
        <div class="flex items-center gap-1.5 text-blue-600 shrink-0">
          <BookOpenIcon class="w-5 h-5" />
          <span class="text-sm font-semibold hidden md:inline">Repository</span>
        </div>

        <div class="flex-1" />

        <!-- Auth Actions -->
        <div class="flex items-center gap-3">
          <template v-if="auth.isAuthenticated">
            <span class="text-sm text-gray-600 hidden sm:inline">{{ auth.user?.name }}</span>
            <RouterLink
              to="/dashboard"
              class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
            >
              Dashboard
            </RouterLink>
          </template>
          <template v-else>
            <span class="text-xs text-gray-500 hidden sm:inline">Login untuk download file</span>
            <RouterLink
              to="/login"
              class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
            >
              Masuk
            </RouterLink>
          </template>
        </div>
      </div>
    </header>

    <!-- Content -->
    <main class="max-w-7xl mx-auto px-4 py-6">
      <RouterView />
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200 bg-white mt-12">
      <div class="max-w-7xl mx-auto px-4 py-6 text-center text-sm text-gray-500">
        © {{ new Date().getFullYear() }} {{ institution.institution?.legal_entity_name || institution.name }} — Repository Karya Ilmiah
      </div>
    </footer>
  </div>
</template>
