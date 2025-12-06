<template>
  <div class="flex items-center">
    <button
      type="button"
      :class="switchClasses"
      :aria-checked="modelValue"
      :aria-label="label"
      :disabled="disabled"
      @click="toggle"
    >
      <span :class="toggleClasses" />
    </button>
    <label v-if="label" class="ml-3 text-sm font-medium text-neutral-700">
      {{ label }}
    </label>
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
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue']);

const toggle = () => {
  if (!props.disabled) {
    emit('update:modelValue', !props.modelValue);
  }
};

const switchClasses = computed(() => {
  const base = 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-primary-500 focus:ring-offset-2';
  
  const activeState = props.modelValue
    ? 'bg-brand-primary-600'
    : 'bg-neutral-200';

  const disabledState = props.disabled
    ? 'opacity-50 cursor-not-allowed'
    : '';

  return [base, activeState, disabledState].join(' ');
});

const toggleClasses = computed(() => {
  const base = 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out';
  
  const position = props.modelValue
    ? 'translate-x-5'
    : 'translate-x-0';

  return [base, position].join(' ');
});
</script>
