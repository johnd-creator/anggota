<template>
  <component
    :is="componentType"
    v-bind="componentProps"
    :class="linkClasses"
    :title="resolvedTitle"
    @click="handleClick"
  >
    <component :is="iconComponent" :class="iconClasses" />
    <span v-if="label" :class="labelClasses">{{ label }}</span>
  </component>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  EyeIcon,
  InformationCircleIcon,
  PaperAirplaneIcon,
  PencilSquareIcon,
  TrashIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'
import IconButton from '@/Components/UI/IconButton.vue'

const props = defineProps({
  action: {
    type: String,
    required: true,
    validator: (value) => ['preview', 'detail', 'edit', 'delete', 'submit', 'approve', 'revise', 'reject'].includes(value),
  },
  ariaLabel: {
    type: String,
    required: true,
  },
  variant: {
    type: String,
    default: null,
    validator: (value) => value === null || ['ghost', 'outline'].includes(value),
  },
  size: {
    type: String,
    default: 'sm',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  href: {
    type: String,
    default: '',
  },
  as: {
    type: String,
    default: 'button',
    validator: (value) => ['button', 'link'].includes(value),
  },
  type: {
    type: String,
    default: 'button',
  },
})

const emit = defineEmits(['click'])

const actionConfig = {
  preview: {
    icon: EyeIcon,
    title: 'Preview',
    iconClass: 'text-neutral-500',
    labelClass: 'text-neutral-600',
    variant: 'ghost',
  },
  detail: {
    icon: InformationCircleIcon,
    title: 'Detail',
    iconClass: 'text-brand-primary-600',
    labelClass: 'text-brand-primary-600',
    variant: 'ghost',
  },
  edit: {
    icon: PencilSquareIcon,
    title: 'Edit',
    iconClass: 'text-blue-600',
    labelClass: 'text-blue-600',
    variant: 'ghost',
  },
  delete: {
    icon: TrashIcon,
    title: 'Hapus',
    iconClass: 'text-status-error',
    labelClass: 'text-status-error',
    variant: 'ghost',
  },
  submit: {
    icon: PaperAirplaneIcon,
    title: 'Ajukan',
    iconClass: 'text-green-600',
    labelClass: 'text-green-600',
    variant: 'outline',
  },
  approve: {
    icon: CheckCircleIcon,
    title: 'Setujui',
    iconClass: 'text-green-600',
    labelClass: 'text-green-600',
    variant: 'outline',
  },
  revise: {
    icon: ArrowPathIcon,
    title: 'Minta Revisi',
    iconClass: 'text-yellow-600',
    labelClass: 'text-yellow-600',
    variant: 'outline',
  },
  reject: {
    icon: XCircleIcon,
    title: 'Tolak',
    iconClass: 'text-red-600',
    labelClass: 'text-red-600',
    variant: 'outline',
  },
}

const config = computed(() => actionConfig[props.action])
const isLink = computed(() => props.as === 'link' || !!props.href)
const resolvedTitle = computed(() => props.title || config.value.title)
const resolvedVariant = computed(() => props.variant || config.value.variant)
const iconComponent = computed(() => config.value.icon)
const iconClasses = computed(() => [props.label ? 'h-4 w-4' : 'h-5 w-5', config.value.iconClass].join(' '))
const labelClasses = computed(() => ['text-xs font-medium', config.value.labelClass].join(' '))

const linkClasses = computed(() => {
  if (!isLink.value) {
    return props.label ? 'gap-1.5' : ''
  }

  const base = 'inline-flex items-center justify-center rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1A2B63]/30'
  const sizes = {
    sm: props.label ? 'px-2 py-1.5 gap-1.5' : 'p-1.5',
    md: props.label ? 'px-3 py-2 gap-1.5' : 'p-2',
    lg: props.label ? 'px-3.5 py-3 gap-2' : 'p-3',
  }
  const variants = {
    ghost: 'hover:bg-[#1A2B63]/10',
    outline: 'border-2 border-[#1A2B63]/20 hover:bg-[#1A2B63]/5 hover:border-[#1A2B63]/40',
  }
  const states = props.disabled ? 'opacity-50 pointer-events-none cursor-not-allowed' : 'hover:scale-110 active:scale-95'

  return [base, sizes[props.size], variants[resolvedVariant.value], states].join(' ')
})

const componentType = computed(() => (isLink.value ? Link : IconButton))

const componentProps = computed(() => {
  if (isLink.value) {
    return {
      href: props.href,
      'aria-label': props.ariaLabel,
    }
  }

  return {
    type: props.type,
    size: props.size,
    variant: resolvedVariant.value,
    disabled: props.disabled,
    ariaLabel: props.ariaLabel,
  }
})

function handleClick(event) {
  if (props.disabled) {
    event?.preventDefault()
    return
  }

  emit('click', event)
}
</script>
