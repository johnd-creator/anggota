<template>
  <div class="w-full">
    <label
      v-if="label"
      :for="inputId"
      class="block text-sm font-medium text-neutral-700 mb-1"
    >
      {{ label }}
      <span v-if="required" class="text-status-error">*</span>
    </label>
    
    <div class="relative">
      <input
        :id="inputId"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :class="inputClasses"
        @input="handleInput"
        @blur="handleBlur"
      />
      
      <!-- Error icon -->
      <div v-if="error" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
        <svg class="h-5 w-5 text-status-error" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
      </div>
    </div>

    <!-- Helper or Error text -->
    <p v-if="error" class="mt-1 text-sm text-status-error">
      {{ error }}
    </p>
    <p v-else-if="helper" class="mt-1 text-sm text-neutral-500">
      {{ helper }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  type: {
    type: String,
    default: 'text',
    validator: (value) => ['text', 'email', 'password', 'number', 'tel', 'url'].includes(value),
  },
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  helper: {
    type: String,
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
  inputId: {
    type: String,
    default: () => `input-${Math.random().toString(36).substr(2, 9)}`,
  },
});

const emit = defineEmits(['update:modelValue', 'blur']);

const handleInput = (event) => {
  emit('update:modelValue', event.target.value);
};

const handleBlur = (event) => {
  emit('blur', event);
};

const inputClasses = computed(() => {
  const base = 'block w-full rounded-lg border px-3 py-2 text-neutral-900 placeholder-neutral-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0';
  
  const errorState = props.error
    ? 'border-status-error focus:border-status-error focus:ring-status-error/20 pr-10'
    : 'border-neutral-300 focus:border-brand-primary-500 focus:ring-brand-primary-500/20';

  const disabledState = props.disabled
    ? 'bg-neutral-100 cursor-not-allowed opacity-60'
    : 'bg-white hover:border-neutral-400';

  return [base, errorState, disabledState].join(' ');
});
</script>
