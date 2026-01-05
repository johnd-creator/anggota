<template>
  <button
    :type="type"
    :disabled="disabled"
    :class="buttonClasses"
    :aria-label="ariaLabel"
    @click="handleClick"
  >
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
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
  variant: {
    type: String,
    default: 'ghost',
    validator: (value) => ['ghost', 'outline'].includes(value),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  ariaLabel: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['click']);

const handleClick = (event) => {
  if (!props.disabled) {
    emit('click', event);
  }
};

const buttonClasses = computed(() => {
  const base = 'inline-flex items-center justify-center rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1A2B63]/30';
  
  const sizes = {
    sm: 'p-1.5',
    md: 'p-2',
    lg: 'p-3',
  };

  const variants = {
    ghost: 'text-neutral-600 hover:bg-[#1A2B63]/10 hover:text-[#1A2B63]',
    outline: 'border-2 border-[#1A2B63]/20 text-[#1A2B63] hover:bg-[#1A2B63]/5 hover:border-[#1A2B63]/40',
  };

  const states = props.disabled
    ? 'opacity-50 cursor-not-allowed'
    : 'hover:scale-110 active:scale-95';

  return [base, sizes[props.size], variants[props.variant], states].join(' ');
});
</script>
