<script setup lang="ts" generic="T extends { id: number }">
import { computed } from 'vue'

const props = defineProps<{
  columns: { key: string; label: string; class?: string }[]
  rows: T[]
  loading?: boolean
  total?: number
  currentPage?: number
  lastPage?: number
}>()

const emit = defineEmits<{
  pageChange: [page: number]
}>()

type PaginationItem = number | 'start-ellipsis' | 'end-ellipsis'

const normalizedCurrentPage = computed(() => {
  const lastPage = Math.max(1, props.lastPage ?? 1)
  return Math.min(Math.max(1, props.currentPage ?? 1), lastPage)
})

const paginationItems = computed<PaginationItem[]>(() => {
  const lastPage = Math.max(1, props.lastPage ?? 1)
  const currentPage = normalizedCurrentPage.value

  if (lastPage <= 7) {
    return Array.from({ length: lastPage }, (_, index) => index + 1)
  }

  let windowStart: number
  let windowEnd: number

  if (currentPage <= 4) {
    windowStart = 2
    windowEnd = 5
  } else if (currentPage >= lastPage - 3) {
    windowStart = lastPage - 4
    windowEnd = lastPage - 1
  } else {
    windowStart = currentPage - 1
    windowEnd = currentPage + 1
  }

  const items: PaginationItem[] = [1]
  if (windowStart > 2) items.push('start-ellipsis')
  for (let page = windowStart; page <= windowEnd; page++) items.push(page)
  if (windowEnd < lastPage - 1) items.push('end-ellipsis')
  items.push(lastPage)

  return items
})

function changePage(page: number) {
  const lastPage = Math.max(1, props.lastPage ?? 1)
  if (page < 1 || page > lastPage || page === normalizedCurrentPage.value) return
  emit('pageChange', page)
}
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
              <slot :name="`header-${col.key}`">{{ col.label }}</slot>
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
    <div v-if="lastPage && lastPage > 1" class="flex flex-col gap-3 px-4 py-3 border-t border-gray-100 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-sm text-gray-500 shrink-0">
        Halaman {{ currentPage }} dari {{ lastPage }} ({{ total }} data)
      </p>
      <nav class="flex flex-wrap items-center gap-1 sm:justify-end" aria-label="Navigasi halaman tabel">
        <button
          type="button"
          :disabled="normalizedCurrentPage === 1"
          class="inline-flex h-8 items-center rounded-lg px-2.5 text-sm text-gray-600 transition-colors hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
          aria-label="Halaman sebelumnya"
          @click="changePage(normalizedCurrentPage - 1)"
        >
          <span aria-hidden="true">‹</span>
          <span class="ml-1 hidden md:inline">Sebelumnya</span>
        </button>

        <template v-for="item in paginationItems" :key="item">
          <span
            v-if="typeof item !== 'number'"
            class="inline-flex h-8 min-w-8 items-center justify-center px-1 text-sm text-gray-400"
            aria-hidden="true"
          >
            …
          </span>
          <button
            v-else
            type="button"
            :disabled="item === normalizedCurrentPage"
            :aria-current="item === normalizedCurrentPage ? 'page' : undefined"
            :aria-label="`Halaman ${item}`"
            :class="[
              'inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-sm transition-colors',
              item === normalizedCurrentPage
                ? 'bg-blue-600 text-white font-medium'
                : 'text-gray-600 hover:bg-gray-100',
            ]"
            @click="changePage(item)"
          >
            {{ item }}
          </button>
        </template>

        <button
          type="button"
          :disabled="normalizedCurrentPage === lastPage"
          class="inline-flex h-8 items-center rounded-lg px-2.5 text-sm text-gray-600 transition-colors hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
          aria-label="Halaman berikutnya"
          @click="changePage(normalizedCurrentPage + 1)"
        >
          <span class="mr-1 hidden md:inline">Berikutnya</span>
          <span aria-hidden="true">›</span>
        </button>
      </nav>
    </div>
  </div>
</template>
