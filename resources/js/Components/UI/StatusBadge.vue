<template>
  <span :class="classes">
    <span v-if="showDot" class="w-2 h-2 rounded-full mr-1.5" :class="dotClass" />
    <slot>{{ displayLabel }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    default: 'pending',
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

const statusLabels = {
  pending: 'Pending',
  approved: 'Disetujui',
  rejected: 'Ditolak',
  active: 'Aktif',
  inactive: 'Nonaktif',
  draft: 'Draft',
  submitted: 'Diajukan',
  revision: 'Revisi',
  sent: 'Terkirim',
  archived: 'Diarsipkan',
  biasa: 'Biasa',
  terbatas: 'Terbatas',
  rahasia: 'Rahasia',
  segera: 'Segera',
  kilat: 'Sangat Segera',
};

const displayLabel = computed(() => props.label || statusLabels[props.status] || props.status);

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
    draft: 'bg-neutral-100 text-neutral-600',
    submitted: 'bg-blue-100 text-blue-700',
    revision: 'bg-yellow-100 text-yellow-700',
    sent: 'bg-green-100 text-green-700',
    archived: 'bg-neutral-200 text-neutral-700',
    biasa: 'bg-neutral-100 text-neutral-700',
    terbatas: 'bg-amber-100 text-amber-800',
    rahasia: 'bg-red-100 text-red-800',
    segera: 'bg-blue-100 text-blue-800',
    kilat: 'bg-red-100 text-red-800',
  };

  return [base, sizes[props.size], statusColors[props.status] || 'bg-neutral-100 text-neutral-600'].join(' ');
});

const dotClass = computed(() => {
  const dotColors = {
    pending: 'bg-status-warning',
    approved: 'bg-status-success',
    rejected: 'bg-status-error',
    status: 'bg-brand-primary-500',
    active: 'bg-status-success',
    inactive: 'bg-neutral-400',
    draft: 'bg-neutral-400',
    submitted: 'bg-blue-500',
    revision: 'bg-yellow-500',
    sent: 'bg-green-500',
    archived: 'bg-neutral-500',
    biasa: 'bg-neutral-400',
    terbatas: 'bg-amber-500',
    rahasia: 'bg-red-500',
    segera: 'bg-blue-500',
    kilat: 'bg-red-500',
  };
  return dotColors[props.status] || 'bg-neutral-400';
});
</script>
