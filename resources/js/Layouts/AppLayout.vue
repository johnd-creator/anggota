<template>
  <div class="min-h-screen bg-neutral-100 flex">
    <!-- Sidebar (Desktop) -->
    <aside class="hidden md:flex flex-col w-64 fixed inset-y-0 z-50" style="background-color: #1A2B63;">
      <!-- Logo Section -->
      <div class="flex items-center gap-3 h-16 px-4 border-b" style="border-color: #2E4080;">
        <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
          <img src="/img/logo.png" alt="Logo" class="w-8 h-8 object-contain" />
        </div>
        <span class="text-xl font-bold text-white">SP-PIPS</span>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <!-- Dashboard -->
        <Link href="/dashboard" :class="menuItemClass('/dashboard')">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
          </svg>
          <span>Dashboard</span>
        </Link>

        <!-- Admin Aspirations -->
        <template v-if="isAdminOrUnit">
          <Link href="/admin/aspirations" :class="menuItemClass('/admin/aspirations')">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
            </svg>
            <span>Aspirasi Anggota</span>
            <span v-if="$page.props.counters?.aspirations_pending" class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">{{ $page.props.counters.aspirations_pending }}</span>
          </Link>
        </template>

        <!-- Surat Section -->
        <template v-if="isSuperAdmin || isAdminUnit || isAdminPusat || isMember || isTreasurer">
          <button @click="toggleSection('letters')" :class="sectionHeaderClass('letters')">
            <div class="flex items-center gap-3">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <span>Surat</span>
            </div>
            <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': expandedSections.letters }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="expandedSections.letters" class="ml-6 space-y-1">
            <Link href="/letters/inbox" :class="subMenuItemClass('/letters/inbox')">Kotak Masuk</Link>
            <Link v-if="isSuperAdmin || isAdminUnit || isAdminPusat" href="/letters/outbox" :class="subMenuItemClass('/letters/outbox')">Surat Keluar</Link>
            <Link v-if="isSuperAdmin || canApproveLetters" href="/letters/approvals" :class="subMenuItemClass('/letters/approvals')">Perlu Persetujuan</Link>
          </div>
        </template>

        <!-- Financials Section -->
        <template v-if="isSuperAdmin || isTreasurer || isAdminUnit">
          <button @click="toggleSection('financials')" :class="sectionHeaderClass('financials')">
            <div class="flex items-center gap-3">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Financials</span>
            </div>
            <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': expandedSections.financials }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="expandedSections.financials" class="ml-6 space-y-1">
            <Link v-if="isSuperAdmin || isTreasurer" href="/finance/categories" :class="subMenuItemClass('/finance/categories')">Kategori</Link>
            <Link href="/finance/ledgers" :class="subMenuItemClass('/finance/ledgers')">Transaksi</Link>
            <Link href="/finance/dues" :class="subMenuItemClass('/finance/dues')">Iuran Bulanan</Link>
          </div>
        </template>

        <!-- Master Data + Admin Workflows -->
        <template v-if="isAdminOrUnit">
          <button @click="toggleSection('members')" :class="sectionHeaderClass('members')">
            <div class="flex items-center gap-3">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span>Master Data</span>
            </div>
            <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': expandedSections.members }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="expandedSections.members" class="ml-6 space-y-1">
            <Link href="/admin/members" :class="subMenuItemClass('/admin/members')">Daftar Anggota</Link>
            <Link href="/admin/units" :class="subMenuItemClass('/admin/units')">Unit Pembangkit</Link>
            <Link v-if="isSuperAdmin" href="/admin/union-positions" :class="subMenuItemClass('/admin/union-positions')">Jabatan Serikat</Link>
            <Link v-if="isSuperAdmin" href="/admin/roles" :class="subMenuItemClass('/admin/roles')">Role & Access</Link>
            <Link v-if="isSuperAdmin" href="/admin/aspiration-categories" :class="subMenuItemClass('/admin/aspiration-categories')">Kategori Aspirasi</Link>
            <Link v-if="isSuperAdmin" href="/admin/letter-categories" :class="subMenuItemClass('/admin/letter-categories')">Kategori Surat</Link>
          </div>

          <Link href="/admin/mutations" :class="menuItemClass('/admin/mutations')">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
            <span>Mutations</span>
            <span v-if="$page.props.counters?.mutations_pending" class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">{{ $page.props.counters.mutations_pending }}</span>
          </Link>

          <Link href="/admin/onboarding" :class="menuItemClass('/admin/onboarding')">
            <div class="flex items-center gap-3 w-full">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
              </svg>
              <span>Onboarding</span>
              <span v-if="$page.props.counters?.onboarding_pending" class="ml-auto bg-amber-500 text-white text-xs px-2 py-0.5 rounded-full">{{ $page.props.counters.onboarding_pending }}</span>
            </div>
          </Link>

          <Link href="/admin/updates" :class="menuItemClass('/admin/updates')">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>Update Requests</span>
            <span v-if="$page.props.counters?.updates_pending" class="ml-auto bg-amber-500 text-white text-xs px-2 py-0.5 rounded-full">{{ $page.props.counters.updates_pending }}</span>
          </Link>
        </template>

        <!-- Reports Section -->
        <template v-if="isAdminOrUnit">
          <button @click="toggleSection('reports')" :class="sectionHeaderClass('reports')">
            <div class="flex items-center gap-3">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              <span>Reports</span>
            </div>
            <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': expandedSections.reports }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="expandedSections.reports" class="ml-6 space-y-1">
            <Link href="/reports/growth" :class="subMenuItemClass('/reports/growth')">Pertumbuhan</Link>
            <Link href="/reports/mutations" :class="subMenuItemClass('/reports/mutations')">Laporan Mutasi</Link>
            <Link href="/reports/documents" :class="subMenuItemClass('/reports/documents')">Monitoring Dokumen</Link>
          </div>
        </template>
        <!-- Member Aspirations -->
        <template v-if="isMember || isTreasurer">
          <Link href="/member/aspirations" :class="menuItemClass('/member/aspirations')">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <span>Aspirasi</span>
          </Link>
        </template>

        <!-- Settings Section -->
        <button @click="toggleSection('settings')" :class="sectionHeaderClass('settings')">
          <div class="flex items-center gap-3">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Settings</span>
          </div>
          <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': expandedSections.settings }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <div v-show="expandedSections.settings" class="ml-6 space-y-1">
          <Link href="/settings" :class="subMenuItemClass('/settings')">Pengaturan</Link>
          <Link href="/help" :class="subMenuItemClass('/help')">Help Center</Link>
          <Link v-if="isSuperAdmin" href="/ops" :class="subMenuItemClass('/ops')">Ops Center</Link>
          <Link v-if="isAdminOrUnit" href="/audit-logs" :class="subMenuItemClass('/audit-logs')">Audit Log</Link>
          <Link v-if="isAdminOrUnit" href="/admin/activity-logs" :class="subMenuItemClass('/admin/activity-logs')">Activity Log</Link>
          <Link v-if="isSuperAdmin" href="/admin/sessions" :class="subMenuItemClass('/admin/sessions')">Active Sessions</Link>
          <Link v-if="isSuperAdmin" href="/ui/components" :class="subMenuItemClass('/ui/components')">UI Components</Link>
        </div>
      </nav>
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div class="md:hidden fixed inset-0 z-40 flex" v-if="mobileMenuOpen" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-black/50" aria-hidden="true" @click="mobileMenuOpen = false" />
      <div class="relative flex-1 flex flex-col max-w-xs w-full" style="background-color: #1A2B63;">
        <div class="absolute top-0 right-0 -mr-12 pt-2">
          <button type="button" class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none" @click="mobileMenuOpen = false">
            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <!-- Mobile Logo -->
        <div class="flex items-center gap-3 h-16 px-4 border-b" style="border-color: #2E4080;">
          <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
            <img src="/img/logo.png" alt="Logo" class="w-8 h-8 object-contain" />
          </div>
          <span class="text-xl font-bold text-white">SP-PIPS</span>
        </div>
	        <!-- Mobile Nav - simplified -->
	        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
	          <Link href="/dashboard" :class="menuItemClass('/dashboard')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
	            </svg>
	            <span>Dashboard</span>
	          </Link>
	          <Link v-if="isAdminOrUnit" href="/admin/aspirations" :class="menuItemClass('/admin/aspirations')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
	            </svg>
	            <span>Aspirasi Anggota</span>
	          </Link>
	          <Link v-if="isSuperAdmin || isAdminUnit || isAdminPusat || isMember || isTreasurer" href="/letters/inbox" :class="menuItemClass('/letters')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
	            </svg>
	            <span>Surat</span>
	          </Link>
	          <Link v-if="isMember || isTreasurer" href="/member/aspirations" :class="menuItemClass('/member/aspirations')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
	            </svg>
	            <span>Aspirasi</span>
	          </Link>
	          <Link v-if="isSuperAdmin || isTreasurer || isAdminUnit" href="/finance/ledgers" :class="menuItemClass('/finance/ledgers')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
	            </svg>
	            <span>Financials</span>
	          </Link>
	          <Link v-if="isAdminOrUnit" href="/admin/members" :class="menuItemClass('/admin/members')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
	            </svg>
	            <span>Master Data</span>
	          </Link>
	          <Link v-if="isAdminOrUnit" href="/admin/mutations" :class="menuItemClass('/admin/mutations')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
	            </svg>
	            <span>Mutations</span>
	          </Link>
	          <Link v-if="isAdminOrUnit" href="/admin/onboarding" :class="menuItemClass('/admin/onboarding')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
	            </svg>
	            <span>Onboarding</span>
	          </Link>
	          <Link v-if="isAdminOrUnit" href="/admin/updates" :class="menuItemClass('/admin/updates')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
	            </svg>
	            <span>Update Requests</span>
	          </Link>
	          <Link v-if="isAdminOrUnit" href="/reports/growth" :class="menuItemClass('/reports')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
	            </svg>
	            <span>Reports</span>
	          </Link>
	          <Link href="/settings" :class="menuItemClass('/settings')" @click="mobileMenuOpen = false">
	            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
	              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
	            </svg>
	            <span>Settings</span>
	          </Link>
	        </nav>
      </div>
    </div>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col md:pl-64 transition-all duration-200">
      <!-- Top Navigation Bar -->
      <header class="sticky top-0 z-30 bg-white border-b border-neutral-200 h-16">
        <div class="flex items-center justify-between h-full px-4 md:px-6">
          <!-- Mobile Menu Button -->
          <button type="button" class="md:hidden p-2 rounded-md text-neutral-500 hover:text-neutral-900 hover:bg-neutral-100" @click="mobileMenuOpen = true">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <!-- Page Title (Desktop) -->
          <h1 v-if="pageTitle" class="hidden md:block text-lg font-semibold text-neutral-900">{{ pageTitle }}</h1>

          <!-- Search Bar -->
          <div class="hidden md:flex flex-1 max-w-md mx-8">
            <div class="relative w-full">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input 
                type="text" 
                placeholder="Search..."
                class="w-full pl-10 pr-4 py-2 border border-neutral-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary-500 focus:border-transparent bg-neutral-50"
              />
            </div>
          </div>

          <!-- Optional Page Actions -->
          <div v-if="$slots.actions" class="hidden md:block mr-4">
            <slot name="actions" />
          </div>

          <!-- Right Side Actions -->
          <div class="flex items-center gap-3">
            <!-- Notification Bell -->
            <div class="relative">
              <button class="relative p-2 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors" @click="toggleNotifDropdown">
                <svg class="h-5 w-5 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span v-if="unreadCount" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] flex items-center justify-center text-[10px] font-bold bg-red-500 text-white rounded-full px-1">
                  {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
              </button>
              <!-- Notification Dropdown -->
              <div v-show="notifOpen" class="absolute right-0 mt-2 w-80 bg-white border border-neutral-200 rounded-xl shadow-xl py-2 z-50">
                <div class="px-4 py-2 border-b border-neutral-100">
                  <span class="text-sm font-semibold text-neutral-800">Notifikasi</span>
                </div>
                <div v-if="recent.length === 0" class="px-4 py-6 text-center text-sm text-neutral-500">
                  Tidak ada notifikasi baru
                </div>
                <div v-else class="max-h-64 overflow-y-auto">
                  <button
                    v-for="n in recent"
                    :key="n.id"
                    type="button"
                    class="w-full text-left px-4 py-3 hover:bg-neutral-50 border-b border-neutral-50 last:border-0"
                    @click="openNotification(n)"
                  >
                    <div class="flex items-start gap-2">
                      <span class="mt-1 w-2 h-2 rounded-full" :class="n.read_at ? 'bg-neutral-300' : 'bg-brand-primary-600'" />
                      <div class="flex-1">
                        <p class="text-sm text-neutral-800">{{ (n.data && n.data.message) || n.message }}</p>
                        <p class="text-xs text-neutral-500 mt-1">{{ relativeTime(n.created_at) }}</p>
                      </div>
                    </div>
                  </button>
                </div>
                <div class="px-4 py-2 border-t border-neutral-100 flex items-center justify-between">
                  <Link href="/notifications" class="text-sm text-brand-primary-600 hover:underline">Lihat semua</Link>
                  <button class="text-xs text-neutral-600 hover:text-brand-primary-600" @click="markAllReadAndClear">Tandai dibaca</button>
                </div>
              </div>
            </div>

            <!-- User Dropdown -->
            <div class="relative">
              <button class="flex items-center gap-2 p-1 rounded-lg hover:bg-neutral-100 transition-colors" @click="userMenuOpen = !userMenuOpen">
                <UserAvatar :src="$page.props.auth.user.avatar" :name="$page.props.auth.user.name" size="h-9 w-9" />
                <div class="hidden md:block text-left">
                  <p class="text-sm font-medium text-neutral-800 leading-tight">{{ $page.props.auth.user.name }}</p>
                  <p class="text-xs text-neutral-500 leading-tight">{{ $page.props.auth.user.role?.label || 'User' }}</p>
                </div>
                <svg class="hidden md:block h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div v-show="userMenuOpen" class="absolute right-0 mt-2 w-48 bg-white border border-neutral-200 rounded-xl shadow-xl py-1 z-50">
                <div class="px-4 py-2 border-b border-neutral-100">
                  <p class="text-sm font-medium text-neutral-800">{{ $page.props.auth.user.name }}</p>
                  <p class="text-xs text-neutral-500">{{ $page.props.auth.user.email }}</p>
                </div>
                <Link href="/settings" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50">Pengaturan</Link>
                <Link href="/member/profile" v-if="isMember || isTreasurer || hasMemberAssociation" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50">Profil Saya</Link>
                <Link href="/member/portal" v-if="isMember || isTreasurer || hasMemberAssociation" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50">KTA Digital</Link>
                <!-- Show unit badge for admins with unit association -->
                <div v-if="hasMemberAssociation && userUnitName" class="px-4 py-2 border-t border-neutral-100 text-xs text-neutral-500">
                  Anggota Unit: <span class="font-medium text-neutral-700">{{ userUnitName }}</span>
                </div>
                <button class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50" @click="doLogout">Logout</button>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1">
        <div class="py-6">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
            <div v-if="$slots.actions" class="md:hidden mb-4">
              <slot name="actions" />
            </div>
            <!-- Flash Messages -->
            <div v-if="flashSuccess" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm flex items-center gap-2">
              <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm flex items-center gap-2">
              <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ flashError }}
            </div>
            <slot />
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import UserAvatar from '@/Components/UI/UserAvatar.vue';

