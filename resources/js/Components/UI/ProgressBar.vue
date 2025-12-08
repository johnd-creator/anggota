<template>
  <div class="w-full">
    <div v-if="showLabel" class="flex justify-between items-center mb-1">
      <span v-if="label" class="text-sm font-medium text-neutral-700">{{ label }}</span>
      <span class="text-sm font-medium" :class="textColorClass">{{ percentage }}%</span>
    </div>
    <div class="w-full bg-neutral-200 rounded-full overflow-hidden" :class="heightClass">
      <div 
        class="h-full rounded-full transition-all duration-500 ease-out"
        :class="barColorClass"
        :style="{ width: `${percentage}%` }"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  value: {
    type: Number,
    default: 0,
  },
  max: {
    type: Number,
    default: 100,
  },
  color: {
    type: String,
    default: 'blue',
    validator: (v) => ['blue', 'green', 'yellow', 'red', 'purple'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  showLabel: {
    type: Boolean,
    default: true,
  },
  label: {
    type: String,
    default: '',
  },
});

const percentage = computed(() => {
  if (props.max <= 0) return 0;
  const pct = Math.round((props.value / props.max) * 100);
  return Math.min(100, Math.max(0, pct));
});

const heightClass = computed(() => {
  const sizes = {
    sm: 'h-1.5',
    md: 'h-2.5',
    lg: 'h-4',
  };
  return sizes[props.size];
});

const barColorClass = computed(() => {
  const colors = {
    blue: 'bg-brand-primary-500',
    green: 'bg-status-success',
    yellow: 'bg-status-warning',
    red: 'bg-status-error',
    purple: 'bg-brand-secondary-500',
  };
  return colors[props.color];
});

const textColorClass = computed(() => {
  const colors = {
    blue: 'text-brand-primary-600',
    green: 'text-status-success-dark',
    yellow: 'text-status-warning-dark',
    red: 'text-status-error-dark',
    purple: 'text-brand-secondary-600',
  };
  return colors[props.color];
});
</script>
