<script setup lang="ts">
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue'
import {
  Bars3Icon, ChevronDownIcon,
  ArrowRightOnRectangleIcon, UserCircleIcon,
} from '@heroicons/vue/24/outline'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useInstitutionStore } from '@/stores/institution'
import NotificationBell from '@/components/NotificationBell.vue'

defineEmits<{ toggleSidebar: [] }>()

const router      = useRouter()
const auth        = useAuthStore()
const institution = useInstitutionStore()
</script>

<template>
  <header class="flex items-center gap-3 px-4 py-2.5 bg-white border-b border-gray-200 shrink-0">
    <!-- Toggle sidebar -->
    <button
      class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors"
      @click="$emit('toggleSidebar')"
    >
      <Bars3Icon class="w-5 h-5" />
    </button>

    <!-- Nama institusi di header (mobile) -->
    <div class="flex items-center gap-2 sm:hidden">
      <div class="w-6 h-6 rounded overflow-hidden bg-blue-600 flex items-center justify-center shrink-0">
        <img v-if="institution.logoUrl" :src="institution.logoUrl" class="w-full h-full object-contain" />
        <span v-else class="text-white text-xs font-bold">{{ institution.name.charAt(0) }}</span>
      </div>
      <span class="text-sm font-semibold text-gray-800 truncate max-w-[140px]">{{ institution.name }}</span>
    </div>

    <div class="flex-1" />

    <!-- Notifications -->
    <NotificationBell />

    <!-- User dropdown -->
    <Menu as="div" class="relative">
      <MenuButton class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
        <div class="w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center shrink-0">
          {{ auth.user?.name?.charAt(0).toUpperCase() }}
        </div>
        <div class="hidden sm:block text-left">
          <p class="text-sm font-medium text-gray-700 leading-tight">{{ auth.user?.name }}</p>
          <p class="text-xs text-gray-400 leading-tight">{{ auth.user?.roles?.[0] }}</p>
        </div>
        <ChevronDownIcon class="w-4 h-4 text-gray-400" />
      </MenuButton>

      <MenuItems class="absolute right-0 mt-1 w-52 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 focus:outline-none">
        <!-- User info di dropdown -->
        <div class="px-4 py-3 border-b border-gray-100">
          <p class="text-sm font-semibold text-gray-900">{{ auth.user?.name }}</p>
          <p class="text-xs text-gray-500 mt-0.5">{{ auth.user?.email }}</p>
          <span class="inline-flex mt-1.5 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-full">
            {{ auth.user?.roles?.[0] }}
          </span>
        </div>

        <MenuItem v-slot="{ active }">
          <button
            :class="['flex items-center gap-2 w-full px-4 py-2 text-sm mt-1', active ? 'bg-gray-50 text-gray-900' : 'text-gray-700']"
            @click="router.push('/profile')"
          >
            <UserCircleIcon class="w-4 h-4" />
            Profil Saya
          </button>
        </MenuItem>

        <div class="my-1 border-t border-gray-100" />

        <MenuItem v-slot="{ active }">
          <button
            :class="['flex items-center gap-2 w-full px-4 py-2 text-sm', active ? 'bg-red-50 text-red-700' : 'text-red-600']"
            @click="auth.logout()"
          >
            <ArrowRightOnRectangleIcon class="w-4 h-4" />
            Keluar
          </button>
        </MenuItem>
      </MenuItems>
    </Menu>
  </header>
</template>
