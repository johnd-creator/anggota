<template>
  <div 
    class="relative"
    @mouseenter="pauseAutoPlay"
    @mouseleave="resumeAutoPlay"
  >
    <!-- Carousel Container -->
    <div class="overflow-hidden rounded-lg">
      <div 
        class="flex transition-transform duration-500 ease-in-out"
        :style="{ transform: `translateX(-${currentIndex * 100}%)` }"
      >
        <div 
          v-for="(item, index) in items" 
          :key="item.id"
          class="w-full flex-shrink-0"
        >
          <slot :item="item" :index="index" />
        </div>
      </div>
    </div>

    <!-- Navigation Buttons -->
    <template v-if="items.length > 1">
      <!-- Previous Button -->
      <button
        type="button"
        :disabled="currentIndex === 0"
        class="absolute left-2 top-1/2 -translate-y-1/2 bg-white shadow-md rounded-full p-2 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all duration-200 z-10"
        :class="{ 'hidden': currentIndex === 0 }"
        @click="previous"
        aria-label="Previous announcement"
      >
        <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>

      <!-- Next Button -->
      <button
        type="button"
        :disabled="currentIndex === items.length - 1"
        class="absolute right-2 top-1/2 -translate-y-1/2 bg-white shadow-md rounded-full p-2 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all duration-200 z-10"
        :class="{ 'hidden': currentIndex === items.length - 1 }"
        @click="next"
        aria-label="Next announcement"
      >
        <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </template>

    <!-- Counter (optional, shows current position) -->
    <div v-if="items.length > 1 && showCounter" class="absolute bottom-3 right-3 bg-black/50 text-white text-xs px-2 py-1 rounded-full">
      {{ currentIndex + 1 }} / {{ items.length }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
  items: {
    type: Array,
    required: true,
    default: () => [],
  },
  autoPlay: {
    type: Boolean,
    default: true,
  },
  interval: {
    type: Number,
    default: 5000,
  },
  showCounter: {
    type: Boolean,
    default: false,
  },
});

const currentIndex = ref(0);
let autoPlayTimer = null;

const next = () => {
  if (currentIndex.value < props.items.length - 1) {
    currentIndex.value++;
  } else {
    currentIndex.value = 0;
  }
};

const previous = () => {
  if (currentIndex.value > 0) {
    currentIndex.value--;
  } else {
    currentIndex.value = props.items.length - 1;
  }
};

const startAutoPlay = () => {
  if (!props.autoPlay || props.items.length <= 1) return;
  
  stopAutoPlay();
  autoPlayTimer = setInterval(() => {
    next();
  }, props.interval);
};

const stopAutoPlay = () => {
  if (autoPlayTimer) {
    clearInterval(autoPlayTimer);
    autoPlayTimer = null;
  }
};

const pauseAutoPlay = () => {
  stopAutoPlay();
};

const resumeAutoPlay = () => {
  startAutoPlay();
};

watch(() => props.items, (newItems) => {
  if (newItems.length === 0) {
    currentIndex.value = 0;
  } else if (currentIndex.value >= newItems.length) {
    currentIndex.value = newItems.length - 1;
  }
  startAutoPlay();
}, { immediate: true });

onMounted(() => {
  startAutoPlay();
});

onUnmounted(() => {
  stopAutoPlay();
});
</script>
