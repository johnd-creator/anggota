<template>
  <component
    :is="href ? Link : 'button'"
    :href="href || undefined"
    :type="!href ? type : undefined"
    :disabled="!href ? (disabled || loading) : undefined"
    :aria-disabled="href ? (disabled || loading) : undefined"
    :class="buttonClasses"
    @click="handleClick"
  >
    <svg
      v-if="loading"
      class="animate-spin -ml-1 mr-2 h-4 w-4"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
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
    validator: (value) => ['button', 'submit', 'reset'].includes(value),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  fullWidth: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['click']);

const handleClick = (event) => {
  if (props.disabled || props.loading) {
    event?.preventDefault?.();
    event?.stopPropagation?.();
    return;
  }

  if (!props.href) {
    emit('click', event);
  }
};

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center font-semibold rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 border-2';
  
  const sizes = {
    sm: 'px-3 py-2 text-sm',
    md: 'px-5 py-2.5 text-sm',
    lg: 'px-6 py-3 text-base',
  };

  const states = props.disabled || props.loading
    ? 'opacity-60 cursor-not-allowed pointer-events-none'
    : 'hover:bg-[#1A2B63]/5 hover:border-[#1A2B63] hover:-translate-y-0.5 active:translate-y-0';

  // Outline style dengan warna navy yang senada
  const colors = 'bg-white text-[#1A2B63] border-[#1A2B63]/30 focus:ring-[#1A2B63]/30';

  const width = props.fullWidth ? 'w-full' : '';

  return [base, sizes[props.size], states, colors, width].join(' ');
});
</script>
