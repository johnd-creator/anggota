<template>
  <div :class="cardClasses">
    <!-- Header slot -->
    <div v-if="$slots.header" :class="headerClasses">
      <slot name="header" />
    </div>

    <!-- Body -->
    <div :class="bodyClasses">
      <slot />
    </div>

    <!-- Footer slot -->
    <div v-if="$slots.footer" :class="footerClasses">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  padding: {
    type: String,
    default: 'md',
    validator: (value) => ['none', 'sm', 'md', 'lg'].includes(value),
  },
  shadow: {
    type: String,
    default: 'md',
    validator: (value) => ['none', 'sm', 'md', 'lg', 'xl'].includes(value),
  },
  hoverable: {
    type: Boolean,
    default: false,
  },
  bordered: {
    type: Boolean,
    default: false,
  },
});

const cardClasses = computed(() => {
  const base = 'bg-white rounded-lg overflow-hidden';
  
  const shadows = {
    none: '',
    sm: 'shadow-sm',
    md: 'shadow',
    lg: 'shadow-lg',
    xl: 'shadow-xl',
  };

  const border = props.bordered ? 'border border-neutral-200' : '';
  const hover = props.hoverable ? 'transition-shadow duration-200 hover:shadow-lg' : '';

  return [base, shadows[props.shadow], border, hover].join(' ');
});

const headerClasses = computed(() => {
  const paddings = {
    none: '',
    sm: 'px-3 py-2',
    md: 'px-4 py-3',
    lg: 'px-6 py-4',
  };

  return ['border-b border-neutral-200', paddings[props.padding]].join(' ');
});

const bodyClasses = computed(() => {
  const paddings = {
    none: '',
    sm: 'p-3',
    md: 'p-4',
    lg: 'p-6',
  };

  return paddings[props.padding];
});

const footerClasses = computed(() => {
  const paddings = {
    none: '',
    sm: 'px-3 py-2',
    md: 'px-4 py-3',
    lg: 'px-6 py-4',
  };

  return ['border-t border-neutral-200 bg-neutral-50', paddings[props.padding]].join(' ');
});
</script>
