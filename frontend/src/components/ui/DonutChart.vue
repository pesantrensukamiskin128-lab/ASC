<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  data: { label: string; value: number; color?: string }[]
  title?: string
  size?: number
}>()

const chartSize = computed(() => props.size ?? 160)
const radius = computed(() => chartSize.value / 2 - 15)
const circumference = computed(() => 2 * Math.PI * radius.value)
const total = computed(() => props.data.reduce((sum, d) => sum + d.value, 0))

const defaultColors = [
  '#3B82F6', '#10B981', '#8B5CF6', '#F59E0B',
  '#06B6D4', '#EC4899', '#6366F1', '#14B8A6',
  '#F97316', '#EF4444', '#84CC16', '#A855F7',
]

const segments = computed(() => {
  let offset = 0
  return props.data.map((item, i) => {
    const percentage = total.value > 0 ? item.value / total.value : 0
    const dashLength = percentage * circumference.value
    const seg = {
      ...item,
      color: item.color ?? defaultColors[i % defaultColors.length],
      dashArray: `${dashLength} ${circumference.value - dashLength}`,
      dashOffset: -offset,
      percentage: Math.round(percentage * 100),
    }
    offset += dashLength
    return seg
  })
})
</script>

<template>
  <div class="flex flex-col items-center">
    <p v-if="title" class="text-sm font-semibold text-gray-800 mb-3">{{ title }}</p>
    <div class="relative">
      <svg :width="chartSize" :height="chartSize" class="transform -rotate-90">
        <circle
          v-for="(seg, i) in segments"
          :key="i"
          :cx="chartSize / 2"
          :cy="chartSize / 2"
          :r="radius"
          fill="none"
          :stroke="seg.color"
          stroke-width="24"
          :stroke-dasharray="seg.dashArray"
          :stroke-dashoffset="seg.dashOffset"
          class="transition-all duration-500"
        />
      </svg>
      <!-- Center text -->
      <div class="absolute inset-0 flex flex-col items-center justify-center">
        <p class="text-2xl font-bold text-gray-900">{{ total }}</p>
        <p class="text-[10px] text-gray-500">Total</p>
      </div>
    </div>
    <!-- Legend -->
    <div class="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1">
      <div v-for="(seg, i) in segments" :key="i" class="flex items-center gap-1.5">
        <div class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: seg.color }" />
        <span class="text-[10px] text-gray-600">{{ seg.label }} ({{ seg.percentage }}%)</span>
      </div>
    </div>
  </div>
</template>
