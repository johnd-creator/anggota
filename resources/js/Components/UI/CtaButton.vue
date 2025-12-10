<template>
  <component
    :is="href ? Link : 'button'"
    :href="href"
    :type="!href ? type : undefined"
    class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-full shadow-lg shadow-blue-300/70 hover:bg-blue-700 transition transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
    :class="{ 'opacity-25 cursor-not-allowed': disabled }"
    :disabled="disabled"
  >
    <slot name="icon" />
    <slot />
  </component>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
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
</script>
