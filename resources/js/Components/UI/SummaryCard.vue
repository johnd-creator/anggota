<template>
  <div class="bg-white rounded-xl border border-neutral-200 p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-neutral-500 font-medium">{{ title }}</p>
        <p :class="['text-2xl font-bold mt-1', valueColorClass]">{{ value }}</p>
        <p v-if="trend" class="text-xs text-neutral-400 mt-1">{{ trend }}</p>
      </div>
      <div v-if="icon" :class="['w-10 h-10 rounded-lg flex items-center justify-center', iconBgClass]">
        <component :is="iconComponent" :class="['w-5 h-5', iconColorClass]" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { 
  InboxIcon, 
  CheckCircleIcon, 
  ClockIcon, 
  XCircleIcon, 
  DocumentTextIcon, 
  UserGroupIcon, 
  PencilSquareIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
  title: { type: String, required: true },
  value: { type: [Number, String], required: true },
  color: { type: String, default: 'blue' }, // blue, green, yellow, red, neutral
  trend: { type: String, default: '' },
  icon: { type: String, default: '' },
});

const colors = {
  blue: { text: 'text-blue-600', bg: 'bg-blue-100', icon: 'text-blue-600' },
  green: { text: 'text-green-600', bg: 'bg-green-100', icon: 'text-green-600' },
  yellow: { text: 'text-yellow-600', bg: 'bg-yellow-100', icon: 'text-yellow-600' },
  red: { text: 'text-red-600', bg: 'bg-red-100', icon: 'text-red-600' },
  neutral: { text: 'text-neutral-900', bg: 'bg-neutral-100', icon: 'text-neutral-600' },
};

const valueColorClass = computed(() => colors[props.color]?.text || colors.neutral.text);
const iconBgClass = computed(() => colors[props.color]?.bg || colors.neutral.bg);
const iconColorClass = computed(() => colors[props.color]?.icon || colors.neutral.icon);

const iconComponent = computed(() => {
  const map = {
    'inbox': InboxIcon,
    'check': CheckCircleIcon,
    'clock': ClockIcon,
    'x': XCircleIcon,
    'document': DocumentTextIcon,
    'users': UserGroupIcon,
    'pencil': PencilSquareIcon
  };
  return map[props.icon] || DocumentTextIcon;
});
</script>
