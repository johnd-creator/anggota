<template>
  <p v-if="message" class="mt-1 text-xs text-status-error">{{ message }}</p>
</template>

<script setup>
defineProps({
  message: { type: String, default: '' },
})
</script>

