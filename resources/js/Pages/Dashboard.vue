<template>
    <AppLayout page-title="Dashboard">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <Link href="/admin/units">
            <CardContainer padding="lg" shadow="sm" hoverable>
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-brand-primary-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-neutral-500 truncate">Total Unit</dt>
                            <dd class="text-lg font-medium text-neutral-900">{{ $page.props.counters?.units_total || 0 }}</dd>
                        </dl>
                    </div>
                </div>
            </CardContainer>
            </Link>

            <Link href="/admin/members">
            <CardContainer padding="lg" shadow="sm" hoverable>
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-brand-secondary-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-brand-secondary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-neutral-500 truncate">Total Seluruh Anggota</dt>
                            <dd class="text-lg font-medium text-neutral-900">{{ $page.props.counters?.members_total || 0 }}</dd>
                        </dl>
                    </div>
                </div>
            </CardContainer>
            </Link>

            <Link href="/admin/mutations">
            <CardContainer padding="lg" shadow="sm" hoverable>
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-status-warning-light rounded-md p-3">
                        <svg class="h-6 w-6 text-status-warning-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-neutral-500 truncate">Mutasi Pending</dt>
                            <!-- <p class="text-xs text-neutral-400">(semua unit)</p> -->
                            <dd class="text-lg font-medium text-neutral-900">{{ $page.props.counters?.mutations_pending || 0 }}</dd>
                        </dl>
                    </div>
                </div>
            </CardContainer>
            </Link>

            <Link href="/admin/onboarding">
            <CardContainer padding="lg" shadow="sm" hoverable>
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-brand-primary-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-neutral-500 truncate">Onboarding Pending</dt>
                            <!-- <p class="text-xs text-neutral-400">(semua unit)</p> -->
                            <dd class="text-lg font-medium text-neutral-900">{{ $page.props.counters?.onboarding_pending || 0 }}</dd>
                        </dl>
                    </div>
                </div>
            </CardContainer>
            </Link>

            <Link href="/admin/updates">
            <CardContainer padding="lg" shadow="sm" hoverable>
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-brand-secondary-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-brand-secondary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-neutral-500 truncate">Update Request Pending</dt>
                            <!-- <p class="text-xs text-neutral-400">(semua unit)</p> -->
                            <dd class="text-lg font-medium text-neutral-900">{{ $page.props.counters?.updates_pending || 0 }}</dd>
                        </dl>
                    </div>
                </div>
            </CardContainer>
            </Link>
            <Link v-if="$page.props.auth.user.role?.name === 'admin_unit'" href="/admin/members">
            <CardContainer padding="lg" shadow="sm" hoverable>
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-brand-secondary-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-brand-secondary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-neutral-500 truncate">Anggota di Unit Saya</dt>
                            <dd class="text-lg font-medium text-neutral-900">{{ $page.props.counters?.members_unit_total || 0 }}</dd>
                        </dl>
                    </div>
                </div>
            </CardContainer>
            </Link>
        </div>


    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const page = usePage();
const pg = page.props || {};
onMounted(() => {});
</script>
