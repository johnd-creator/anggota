<template>
  <span :class="classes"><slot /></span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: { type: String, default: 'neutral' },
  size: { type: String, default: 'md' },
});

const classes = computed(() => {
  const base = 'inline-flex items-center rounded-full font-medium';
  const sizes = { sm: 'text-xs px-2 py-1', md: 'text-xs px-2.5 py-1.5', lg: 'text-sm px-3 py-1.5' };
  const variants = {
    neutral: 'bg-neutral-100 text-neutral-700',
    success: 'bg-status-success-light text-status-success-dark',
    warning: 'bg-status-warning-light text-status-warning-dark',
    danger: 'bg-status-danger-light text-status-danger-dark',
    info: 'bg-status-info-light text-status-info-dark',
    brand: 'bg-brand-primary-100 text-brand-primary-800',
  };
  return [base, sizes[props.size], variants[props.variant]].join(' ');
});
</script>

