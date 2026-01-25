<template>
  <div class="bg-white p-4 rounded-xl border border-neutral-100 shadow-sm space-y-3">
    <!-- Header: Title & Status -->
    <div class="flex justify-between items-start gap-3">
      <div class="flex-1 min-w-0">
        <h3 class="font-bold text-neutral-900 truncate leading-snug">{{ title }}</h3>
        <p v-if="subtitle" class="text-xs text-neutral-500 mt-0.5 truncate">{{ subtitle }}</p>
      </div>
      <div v-if="status" class="flex-shrink-0">
        <span 
          class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-medium uppercase tracking-wide"
          :class="statusClass"
        >
          {{ statusLabel }}
        </span>
      </div>
    </div>

    <!-- Meta Information (Grid) -->
    <div v-if="meta && meta.length" class="grid grid-cols-2 gap-y-2 gap-x-4 pt-2 border-t border-neutral-50">
      <div v-for="(item, idx) in meta" :key="idx" class="flex flex-col">
        <span class="text-[10px] text-neutral-400 uppercase font-semibold">{{ item.label }}</span>
        <span class="text-xs text-neutral-700 font-medium truncate">{{ item.value }}</span>
      </div>
    </div>

    <!-- Actions Footer -->
    <div v-if="$slots.actions" class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  status: { type: [String, Object], default: null }, // Can be raw string or object { label, color }
  meta: { type: Array, default: () => [] }, // Array of { label, value }
});

const statusLabel = computed(() => {
  if (typeof props.status === 'object' && props.status !== null) {
    return props.status.label;
  }
  return props.status;
});

const statusClass = computed(() => {
  if (typeof props.status === 'object' && props.status !== null && props.status.color) {
    // Map common color names to Tailwind utility sets or use raw if valid class
    const map = {
      success: 'bg-green-100 text-green-700',
      warning: 'bg-yellow-100 text-yellow-700',
      danger: 'bg-red-100 text-red-700',
      info: 'bg-blue-100 text-blue-700',
      neutral: 'bg-gray-100 text-gray-700',
    };
    return map[props.status.color] || props.status.color;
  }
  
  // Fallback string matching
  const s = String(props.status).toLowerCase();
  if (['approved', 'paid', 'success', 'aktif', 'disetujui'].includes(s)) return 'bg-green-100 text-green-700';
  if (['pending', 'submitted', 'draft', 'revision', 'menunggu'].includes(s)) return 'bg-yellow-100 text-yellow-700';
  if (['rejected', 'unpaid', 'failed', 'ditolak', 'gagal'].includes(s)) return 'bg-red-100 text-red-700';
  
  return 'bg-gray-100 text-gray-700';
});
</script>
