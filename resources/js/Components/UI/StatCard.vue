<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="bg-white overflow-hidden shadow-sm rounded-xl border border-neutral-100 p-6 relative transition-all duration-200"
        :class="href ? 'hover:shadow-md hover:border-brand-primary-200 cursor-pointer group' : ''"
    >
        <!-- Decorative background: blobs + icon (non-interactive) -->
        <div aria-hidden="true" class="absolute inset-0 overflow-hidden pointer-events-none">
            <div :class="['absolute -right-10 -top-10 h-28 w-28 rounded-full blur-3xl opacity-60', decorBlob1Classes]"></div>
            <div :class="['absolute -right-14 top-1/3 h-36 w-36 rounded-full blur-3xl opacity-50', decorBlob2Classes]"></div>
            <div :class="['absolute right-6 -bottom-14 h-24 w-24 rounded-full blur-2xl opacity-40', decorBlob3Classes]"></div>
            <div class="absolute -right-6 -bottom-6 opacity-20">
                <component :is="iconComponent" :class="['w-24 h-24 sm:w-28 sm:h-28', iconColorClasses]" />
            </div>
        </div>

        <!-- Content with full width -->
        <div class="relative z-10">
            <p class="text-base font-medium text-neutral-500 mb-2">{{ title }}</p>
            <h4 class="text-3xl font-bold text-neutral-900 group-hover:text-brand-primary-600 transition-colors mb-3">
                {{ value }}
            </h4>
            <div v-if="badgeText">
                <span :class="['inline-flex items-center px-2.5 py-1 rounded text-xs font-medium', badgeClasses]">
                    {{ badgeText }}
                </span>
            </div>
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
    IdentificationIcon,
    CheckCircleIcon
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
    'id-card': IdentificationIcon,
    check: CheckCircleIcon
};

const iconComponent = computed(() => iconMap[props.icon] || BuildingOfficeIcon);

const colorMap = {
    blue: { bg: 'bg-blue-50', text: 'text-blue-600', badge: 'bg-blue-100 text-blue-800', blob1: 'bg-blue-500/20', blob2: 'bg-sky-400/20', blob3: 'bg-indigo-400/15' },
    green: { bg: 'bg-green-50', text: 'text-green-600', badge: 'bg-green-100 text-green-800', blob1: 'bg-green-500/20', blob2: 'bg-emerald-400/20', blob3: 'bg-lime-400/15' },
    red: { bg: 'bg-red-50', text: 'text-red-600', badge: 'bg-red-100 text-red-800', blob1: 'bg-rose-500/20', blob2: 'bg-red-400/20', blob3: 'bg-orange-400/15' },
    amber: { bg: 'bg-amber-50', text: 'text-amber-600', badge: 'bg-amber-100 text-amber-800', blob1: 'bg-amber-400/25', blob2: 'bg-orange-400/20', blob3: 'bg-yellow-400/15' },
    purple: { bg: 'bg-purple-50', text: 'text-purple-600', badge: 'bg-purple-100 text-purple-800', blob1: 'bg-fuchsia-500/20', blob2: 'bg-purple-400/20', blob3: 'bg-indigo-400/15' },
    gray: { bg: 'bg-gray-50', text: 'text-gray-600', badge: 'bg-gray-100 text-gray-800', blob1: 'bg-neutral-400/20', blob2: 'bg-neutral-300/20', blob3: 'bg-neutral-300/15' }
};

const iconBgClasses = computed(() => colorMap[props.iconColor]?.bg || 'bg-gray-50');
const iconColorClasses = computed(() => colorMap[props.iconColor]?.text || 'text-gray-600');
const badgeClasses = computed(() => colorMap[props.badgeColor]?.badge || 'bg-gray-100 text-gray-800');
const decorBlob1Classes = computed(() => colorMap[props.iconColor]?.blob1 || 'bg-neutral-400/20');
const decorBlob2Classes = computed(() => colorMap[props.iconColor]?.blob2 || 'bg-neutral-300/20');
const decorBlob3Classes = computed(() => colorMap[props.iconColor]?.blob3 || 'bg-neutral-300/15');
</script>
