<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click="handleBackdropClick">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-neutral-900 bg-opacity-50 transition-opacity" />

        <!-- Modal container -->
        <div class="flex min-h-full items-center justify-center p-4">
          <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            enter-to-class="opacity-100 translate-y-0 sm:scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-y-0 sm:scale-100"
            leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          >
            <div
              v-if="show"
              :class="modalClasses"
              @click.stop
            >
              <!-- Header -->
              <div v-if="$slots.header || title" class="px-6 py-4 border-b border-neutral-200">
                <div class="flex items-center justify-between">
                  <h3 class="text-lg font-semibold text-neutral-900">
                    <slot name="header">{{ title }}</slot>
                  </h3>
                  <button
                    v-if="closable"
                    type="button"
                    class="text-neutral-400 hover:text-neutral-600 transition-colors"
                    @click="close"
                  >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Body -->
              <div class="px-6 py-4">
                <slot />
              </div>

              <!-- Footer -->
              <div v-if="$slots.footer" class="px-6 py-4 bg-neutral-50 border-t border-neutral-200">
                <slot name="footer" />
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, watch } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg', 'xl', 'full'].includes(value),
  },
  closable: {
    type: Boolean,
    default: true,
  },
  closeOnBackdrop: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['close', 'update:show']);

const close = () => {
  emit('update:show', false);
  emit('close');
};

const handleBackdropClick = () => {
  if (props.closeOnBackdrop) {
    close();
  }
};

const modalClasses = computed(() => {
  const base = 'relative bg-white rounded-lg shadow-xl transform transition-all';
  
  const sizes = {
    sm: 'max-w-sm w-full',
    md: 'max-w-md w-full',
    lg: 'max-w-lg w-full',
    xl: 'max-w-2xl w-full',
    full: 'max-w-7xl w-full',
  };

  return [base, sizes[props.size]].join(' ');
});

// Prevent body scroll when modal is open
watch(() => props.show, (newValue) => {
  if (newValue) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});
</script>
