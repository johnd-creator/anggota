<template>
  <button :class="classes" @click="$emit('toggle')" :aria-pressed="active">
    <slot />
    <svg v-if="removable" class="h-3 w-3 ml-2" fill="currentColor" viewBox="0 0 20 20">
      <path fill-rule="evenodd" d="M6 6l8 8M14 6l-8 8" clip-rule="evenodd" />
    </svg>
  </button>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({ active: Boolean, removable: { type: Boolean, default: false } });
const classes = computed(() => {
  const base = 'inline-flex items-center rounded-full border px-3 py-1 text-xs transition-colors focus:outline-none focus:ring-2 focus:ring-brand-primary-500';
  return props.active ? base + ' bg-brand-primary-50 border-brand-primary-300 text-brand-primary-800' : base + ' bg-white border-neutral-300 text-neutral-700 hover:bg-neutral-50';
});
</script>

