<template>
  <label :for="forId" class="block text-sm font-medium text-neutral-700">
    <slot />
  </label>
</template>

<script setup>
const props = defineProps({
  for: { type: String, default: '' },
})

const forId = props.for
</script>

