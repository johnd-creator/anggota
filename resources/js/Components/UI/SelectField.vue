<template>
  <div class="w-full">
    <label
      v-if="label"
      :for="selectId"
      class="block text-sm font-medium text-neutral-700 mb-1"
    >
      {{ label }}
      <span v-if="required" class="text-status-error">*</span>
    </label>
    
    <div class="relative">
      <select
        :id="selectId"
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        :class="selectClasses"
        @change="handleChange"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option
          v-for="option in options"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </option>
      </select>
      
      <!-- Dropdown icon -->
      <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
        <svg class="h-5 w-5 text-neutral-400" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
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
  options: {
    type: Array,
    required: true,
    validator: (value) => value.every(opt => 'value' in opt && 'label' in opt),
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
  selectId: {
    type: String,
    default: () => `select-${Math.random().toString(36).substr(2, 9)}`,
  },
});

const emit = defineEmits(['update:modelValue']);

const handleChange = (event) => {
  emit('update:modelValue', event.target.value);
};

const selectClasses = computed(() => {
  const base = 'block w-full rounded-lg border px-3 py-2 pr-10 text-neutral-900 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0 appearance-none';
  
  const errorState = props.error
    ? 'border-status-error focus:border-status-error focus:ring-status-error/20'
    : 'border-neutral-300 focus:border-brand-primary-500 focus:ring-brand-primary-500/20';

  const disabledState = props.disabled
    ? 'bg-neutral-100 cursor-not-allowed opacity-60'
    : 'bg-white hover:border-neutral-400';

  return [base, errorState, disabledState].join(' ');
});
</script>
