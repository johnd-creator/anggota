<template>
    <AppLayout page-title="Dashboard">
        <!-- KPI Stat Cards Row -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 mb-6">
            <StatCard
                title="Total Unit"
                :value="$page.props.counters?.units_total || 0"
                icon="building"
                iconColor="blue"
                badgeText="Total Summary"
                badgeColor="blue"
                :href="isMemberRole ? '' : '/admin/units'"
            />
            <StatCard
                title="Total Seluruh Anggota"
                :value="$page.props.counters?.members_total || 0"
                icon="users"
                iconColor="amber"
                badgeText="KPI Summary"
                badgeColor="amber"
                :href="isMemberRole ? '' : '/admin/members'"
            />
            <StatCard v-if="!isMemberRole"
                title="Mutasi Pending"
                :value="$page.props.counters?.mutations_pending || 0"
                icon="transfer"
                iconColor="red"
                badgeText="KPI Summary"
                badgeColor="red"
                href="/admin/mutations"
            />
            <StatCard v-if="!isMemberRole"
                title="Onboarding Pending"
                :value="$page.props.counters?.onboarding_pending || 0"
                icon="user-plus"
                iconColor="amber"
                badgeText="New Summary"
                badgeColor="amber"
                href="/admin/onboarding"
            />
            <StatCard v-if="!isMemberRole"
                title="Update Request Pending"
                :value="$page.props.counters?.updates_pending || 0"
                icon="refresh"
                iconColor="blue"
                badgeText="KPI Summary"
                badgeColor="blue"
                href="/admin/updates"
            />
            <StatCard
                v-if="showUnitMembersCard"
                title="Total Anggota"
                :value="$page.props.counters?.members_unit_total || 0"
                icon="id-card"
                iconColor="green"
                :badgeText="unitBadgeText"
                badgeColor="green"
                :href="unitCardHref"
            />
        </div>

        <div v-if="isMemberRole" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-neutral-100 p-6 flex flex-col gap-2">
                <p class="text-sm text-neutral-500">Kartu Digital</p>
                <h3 class="text-lg font-semibold text-neutral-900">Akses KTA Digital</h3>
                <p class="text-sm text-neutral-500">Lihat dan unduh KTA kamu kapan saja.</p>
                <Link href="/member/portal" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-primary-600 hover:underline">
                    Buka Portal KTA
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </Link>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-neutral-100 p-6 flex flex-col gap-2">
                <p class="text-sm text-neutral-500">Profil Anggota</p>
                <h3 class="text-lg font-semibold text-neutral-900">Perbarui Data Anda</h3>
                <p class="text-sm text-neutral-500">Pastikan informasi personal selalu akurat.</p>
                <Link href="/member/profile" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-primary-600 hover:underline">
                    Buka Profil
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </Link>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6" v-if="!isMemberRole">
            <!-- Iuran Bulan Ini Panel - spans 2 columns -->
            <div v-if="showDuesCard" class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-neutral-100 p-6">
                <h3 class="text-lg font-semibold text-neutral-900 mb-4">Iuran Bulan Ini</h3>

                <!-- Progress Bar -->
                <div class="mb-4">
                    <ProgressBar
                        :value="duesProgress"
                        :max="100"
                        color="blue"
                        size="lg"
                        :showLabel="true"
                    />
                </div>

                <!-- Amount Display -->
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <span class="text-2xl font-bold text-brand-primary-600">{{ formatCurrency(duesCollected) }}</span>
                        <span class="text-neutral-500 mx-2">/</span>
                        <span class="text-lg text-neutral-600">{{ formatCurrency(duesTarget) }}</span>
                    </div>
                    <span class="text-sm text-neutral-500">{{ duesProgress }}%</span>
                </div>

                <!-- Action Button -->
                <Link href="/finance/dues" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-primary-600 text-white text-sm font-medium rounded-lg hover:bg-brand-primary-700 transition-colors">
                    Bayar Iuran Sekarang
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </Link>
            </div>

            <!-- Recent Activity Panel -->
            <div class="bg-white rounded-xl shadow-sm border border-neutral-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-neutral-900">Recent Activity</h3>
                    <button class="p-1.5 rounded-lg hover:bg-neutral-100 transition-colors">
                        <svg class="h-4 w-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div v-for="(activity, index) in recentActivities" :key="index" class="flex items-start gap-3">
                        <div class="flex-shrink-0 mt-1">
                            <component :is="getActivityIcon(activity.type)" class="h-4 w-4" :class="getActivityIconClass(activity.type)" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-neutral-800">{{ activity.message }}</p>
                            <p class="text-xs text-neutral-500 mt-0.5">{{ activity.time }}</p>
                        </div>
                    </div>

                    <div v-if="recentActivities.length === 0" class="text-center py-4 text-sm text-neutral-500">
                        Belum ada aktivitas terbaru
                    </div>
                </div>
            </div>
        </div>
        <div v-else-if="isMemberRole && showMemberDuesCard" class="bg-white rounded-xl shadow-sm border border-neutral-100 p-6 mb-6">
            <h3 class="text-lg font-semibold text-neutral-900 mb-4">Iuran Bulan Ini</h3>
            <ProgressBar :value="duesProgress" :max="100" color="blue" size="lg" :showLabel="true" />
            <div class="flex items-center justify-between mt-4">
                <div>
                    <span class="text-2xl font-bold text-brand-primary-600">{{ formatCurrency(duesCollected) }}</span>
                    <span class="text-neutral-500 mx-2">/</span>
                    <span class="text-lg text-neutral-600">{{ formatCurrency(duesTarget) }}</span>
                </div>
                <span class="text-sm text-neutral-500">{{ duesProgress }}%</span>
            </div>
        </div>

        <!-- Recent Mutasi Pending Table -->
        <div v-if="!isMemberRole" class="bg-white rounded-xl shadow-sm border border-neutral-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-neutral-100">
                <h3 class="text-lg font-semibold text-neutral-900">Recent Mutasi Pending</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200">
                    <thead class="bg-neutral-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-neutral-600 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-neutral-600 uppercase tracking-wider">Member Name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-neutral-600 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-neutral-600 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-neutral-600 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 bg-white">
                        <tr v-for="mutation in recentMutations" :key="mutation.id" class="hover:bg-neutral-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-800">{{ mutation.id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-800">{{ mutation.member_name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-600">{{ mutation.type }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-600">{{ formatDate(mutation.date) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <StatusBadge :status="mutation.status" :label="mutation.status_label" />
                            </td>
                        </tr>
                        <tr v-if="recentMutations.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-neutral-500">
                                Tidak ada mutasi pending saat ini
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Unpaid Members Modal -->
        <ModalBase v-model:show="showUnpaidModal" title="Anggota Belum Bayar Iuran" size="lg">
            <div class="space-y-4">
                <p class="text-sm text-neutral-600">Periode: <strong>{{ duesSummary?.period || currentPeriod }}</strong></p>
                <div v-if="unpaidMembers.length === 0" class="text-center text-neutral-500 py-4">
                    Semua anggota sudah membayar iuran bulan ini! 🎉
                </div>
                <div v-else class="max-h-80 overflow-y-auto">
                    <table class="min-w-full divide-y divide-neutral-200">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs text-neutral-500">Nama</th>
                                <th class="px-4 py-2 text-left text-xs text-neutral-500">KTA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 bg-white">
                            <tr v-for="m in unpaidMembers" :key="m.id">
                                <td class="px-4 py-2 text-sm">{{ m.full_name }}</td>
                                <td class="px-4 py-2 text-sm">{{ m.kta_number || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-between">
                    <Link href="/finance/dues?status=unpaid" class="text-sm text-brand-primary-600 hover:underline">
                        Kelola Iuran →
                    </Link>
                    <SecondaryButton @click="showUnpaidModal = false">Tutup</SecondaryButton>
                </div>
            </template>
        </ModalBase>
    </AppLayout>
</template>

<script setup>
import { h } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import ProgressBar from '@/Components/UI/ProgressBar.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const page = usePage();
const pg = page.props || {};

const roleName = computed(() => pg.auth?.user?.role?.name || '');
const isMemberRole = computed(() => roleName.value === 'anggota');

const showUnpaidModal = ref(false);
const currentPeriod = new Date().toISOString().slice(0, 7);

// Dues data
const duesSummary = computed(() => pg.dues_summary || null);
const unpaidMembers = computed(() => pg.unpaid_members || []);

const showUnitMembersCard = computed(() => ['admin_unit', 'bendahara', 'anggota'].includes(roleName.value));
const showDuesCard = computed(() => ['admin_unit', 'bendahara', 'super_admin'].includes(roleName.value));
const showMemberDuesCard = computed(() => isMemberRole.value && !!duesSummary.value);
const unitBadgeText = computed(() => {
    if (!showUnitMembersCard.value) return '';
    const unit = pg.auth?.user?.organization_unit;
    if (unit?.name) return unit.name;
    if (unit?.code) return unit.code;
    return 'Unit Saya';
});
const unitCardHref = computed(() => (isMemberRole.value ? '/member/profile' : ''));

// Dues progress calculations
const duesCollected = computed(() => {
    if (!duesSummary.value) return 0;
    return duesSummary.value.paid_amount || duesSummary.value.paid * 50000 || 0;
});

const duesTarget = computed(() => {
    if (!duesSummary.value) return 500000000;
    return duesSummary.value.target_amount || duesSummary.value.total * 50000 || 500000000;
});

const duesProgress = computed(() => {
    if (duesTarget.value <= 0) return 0;
    return Math.round((duesCollected.value / duesTarget.value) * 100);
});

// Recent activities - from alerts or mock data
const recentActivities = computed(() => {
    const alerts = pg.alerts || {};
    const activities = [];

    if (alerts.documents_missing > 0) {
        activities.push({
            type: 'warning',
            message: `${alerts.documents_missing} anggota belum upload dokumen`,
            time: 'Baru saja'
        });
    }
    if (alerts.mutations_sla_breach > 0) {
        activities.push({
            type: 'error',
            message: `${alerts.mutations_sla_breach} mutasi melewati SLA`,
            time: 'Hari ini'
        });
    }

    // Add some general activities
    activities.push(
        { type: 'success', message: "Member 'John Doe' added", time: '2 jam yang lalu' },
        { type: 'info', message: 'Mutation request from Unit 101 approved', time: '3 jam yang lalu' },
        { type: 'info', message: 'New update request from Unit 304', time: '5 jam yang lalu' }
    );

    return activities.slice(0, 5);
});

// Recent mutations - from dashboard data or mock
const recentMutations = computed(() => {
    const mutations = pg.dashboard?.mutations || {};

    // Mock data for display - in real scenario this would come from backend
    return [
        { id: 3049001, member_name: 'John Doe', type: 'Management', date: '2022-07-24', status: 'status', status_label: 'Status' },
        { id: 3045002, member_name: 'John Doe', type: 'Mutation', date: '2022-07-22', status: 'approved', status_label: 'Approved' },
        { id: 3045003, member_name: 'John Doe', type: 'Mutation', date: '2023-07-23', status: 'status', status_label: 'Status' },
    ];
});

// Format helpers
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Activity icons
const getActivityIcon = (type) => {
    const icons = {
        success: {
            render() {
                return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
                    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' })
                ]);
            }
        },
        info: {
            render() {
                return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
                    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' })
                ]);
            }
        },
        warning: {
            render() {
                return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
                    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' })
                ]);
            }
        },
        error: {
            render() {
                return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
                    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
                ]);
            }
        },
    };
    return icons[type] || icons.info;
};

const getActivityIconClass = (type) => {
    const classes = {
        success: 'text-green-500',
        info: 'text-blue-500',
        warning: 'text-amber-500',
        error: 'text-red-500',
    };
    return classes[type] || classes.info;
};
</script>
