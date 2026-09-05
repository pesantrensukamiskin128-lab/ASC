<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  data: { label: string; value: number; color?: string }[]
  title?: string
  height?: number
  showValues?: boolean
  orientation?: 'vertical' | 'horizontal'
}>()

const maxValue = computed(() => Math.max(...props.data.map(d => d.value), 1))
const chartHeight = computed(() => props.height ?? 200)
const isVertical = computed(() => (props.orientation ?? 'vertical') === 'vertical')

const defaultColors = [
  'bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-orange-500',
  'bg-cyan-500', 'bg-pink-500', 'bg-indigo-500', 'bg-teal-500',
  'bg-amber-500', 'bg-rose-500', 'bg-emerald-500', 'bg-violet-500',
]

function getColor(index: number, custom?: string): string {
  return custom ?? defaultColors[index % defaultColors.length] ?? 'bg-blue-500'
}

const totalValue = computed(() => props.data.reduce((sum, d) => sum + d.value, 0))
</script>

<template>
  <div class="w-full">
    <p v-if="title" class="text-sm font-semibold text-gray-800 mb-4">{{ title }}</p>

    <!-- Vertical Bar Chart -->
    <div v-if="isVertical" class="flex items-end gap-2 justify-center" :style="{ height: `${chartHeight}px` }">
      <div v-for="(item, i) in data" :key="item.label" class="flex flex-col items-center justify-end flex-1 h-full group relative">
        <!-- Tooltip -->
        <div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity bg-gray-800 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap z-10">
          {{ item.label }}: {{ item.value }}
        </div>
        <!-- Value on top -->
        <span v-if="showValues !== false" class="text-[10px] font-bold text-gray-600 mb-1">{{ item.value }}</span>
        <!-- Bar -->
        <div
          :class="['w-full max-w-[48px] rounded-t-md transition-all duration-500 hover:opacity-80 cursor-pointer', getColor(i, item.color)]"
          :style="{ height: `${Math.max((item.value / maxValue) * 100, 3)}%` }"
        />
        <!-- Label -->
        <span class="text-[9px] text-gray-500 mt-2 text-center leading-tight truncate w-full px-0.5">{{ item.label }}</span>
      </div>
    </div>

    <!-- Horizontal Bar Chart -->
    <div v-else class="space-y-2.5">
      <div v-for="(item, i) in data" :key="item.label" class="group">
        <div class="flex items-center justify-between mb-0.5">
          <span class="text-xs text-gray-600 truncate max-w-[140px]">{{ item.label }}</span>
          <span class="text-xs font-bold text-gray-700">{{ item.value }}</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-5 overflow-hidden">
          <div
            :class="['h-full rounded-full transition-all duration-500', getColor(i, item.color)]"
            :style="{ width: `${Math.max((item.value / maxValue) * 100, 3)}%` }"
          />
        </div>
      </div>
    </div>
  </div>
</template>
