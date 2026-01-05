<template>
  <component
    :is="href ? Link : 'button'"
    :href="href"
    :type="!href ? type : undefined"
    :class="buttonClasses"
    :disabled="disabled"
  >
    <slot name="icon" />
    <slot />
  </component>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  href: {
    type: String,
    default: null,
  },
  type: {
    type: String,
    default: 'button',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-full shadow-lg transition-all duration-200 transform focus:outline-none focus:ring-2 focus:ring-offset-2';
  
  const states = props.disabled
    ? 'opacity-60 cursor-not-allowed'
    : 'hover:-translate-y-0.5 active:translate-y-0 hover:shadow-xl';

  // Gradient dengan warna navy yang senada dengan sidebar
  const colors = 'bg-gradient-to-r from-[#1A2B63] to-[#2E4080] text-white shadow-[#1A2B63]/40 hover:from-[#2E4080] hover:to-[#1A2B63] focus:ring-[#1A2B63]/50';

  return [base, states, colors].join(' ');
});
</script>
