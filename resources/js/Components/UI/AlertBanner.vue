<template>
  <div v-if="isVisible" :class="alertClasses" role="alert">
    <div class="flex items-start gap-3">
      <!-- Icon with background circle -->
      <div :class="iconWrapperClasses">
        <component :is="iconComponent" class="h-5 w-5" />
      </div>
      
      <!-- Content -->
      <div class="flex-1 min-w-0">
        <h3 v-if="title" :class="titleClasses">
          {{ title }}
        </h3>
        <div :class="messageClasses">
          <slot>{{ message }}</slot>
        </div>
      </div>
      
      <!-- Dismiss button -->
      <div v-if="dismissible" class="flex-shrink-0">
        <button
          type="button"
          :class="closeButtonClasses"
          @click="handleDismiss"
        >
          <span class="sr-only">Dismiss</span>
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  type: {
    type: String,
    default: 'info',
    validator: (value) => ['info', 'success', 'warning', 'error'].includes(value),
  },
  title: {
    type: String,
    default: '',
  },
  message: {
    type: String,
    default: '',
  },
  dismissible: {
    type: Boolean,
    default: false,
  },
  autoDismissMs: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(['dismiss']);

const isVisible = ref(true);
let dismissTimer = null;

const resolvedAutoDismissMs = computed(() => {
  if (props.autoDismissMs > 0) {
    return props.autoDismissMs;
  }
  if (props.dismissible && props.type === 'success') {
    return 4000;
  }
  return 0;
});

function clearTimer() {
  if (dismissTimer) {
    clearTimeout(dismissTimer);
    dismissTimer = null;
  }
}

function startTimer() {
  clearTimer();
  const ms = resolvedAutoDismissMs.value;
  if (ms > 0) {
    dismissTimer = setTimeout(() => {
      isVisible.value = false;
      emit('dismiss');
    }, ms);
  }
}

function handleDismiss() {
  isVisible.value = false;
  clearTimer();
  emit('dismiss');
}

watch(() => [props.message, props.title], () => {
  isVisible.value = true;
  startTimer();
});

onMounted(() => {
  startTimer();
});

onBeforeUnmount(() => {
  clearTimer();
});

const alertClasses = computed(() => {
  const base = 'relative rounded-xl p-4 shadow-sm border-l-4 transition-all duration-200';
  
  const variants = {
    info: 'bg-blue-50 border-blue-500',
    success: 'bg-green-50 border-green-500',
    warning: 'bg-amber-50 border-amber-500',
    error: 'bg-red-50 border-red-500',
  };

  return [base, variants[props.type]].join(' ');
});

const iconWrapperClasses = computed(() => {
  const base = 'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center';
  
  const variants = {
    info: 'bg-blue-100 text-blue-600',
    success: 'bg-green-100 text-green-600',
    warning: 'bg-amber-100 text-amber-600',
    error: 'bg-red-100 text-red-600',
  };

  return [base, variants[props.type]].join(' ');
});

const titleClasses = computed(() => {
  const base = 'text-sm font-semibold mb-1';
  
  const variants = {
    info: 'text-blue-900',
    success: 'text-green-900',
    warning: 'text-amber-900',
    error: 'text-red-900',
  };

  return [base, variants[props.type]].join(' ');
});

const messageClasses = computed(() => {
  const base = props.title ? 'text-sm' : 'text-sm font-medium';
  
  const variants = {
    info: 'text-blue-800',
    success: 'text-green-800',
    warning: 'text-amber-800',
    error: 'text-red-800',
  };

  return [base, variants[props.type]].join(' ');
});

const closeButtonClasses = computed(() => {
  const base = 'inline-flex rounded-lg p-1.5 transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2';
  
  const variants = {
    info: 'text-blue-600 hover:bg-blue-100 focus:ring-blue-500',
    success: 'text-green-600 hover:bg-green-100 focus:ring-green-500',
    warning: 'text-amber-600 hover:bg-amber-100 focus:ring-amber-500',
    error: 'text-red-600 hover:bg-red-100 focus:ring-red-500',
  };

  return [base, variants[props.type]].join(' ');
});

const iconComponent = computed(() => {
  const icons = {
    info: {
      template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>`,
    },
    success: {
      template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>`,
    },
    warning: {
      template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>`,
    },
    error: {
      template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>`,
    },
  };

  return icons[props.type];
});
</script>
