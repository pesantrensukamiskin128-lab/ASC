<script setup lang="ts" generic="T extends { id: number }">
defineProps<{
  columns: { key: string; label: string; class?: string }[]
  rows: T[]
  loading?: boolean
  total?: number
  currentPage?: number
  lastPage?: number
}>()

defineEmits<{
  pageChange: [page: number]
}>()
</script>

<template>
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th
              v-for="col in columns"
              :key="col.key"
              :class="['px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider', col.class]"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <!-- Loading -->
          <tr v-if="loading">
            <td :colspan="columns.length" class="px-4 py-12 text-center text-gray-400">
              <div class="flex items-center justify-center gap-2">
                <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                Memuat data...
              </div>
            </td>
          </tr>

          <!-- Empty -->
          <tr v-else-if="rows.length === 0">
            <td :colspan="columns.length" class="px-4 py-12 text-center text-gray-400">
              Tidak ada data ditemukan.
            </td>
          </tr>

          <!-- Rows -->
          <tr
            v-else
            v-for="row in rows"
            :key="row.id"
            class="border-b border-gray-100 hover:bg-gray-50 transition-colors"
          >
            <slot :row="row" />
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage && lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
      <p class="text-sm text-gray-500">
        Halaman {{ currentPage }} dari {{ lastPage }} ({{ total }} data)
      </p>
      <div class="flex gap-1">
        <button
          v-for="p in lastPage"
          :key="p"
          :disabled="p === currentPage"
          :class="[
            'px-3 py-1 rounded-lg text-sm transition-colors',
            p === currentPage
              ? 'bg-blue-600 text-white font-medium'
              : 'text-gray-600 hover:bg-gray-100',
          ]"
          @click="$emit('pageChange', p)"
        >
          {{ p }}
        </button>
      </div>
    </div>
  </div>
</template>
