<template>
  <component :is="href ? Link : 'div'" :href="href" class="block">
    <div :class="cardClasses">
      <!-- Icon top right -->
      <div v-if="icon" class="absolute top-4 right-4 p-2 rounded-lg" :class="iconBgClass">
        <component :is="iconComponent" class="h-5 w-5" :class="iconColorClass" />
      </div>
      
      <!-- Content -->
      <div>
        <p class="text-sm font-medium text-neutral-500 mb-1">{{ title }}</p>
        <p class="text-3xl font-bold text-neutral-900 mb-4">{{ formattedValue }}</p>
        
        <!-- Footer Badge -->
        <div v-if="badgeText" class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full" :class="dotColorClass" />
          <span class="text-xs text-neutral-500">{{ badgeText }}</span>
        </div>
      </div>
    </div>
  </component>
</template>

<script setup>
import { computed, h } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [Number, String],
    default: 0,
  },
  icon: {
    type: String,
    default: '',
    validator: (v) => ['', 'building', 'users', 'transfer', 'user-plus', 'refresh', 'money', 'chart', 'id-card', 'clock', 'inbox', 'mail', 'pencil', 'shield', 'bolt'].includes(v),
  },
  iconColor: {
    type: String,
    default: 'blue',
    validator: (v) => ['blue', 'amber', 'red', 'green', 'purple'].includes(v),
  },
  badgeText: {
    type: String,
    default: '',
  },
  badgeColor: {
    type: String,
    default: 'blue',
    validator: (v) => ['blue', 'amber', 'red', 'green', 'purple'].includes(v),
  },
  href: {
    type: String,
    default: '',
  },
});

const formattedValue = computed(() => {
  if (typeof props.value === 'number') {
    return props.value.toLocaleString('id-ID');
  }
  return props.value;
});

const cardClasses = computed(() => {
  const base = 'bg-white rounded-xl shadow-sm border border-neutral-100 p-5 relative transition-shadow duration-200';
  const hover = props.href ? 'hover:shadow-md cursor-pointer' : '';
  return [base, hover].filter(Boolean).join(' ');
});

const iconBgClass = computed(() => {
  const colors = {
    blue: 'bg-brand-primary-100',
    amber: 'bg-amber-100',
    red: 'bg-red-50',
    green: 'bg-green-100',
    purple: 'bg-brand-secondary-100',
  };
  return colors[props.iconColor];
});

const iconColorClass = computed(() => {
  const colors = {
    blue: 'text-brand-primary-600',
    amber: 'text-amber-600',
    red: 'text-red-400',
    green: 'text-green-600',
    purple: 'text-brand-secondary-600',
  };
  return colors[props.iconColor];
});

const dotColorClass = computed(() => {
  const colors = {
    blue: 'bg-brand-primary-500',
    amber: 'bg-amber-500',
    red: 'bg-red-500',
    green: 'bg-green-500',
    purple: 'bg-brand-secondary-500',
  };
  return colors[props.badgeColor];
});

// Icon components as render functions
const iconComponent = computed(() => {
  const icons = {
    building: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' })
        ]);
      }
    },
    users: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' })
        ]);
      }
    },
    transfer: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' })
        ]);
      }
    },
    'user-plus': {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' })
        ]);
      }
    },
    refresh: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15' })
        ]);
      }
    },
    money: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
        ]);
      }
    },
    chart: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' })
        ]);
      }
    },
    'id-card': {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2' })
        ]);
      }
    },
    clock: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' })
        ]);
      }
    },
    inbox: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0l-2 6H8l-2-6m14 0h-4a2 2 0 01-2 2h-4a2 2 0 01-2-2H4' })
        ]);
      }
    },
    mail: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8m-18 0V6a2 2 0 012-2h14a2 2 0 012 2v2m-18 0v10a2 2 0 002 2h14a2 2 0 002-2V8' })
        ]);
      }
    },
    pencil: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' })
        ]);
      }
    },
    shield: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 2l7 4v6c0 5-3 9-7 10-4-1-7-5-7-10V6l7-4z' })
        ]);
      }
    },
    bolt: {
      render() {
        return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M13 10V3L4 14h7v7l9-11h-7z' })
        ]);
      }
    },
  };
  return icons[props.icon] || null;
});
</script>
