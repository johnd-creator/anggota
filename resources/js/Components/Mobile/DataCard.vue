<template>
  <div class="bg-white rounded-xl border border-neutral-200 p-4 shadow-sm">
    <div class="flex items-start justify-between gap-3">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-1">
          <h3 class="font-semibold text-neutral-900 truncate">{{ title }}</h3>
          <StatusBadge v-if="status" :status="status" size="sm" />
        </div>
        <p v-if="subtitle" class="text-sm text-neutral-500 truncate">{{ subtitle }}</p>
      </div>
      
      <div v-if="$slots.actions" class="flex-shrink-0">
        <slot name="actions" />
      </div>
    </div>

    <div v-if="meta && meta.length > 0" class="mt-3 space-y-2">
      <div v-for="(item, index) in meta" :key="index" class="flex justify-between">
        <span class="text-xs text-neutral-500">{{ item.label }}</span>
        <span class="text-xs font-medium text-neutral-900 text-right">{{ item.value }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import StatusBadge from '@/Components/UI/StatusBadge.vue';

defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  status: {
    type: String,
    default: '',
  },
  meta: {
    type: Array,
    default: () => [],
  },
});
</script>
