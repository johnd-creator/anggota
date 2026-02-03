<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'

interface Props {
  src?: string | null
  alt?: string
  size?: 'thumb' | 'small' | 'medium' | 'large'
  class?: string
  loading?: 'lazy' | 'eager'
}

const props = withDefaults(defineProps<Props>(), {
  src: null,
  alt: '',
  size: 'medium',
  class: '',
  loading: 'lazy'
})

const emit = defineEmits<{
  loaded: []
  error: []
}>()

const imageLoaded = ref(false)
const imageError = ref(false)
const currentSrc = ref('')

onMounted(() => {
  if (props.src) {
    // Add /storage/ prefix if not already present
    currentSrc.value = props.src.startsWith('/storage/') 
      ? props.src 
      : `/storage/${props.src}`
  }
})

const handleLoad = () => {
  imageLoaded.value = true
  emit('loaded')
}

const handleError = () => {
  imageError.value = true
  emit('error')
}

const containerClass = computed(() => {
  return [
    'optimized-image-container',
    props.class,
    {
      'loaded': imageLoaded.value,
      'error': imageError.value
    }
  ]
})

const imageClass = computed(() => {
  return [
    'optimized-image',
    {
      'opacity-0': !imageLoaded.value,
      'opacity-100': imageLoaded.value
    }
  ]
})
</script>

<template>
  <div :class="containerClass">
    <!-- Picture element with WebP support -->
    <picture v-if="src && !imageError">
      <!-- WebP source -->
      <source
        v-if="size === 'thumb'"
        :srcset="`${src}?size=thumb&format=webp`"
        type="image/webp"
      >
      <source
        v-else-if="size === 'small'"
        :srcset="`${src}?size=small&format=webp`"
        type="image/webp"
      >
      <source
        v-else-if="size === 'medium'"
        :srcset="`${src}?size=medium&format=webp`"
        type="image/webp"
      >
      <source
        v-else-if="size === 'large'"
        :srcset="`${src}?size=large&format=webp`"
        type="image/webp"
      >
      <source
        v-else
        :srcset="`${src}?format=webp`"
        type="image/webp"
      >

      <!-- Fallback source -->
      <img
        :src="currentSrc"
        :alt="alt"
        :loading="loading"
        :class="imageClass"
        @load="handleLoad"
        @error="handleError"
        decoding="async"
      />
    </picture>

    <!-- Fallback/Placeholder -->
    <div v-else class="image-placeholder">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
      </svg>
    </div>
  </div>
</template>

<style scoped>
.optimized-image-container {
  position: relative;
  overflow: hidden;
  background-color: #f3f4f6;
}

.image-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  min-height: 100px;
  background-color: #f3f4f6;
  color: #9ca3af;
}

.image-placeholder svg {
  width: 48px;
  height: 48px;
}

.optimized-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: opacity 0.3s ease-in-out;
}

.opacity-0 {
  opacity: 0;
}

.opacity-100 {
  opacity: 1;
}

/* Loading skeleton effect */
.optimized-image-container:not(.loaded)::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(
    90deg,
    #f0f0f0 25%,
    #e0e0e0 50%,
    #f0f0f0 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  z-index: 1;
}

@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

/* Error state */
.optimized-image-container.error {
  background-color: #fee2e2;
}

.optimized-image-container.error .image-placeholder {
  color: #ef4444;
}
</style>
