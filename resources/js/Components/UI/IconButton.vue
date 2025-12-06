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
  const base = 'inline-flex items-center justify-center rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-primary-500';
  
  const sizes = {
    sm: 'p-1.5',
    md: 'p-2',
    lg: 'p-3',
  };

  const variants = {
    ghost: 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900',
    outline: 'border border-neutral-300 text-neutral-700 hover:bg-neutral-50',
  };

  const states = props.disabled
    ? 'opacity-50 cursor-not-allowed'
    : 'active:scale-95';

  return [base, sizes[props.size], variants[props.variant], states].join(' ');
});
</script>
