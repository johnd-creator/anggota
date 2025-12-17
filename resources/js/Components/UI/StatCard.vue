<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="bg-white overflow-hidden shadow-sm rounded-xl border border-neutral-100 p-6 flex items-start justify-between transition-all duration-200"
        :class="href ? 'hover:shadow-md hover:border-brand-primary-200 cursor-pointer group' : ''"
    >
        <div>
            <p class="text-sm font-medium text-neutral-500 mb-1">{{ title }}</p>
            <h4 class="text-2xl font-bold text-neutral-900 group-hover:text-brand-primary-600 transition-colors">
                {{ value }}
            </h4>
            <div v-if="badgeText" class="mt-3">
                <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium', badgeClasses]">
                    {{ badgeText }}
                </span>
            </div>
        </div>
        <div :class="['p-3 rounded-lg', iconBgClasses]">
            <component :is="iconComponent" :class="['w-6 h-6', iconColorClasses]" />
        </div>
    </component>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { 
    BuildingOfficeIcon, 
    UsersIcon, 
    InboxIcon, 
    EnvelopeIcon, 
    ArrowPathIcon,
    BoltIcon,
    ArrowsRightLeftIcon,
    UserPlusIcon,
    ChatBubbleLeftRightIcon,
    ClockIcon,
    IdentificationIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    title: String,
    value: [String, Number],
    icon: String,
    iconColor: {
        type: String,
        default: 'blue'
    },
    badgeText: String,
    badgeColor: {
        type: String,
        default: 'gray'
    },
    href: String
});

const iconMap = {
    building: BuildingOfficeIcon,
    users: UsersIcon,
    inbox: InboxIcon,
    mail: EnvelopeIcon,
    refresh: ArrowPathIcon,
    bolt: BoltIcon,
    transfer: ArrowsRightLeftIcon,
    'user-plus': UserPlusIcon,
    'chat-alt': ChatBubbleLeftRightIcon,
    clock: ClockIcon,
    'id-card': IdentificationIcon
};

const iconComponent = computed(() => iconMap[props.icon] || BuildingOfficeIcon);

const colorMap = {
    blue: { bg: 'bg-blue-50', text: 'text-blue-600', badge: 'bg-blue-100 text-blue-800' },
    green: { bg: 'bg-green-50', text: 'text-green-600', badge: 'bg-green-100 text-green-800' },
    red: { bg: 'bg-red-50', text: 'text-red-600', badge: 'bg-red-100 text-red-800' },
    amber: { bg: 'bg-amber-50', text: 'text-amber-600', badge: 'bg-amber-100 text-amber-800' },
    purple: { bg: 'bg-purple-50', text: 'text-purple-600', badge: 'bg-purple-100 text-purple-800' },
    gray: { bg: 'bg-gray-50', text: 'text-gray-600', badge: 'bg-gray-100 text-gray-800' }
};

const iconBgClasses = computed(() => colorMap[props.iconColor]?.bg || 'bg-gray-50');
const iconColorClasses = computed(() => colorMap[props.iconColor]?.text || 'text-gray-600');
const badgeClasses = computed(() => colorMap[props.badgeColor]?.badge || 'bg-gray-100 text-gray-800');
</script>
