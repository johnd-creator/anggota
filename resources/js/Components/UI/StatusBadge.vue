<template>
  <span :class="classes">
    <span v-if="showDot" class="w-2 h-2 rounded-full mr-1.5" :class="dotClass" />
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    default: 'pending',
    validator: (v) => ['pending', 'approved', 'rejected', 'status', 'active', 'inactive'].includes(v),
  },
  label: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  showDot: {
    type: Boolean,
    default: true,
  },
});

const classes = computed(() => {
  const base = 'inline-flex items-center rounded-full font-medium';
  
  const sizes = {
    sm: 'text-xs px-2 py-0.5',
    md: 'text-xs px-2.5 py-1',
    lg: 'text-sm px-3 py-1.5',
  };

  const statusColors = {
    pending: 'bg-status-warning-light text-status-warning-dark',
    approved: 'bg-status-success-light text-status-success-dark',
    rejected: 'bg-status-error-light text-status-error-dark',
    status: 'bg-brand-primary-100 text-brand-primary-700',
    active: 'bg-status-success-light text-status-success-dark',
    inactive: 'bg-neutral-100 text-neutral-600',
  };

  return [base, sizes[props.size], statusColors[props.status]].join(' ');
});

const dotClass = computed(() => {
  const dotColors = {
    pending: 'bg-status-warning',
    approved: 'bg-status-success',
    rejected: 'bg-status-error',
    status: 'bg-brand-primary-500',
    active: 'bg-status-success',
    inactive: 'bg-neutral-400',
  };
  return dotColors[props.status];
});
</script>
