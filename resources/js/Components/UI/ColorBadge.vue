<template>
  <span :class="classes">
    <span v-if="dot" class="mr-1.5 h-2 w-2 rounded-full" :class="dotClass" />
    <slot />
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  color: { type: String, default: 'neutral' },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  dot: { type: Boolean, default: false },
})

const sizes = {
  sm: 'text-xs px-2 py-0.5',
  md: 'text-xs px-2.5 py-1',
  lg: 'text-sm px-3 py-1.5',
}

const variants = {
  neutral: 'bg-neutral-100 text-neutral-700 ring-neutral-200',
  blue: 'bg-blue-100 text-blue-800 ring-blue-200',
  cyan: 'bg-cyan-100 text-cyan-800 ring-cyan-200',
  indigo: 'bg-indigo-100 text-indigo-800 ring-indigo-200',
  green: 'bg-green-100 text-green-800 ring-green-200',
  amber: 'bg-amber-100 text-amber-800 ring-amber-200',
  red: 'bg-red-100 text-red-800 ring-red-200',
  purple: 'bg-purple-100 text-purple-800 ring-purple-200',
  teal: 'bg-teal-100 text-teal-800 ring-teal-200',
}

const classes = computed(() => {
  const base = 'inline-flex items-center rounded-full font-medium ring-1 ring-inset'
  const variant = variants[props.color] || variants.neutral
  return [base, sizes[props.size], variant].join(' ')
})

const dotClass = computed(() => {
  const dotVariants = {
    neutral: 'bg-neutral-400',
    blue: 'bg-blue-500',
    cyan: 'bg-cyan-500',
    indigo: 'bg-indigo-500',
    green: 'bg-green-500',
    amber: 'bg-amber-500',
    red: 'bg-red-500',
    purple: 'bg-purple-500',
    teal: 'bg-teal-500',
  }
  return dotVariants[props.color] || dotVariants.neutral
})
</script>

