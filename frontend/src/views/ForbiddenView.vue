<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
</script>

<template>
  <div class="flex flex-col items-center justify-center min-h-[60vh] text-center">
    <p class="text-6xl font-bold text-gray-200">403</p>
    <h2 class="text-xl font-semibold text-gray-700 mt-2">Akses Ditolak</h2>
    <p class="text-sm text-gray-500 mt-1">Anda tidak memiliki izin untuk mengakses halaman ini.</p>
    <button
      class="mt-6 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
      @click="router.push('/dashboard')"
    >
      Kembali ke Dashboard
    </button>
  </div>
</template>