defineProps({
  pageTitle: {
    type: String,
    default: '',
  },
});

const mobileMenuOpen = ref(false);
const page = usePage();
const notifOpen = ref(false);
const userMenuOpen = ref(false);
const unreadCount = ref(page.props?.counters?.notifications_unread || 0);
const recent = ref([]);

// Collapsible sections state
const expandedSections = ref({
  members: false,
  mutations: false,
  onboarding: false,
  financials: false,
  reports: false,
  letters: false,
  settings: false,
});

function toggleSection(section) {
  expandedSections.value[section] = !expandedSections.value[section];
}

function syncExpandedToRoute(path) {
  expandedSections.value.members = /^\/admin\/(members|units|union-positions|roles|aspiration-categories|letter-categories)/.test(path);
  expandedSections.value.financials = /^\/finance\//.test(path);
  expandedSections.value.reports = /^\/reports\//.test(path);
  expandedSections.value.letters = /^\/letters\//.test(path);
  expandedSections.value.settings = /^\/(settings|help|ops|audit-logs|admin\/activity-logs|admin\/sessions|ui\/components)/.test(path);
}

function toggleNotifDropdown() {
  notifOpen.value = !notifOpen.value;
  userMenuOpen.value = false;
  if (notifOpen.value) {
    fetch('/notifications/recent')
      .then(r => r.json())
      .then(d => { 
        recent.value = d.items || []; 
        const ids = (recent.value || []).filter(x => !x.read_at).map(x => x.id);
        if (ids.length) {
          router.post('/notifications/read-batch', { ids }, {
            onSuccess() {
              unreadCount.value = Math.max(0, (unreadCount.value || 0) - ids.length);
            }
          });
        }
      })
      .catch(() => { recent.value = []; });
  }
}

