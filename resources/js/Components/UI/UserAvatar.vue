<template>
  <div :class="wrapperClasses">
    <OptimizedImage
      v-if="src && !errored"
      :src="src"
      :alt="name"
      size="small"
      class="h-full w-full"
      @error="errored = true"
    />
    <span v-else class="text-sm font-semibold">
      {{ initials }}
    </span>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import OptimizedImage from '@/Components/OptimizedImage.vue';

const props = defineProps({
  src: {
    type: String,
    default: '',
  },
  name: {
    type: String,
    default: 'User',
  },
  size: {
    type: String,
    default: 'h-9 w-9',
  },
});

const errored = ref(false);

const initials = computed(() => {
  if (!props.name) return 'U';
  return props.name
    .split(' ')
    .filter(Boolean)
    .map(part => part[0]?.toUpperCase() || '')
    .join('')
    .slice(0, 2) || 'U';
});

const wrapperClasses = computed(() => `rounded-full overflow-hidden bg-brand-primary-100 text-brand-primary-700 flex items-center justify-center ${props.size}`);
</script>
