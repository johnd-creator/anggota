<template>
  <div class="flex items-start">
    <div class="flex items-center h-5">
      <input
        :id="checkboxId"
        type="checkbox"
        :checked="modelValue"
        :disabled="disabled"
        :class="checkboxClasses"
        @change="handleChange"
      />
    </div>
    <div v-if="label || description" class="ml-3 text-sm">
      <label :for="checkboxId" class="font-medium text-neutral-700">
        {{ label }}
      </label>
      <p v-if="description" class="text-neutral-500">{{ description }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  checkboxId: {
    type: String,
    default: () => `checkbox-${Math.random().toString(36).substr(2, 9)}`,
  },
});

const emit = defineEmits(['update:modelValue']);

const handleChange = (event) => {
  emit('update:modelValue', event.target.checked);
};

const checkboxClasses = computed(() => {
  const base = 'h-4 w-4 rounded border-neutral-300 text-brand-primary-600 focus:ring-2 focus:ring-brand-primary-500 focus:ring-offset-2 transition-colors duration-200';
  
  const disabledState = props.disabled
    ? 'opacity-50 cursor-not-allowed'
    : 'cursor-pointer';

  return [base, disabledState].join(' ');
});
</script>