function openNotification(n) {
  const link = n?.link || (n?.data && n.data.link) || null;
  notifOpen.value = false;
  if (link) {
    router.visit(link);
  } else {
    router.visit('/notifications');
  }
}

function markAllReadAndClear() {
  router.post('/notifications/read-all', {}, {
    onSuccess() {
      unreadCount.value = 0;
      recent.value = [];
      notifOpen.value = false;
    },
    onError() { console.error('Mark all read failed'); }
  });
}

const flashSuccess = ref(page.props?.flash?.success || '');
const flashError = ref(page.props?.flash?.error || '');

onMounted(() => {
  if (flashSuccess.value) {
    setTimeout(() => { flashSuccess.value = ''; }, 4000);
  }
  if (flashError.value) {
    setTimeout(() => { flashError.value = ''; }, 5000);
  }
  syncExpandedToRoute(page.url || window.location.pathname || '');
});

watch(
  () => page.url,
  (val) => {
    syncExpandedToRoute(val || window.location.pathname || '');
  }
);

function relativeTime(s) {
  if (!s) return '';
  const d = new Date(s);
  const now = new Date();
  const diff = Math.floor((now.getTime() - d.getTime()) / 1000);
  if (diff < 60) return `${diff} detik yang lalu`;
  const m = Math.floor(diff / 60);
  if (m < 60) return `${m} menit yang lalu`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h} jam yang lalu`;
  return d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

const roleName = computed(() => page.props?.auth?.user?.role?.name || '');
const isAdminOrUnit = computed(() => ['super_admin', 'admin_unit', 'admin_pusat'].includes(roleName.value));
const isSuperAdmin = computed(() => roleName.value === 'super_admin');
const isAdminUnit = computed(() => roleName.value === 'admin_unit');
const isAdminPusat = computed(() => roleName.value === 'admin_pusat');
const isTreasurer = computed(() => roleName.value === 'bendahara');
const isMember = computed(() => roleName.value === 'anggota');
const unionPositionName = computed(() => (page.props?.auth?.user?.union_position?.name || '').toLowerCase());
const canApproveLetters = computed(() => ['ketua', 'sekretaris'].includes(unionPositionName.value));
// Check if admin has member association (for super_admin/admin_pusat who are also members)
const hasMemberAssociation = computed(() => {
  const user = page.props?.auth?.user;
  return (isSuperAdmin.value || isAdminPusat.value) && user?.member_id;
});
const userUnitName = computed(() => page.props?.auth?.user?.organization_unit?.name || null);

function doLogout() {
  router.post('/logout', {}, { onFinish() { userMenuOpen.value = false; } });
}

// Menu item styling - using inline styles for colors
function menuItemClass(path) {
  const isActive = page.url === path || page.url.startsWith(path + '/');
  return [
    'flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors sidebar-menu-item',
    isActive ? 'sidebar-active' : 'sidebar-inactive'
  ].join(' ');
}

function subMenuItemClass(path) {
  const isActive = page.url === path || page.url.startsWith(path + '/');
  return [
    'block px-3 py-2 text-sm rounded-md transition-colors sidebar-menu-item',
    isActive ? 'sidebar-active' : 'sidebar-inactive'
  ].join(' ');
}

function sectionHeaderClass(section) {
  const hasActiveChild = {
    members: ['/admin/members', '/admin/units', '/admin/union-positions', '/admin/roles', '/admin/aspiration-categories', '/admin/letter-categories'].some(p => page.url.startsWith(p)),
    mutations: page.url.startsWith('/admin/mutations'),
    onboarding: page.url.startsWith('/admin/onboarding'),
    financials: ['/finance/categories', '/finance/ledgers', '/finance/dues'].some(p => page.url.startsWith(p)),
    reports: page.url.startsWith('/reports'),
    letters: page.url.startsWith('/letters'),
    settings: ['/settings', '/help', '/ops', '/audit-logs', '/admin/activity-logs', '/admin/sessions', '/ui/components'].some(p => page.url.startsWith(p)),
  };
  return [
    'flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium rounded-lg transition-colors sidebar-menu-item',
    hasActiveChild[section] ? 'sidebar-active' : 'sidebar-inactive'
  ].join(' ');
}
</script>
