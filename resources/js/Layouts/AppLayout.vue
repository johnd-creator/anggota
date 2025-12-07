<template>
  <div class="min-h-screen bg-neutral-50 flex">
    <!-- Sidebar (Desktop) -->
    <aside class="hidden md:flex flex-col w-64 bg-white border-r border-neutral-200 fixed inset-y-0 z-50">
      <div class="flex items-center justify-center h-16 border-b border-neutral-200">
        <h1 class="text-xl font-bold text-brand-primary-600">SP-PIPS</h1>
      </div>

      <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
        <Link href="/dashboard" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url === '/dashboard' ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
          <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
          </svg>
          Dashboard
        </Link>

        <template v-if="isAdminOrUnit">
          <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Master Data</p>
          </div>
          <Link href="/admin/units" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/admin/units') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            Unit Pembangkit
          </Link>
          <Link v-if="isSuperAdmin" href="/admin/union-positions" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/admin/union-positions') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Jabatan Serikat
          </Link>
          <Link v-if="isSuperAdmin" href="/admin/roles" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/admin/roles') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.1.9-2 2-2h6M4 15h6a2 2 0 002-2V5" />
            </svg>
            Role & Access
          </Link>

          <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Keanggotaan</p>
          </div>
          <Link href="/admin/members" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/admin/members') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Daftar Anggota
          </Link>
          <Link href="/admin/updates" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/admin/updates') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Permintaan Update
            <span v-if="$page.props.counters?.updates_pending" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-status-warning-light text-status-warning-dark">{{ $page.props.counters.updates_pending }}</span>
          </Link>

          <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Onboarding</p>
          </div>
          <Link href="/admin/onboarding" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/admin/onboarding') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            Onboarding Reguler
            <span v-if="$page.props.counters?.onboarding_pending" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-status-warning-light text-status-warning-dark">{{ $page.props.counters.onboarding_pending }}</span>
          </Link>

          <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Mutasi</p>
          </div>
          <Link href="/admin/mutations" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/admin/mutations') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Mutasi Anggota
            <span v-if="$page.props.counters?.mutations_pending" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-status-warning-light text-status-warning-dark">{{ $page.props.counters.mutations_pending }}</span>
          </Link>
        </template>

        <template v-if="isSuperAdmin || isTreasurer || isAdminUnit">
          <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Keuangan</p>
          </div>
          <Link href="/finance/categories" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/finance/categories') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            Kategori Keuangan
          </Link>
          <Link href="/finance/ledgers" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/finance/ledgers') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            Transaksi Keuangan
          </Link>
          <Link href="/finance/dues" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/finance/dues') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m4-3h-7m0 0l3-3m-3 3l3 3"/></svg>
            Iuran Bulanan
          </Link>
        </template>

        <template v-if="isMember || isTreasurer">
          <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Anggota</p>
          </div>
          <Link href="/member/profile" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/member/profile') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Profil Saya
          </Link>
          <Link href="/member/portal" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/member/portal') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            Kartu Digital
          </Link>
          <Link href="/notifications" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/notifications') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            Notifikasi
            <span v-if="$page.props.counters?.notifications_unread" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-brand-primary-600 text-white">{{ $page.props.counters.notifications_unread }}</span>
          </Link>
        </template>

        <template v-if="isAdminOrUnit">
          <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Sistem</p>
          </div>
          <Link v-if="isSuperAdmin" href="/ops" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/ops') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-9 4h12M9 8h6"/></svg>
            Ops Center
          </Link>
          <Link href="/audit-logs" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url === '/audit-logs' ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            Audit Log
          </Link>
          <Link href="/admin/activity-logs" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/admin/activity-logs') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Activity Log
          </Link>
          <Link v-if="isSuperAdmin" href="/admin/sessions" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/admin/sessions') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 01-2 2h-2a2 2 0 01-2-2M7 7a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 01-2 2H9a2 2 0 01-2-2" />
            </svg>
            Active Sessions
          </Link>
          <Link href="/notifications" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/notifications') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            Notification Center
            <span v-if="$page.props.counters?.notifications_unread" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-brand-primary-600 text-white">{{ $page.props.counters.notifications_unread }}</span>
          </Link>

          <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Pelaporan</p>
          </div>
          <Link href="/reports/growth" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/reports/growth') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Laporan Pertumbuhan</Link>
          <Link href="/reports/mutations" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/reports/mutations') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Laporan Mutasi</Link>
          <Link href="/reports/documents" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/reports/documents') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Monitoring Dokumen</Link>

          <div class="pt-4 pb-2">
            <p class="px-4 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Tools</p>
          </div>
          <Link v-if="isSuperAdmin" href="/ui/components" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url === '/ui/components' ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
            <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
            </svg>
            UI Components
          </Link>
          <Link href="/help" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/help') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Help Center</Link>
          <Link href="/settings" :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors', $page.url.startsWith('/settings') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Pengaturan</Link>
        </template>


      </nav>

      <div class="p-4 border-t border-neutral-200">
        <div class="flex items-center">
          <UserAvatar :src="$page.props.auth.user.avatar" :name="$page.props.auth.user.name" />
          <div class="ml-3">
            <p class="text-sm font-medium text-neutral-700">{{ $page.props.auth.user.name }}</p>
            <p class="text-xs text-neutral-500">{{ $page.props.auth.user.role ? $page.props.auth.user.role.label : 'Pengguna' }}</p>
          </div>
        </div>
      </div>
    </aside>

    <!-- Mobile Header & Sidebar Overlay -->
    <div class="md:hidden fixed inset-0 z-40 flex" v-if="mobileMenuOpen" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-neutral-600 bg-opacity-75" aria-hidden="true" @click="mobileMenuOpen = false"></div>
      <div class="relative flex-1 flex flex-col max-w-xs w-full bg-white">
        <div class="absolute top-0 right-0 -mr-12 pt-2">
          <button type="button" class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" @click="mobileMenuOpen = false">
            <span class="sr-only">Close sidebar</span>
            <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
          <div class="flex-shrink-0 flex items-center px-4">
            <h1 class="text-xl font-bold text-brand-primary-600">SIM-SP</h1>
          </div>
          <nav class="mt-5 px-2 space-y-1">
            <Link href="/dashboard" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url === '/dashboard' ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">
              <svg class="mr-4 h-6 w-6 text-neutral-400 group-hover:text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
              </svg>
              Dashboard
            </Link>
            <template v-if="isAdminOrUnit">
              <div class="pt-4 pb-2">
                <p class="px-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Master Data</p>
              </div>
              <Link href="/admin/units" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/admin/units') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Master Unit</Link>
              <Link v-if="isSuperAdmin" href="/admin/union-positions" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/admin/union-positions') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Jabatan Serikat</Link>

              <div class="pt-4 pb-2">
                <p class="px-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Keanggotaan</p>
              </div>
              <Link href="/admin/members" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/admin/members') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Daftar Anggota</Link>
              <Link href="/admin/updates" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/admin/updates') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Permintaan Update Data <span v-if="$page.props.counters?.updates_pending" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-status-warning-light text-status-warning-dark">{{ $page.props.counters.updates_pending }}</span></Link>

              <div class="pt-4 pb-2">
                <p class="px-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Onboarding</p>
              </div>
              <Link href="/admin/onboarding" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/admin/onboarding') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Onboarding Reguler <span v-if="$page.props.counters?.onboarding_pending" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-status-warning-light text-status-warning-dark">{{ $page.props.counters.onboarding_pending }}</span></Link>

              <div class="pt-4 pb-2">
                <p class="px-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Mutasi</p>
              </div>
              <Link href="/admin/mutations" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/admin/mutations') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Mutasi Anggota <span v-if="$page.props.counters?.mutations_pending" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-status-warning-light text-status-warning-dark">{{ $page.props.counters.mutations_pending }}</span></Link>
            </template>

            <template v-if="isSuperAdmin || isTreasurer || isAdminUnit">
              <div class="pt-4 pb-2">
                <p class="px-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Keuangan</p>
              </div>
              <Link href="/finance/categories" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/finance/categories') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Kategori Keuangan</Link>
              <Link href="/finance/ledgers" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/finance/ledgers') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Transaksi Keuangan</Link>
            </template>

            <template v-if="isMember || isTreasurer">
              <div class="pt-4 pb-2">
                <p class="px-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Anggota</p>
              </div>
              <Link href="/member/profile" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/member/profile') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Profil Saya</Link>
              <Link href="/member/portal" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/member/portal') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Kartu Digital</Link>
              <Link href="/notifications" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/notifications') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Notifikasi <span v-if="$page.props.counters?.notifications_unread" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-brand-primary-600 text-white">{{ $page.props.counters.notifications_unread }}</span></Link>
            </template>

            <template v-if="isAdminOrUnit">
              <div class="pt-4 pb-2">
                <p class="px-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Sistem</p>
              </div>
              <Link v-if="isSuperAdmin" href="/ops" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/ops') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Ops Center</Link>
              <Link href="/audit-logs" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url === '/audit-logs' ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Audit Log</Link>
              <Link href="/admin/activity-logs" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/admin/activity-logs') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Activity Log</Link>
              <Link v-if="isSuperAdmin" href="/admin/sessions" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/admin/sessions') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Active Sessions</Link>
              <Link href="/notifications" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/notifications') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Notification Center <span v-if="$page.props.counters?.notifications_unread" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-brand-primary-600 text-white">{{ $page.props.counters.notifications_unread }}</span></Link>

              <div class="pt-4 pb-2">
                <p class="px-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Pelaporan</p>
              </div>
              <Link href="/reports/growth" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/reports/growth') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Laporan Pertumbuhan</Link>
              <Link href="/reports/mutations" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/reports/mutations') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Laporan Mutasi</Link>
              <Link href="/reports/documents" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/reports/documents') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Monitoring Dokumen</Link>

              <div class="pt-4 pb-2">
                <p class="px-2 text-xs font-semibold text-neutral-400 uppercase tracking-wider">Tools</p>
              </div>
              <Link v-if="isSuperAdmin" href="/ui/components" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url === '/ui/components' ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">UI Components</Link>
              <Link href="/help" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/help') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Help Center</Link>
              <Link href="/settings" :class="['group flex items-center px-2 py-2 text-base font-medium rounded-md', $page.url.startsWith('/settings') ? 'bg-brand-primary-50 text-brand-primary-700' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900']">Pengaturan</Link>
            </template>
          </nav>
        </div>
      </div>
    </div>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col md:pl-64 transition-all duration-200">
      <!-- Mobile Header -->
      <div class="sticky top-0 z-10 md:hidden pl-1 pt-1 sm:pl-3 sm:pt-3 bg-white border-b border-neutral-200">
        <button type="button" class="-ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-md text-neutral-500 hover:text-neutral-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-primary-500" @click="mobileMenuOpen = true">
          <span class="sr-only">Open sidebar</span>
          <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>

      <!-- Page Content -->
      <main class="flex-1">
        <div class="py-6">
          <!-- Enhanced Page Header with border -->
          <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
            <div class="bg-white border-b-2 border-neutral-200 shadow-sm px-6 py-5 mb-6 -mx-4 sm:-mx-6 md:-mx-8">
              <div class="flex items-center justify-between">
                <h1 v-if="pageTitle" class="text-2xl font-bold text-neutral-900">{{ pageTitle }}</h1>
                <div class="flex items-center gap-3">
                  <slot name="actions" />
                  <button class="relative inline-flex items-center justify-center h-10 w-10 rounded-full bg-neutral-100 hover:bg-neutral-200 transition-colors" @click="toggleNotifDropdown">
                    <svg class="h-5 w-5 text-neutral-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                    <span v-if="unreadCount" class="absolute -top-1 -right-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] bg-brand-primary-600 text-white">{{ unreadCount }}</span>
                  </button>
                  <div v-show="notifOpen" class="absolute right-16 mt-2 w-80 bg-white border border-neutral-200 rounded-lg shadow-xl py-2 z-50">
                    <div class="px-3 py-1 text-sm font-medium text-neutral-700">Notifikasi Terbaru</div>
                    <div v-if="recent.length===0" class="px-3 py-2 text-sm text-neutral-500">Tidak ada notifikasi baru</div>
                    <div v-else>
                      <div v-for="n in recent" :key="n.id" class="px-3 py-2 hover:bg-neutral-50">
                        <div class="text-sm text-neutral-900">{{ (n.data && n.data.message) || n.message }}</div>
                        <div class="text-xs text-neutral-500">{{ relativeTime((n.created_at)) }}</div>
                      </div>
                    </div>
                    <div class="px-3 py-2 flex items-center justify-between">
                      <Link href="/notifications" class="text-brand-primary-700 text-sm">Lihat semua</Link>
                      <button class="text-sm text-neutral-700 hover:text-brand-primary-700" @click="markAllReadAndClear">Tandai semua dibaca</button>
                    </div>
                  </div>

                  <div class="relative">
                  <button class="h-10 w-10 rounded-full overflow-hidden border-2 border-neutral-200 hover:border-brand-primary-400 transition-colors" @click="userMenuOpen = !userMenuOpen" aria-haspopup="menu">
                      <UserAvatar :src="$page.props.auth.user.avatar" :name="$page.props.auth.user.name" size="h-full w-full" />
                    </button>
                    <div v-show="userMenuOpen" class="absolute right-0 mt-2 w-48 bg-white border border-neutral-200 rounded-lg shadow-xl py-1">
                      <Link href="/settings" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 transition-colors">Pengaturan</Link>
                      <button class="block w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50 transition-colors" @click="doLogout">Logout</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 mt-4">
            <div v-if="flashSuccess" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 text-sm">
              {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-2 text-sm">
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
import { ref, computed, watch, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import UserAvatar from '@/Components/UI/UserAvatar.vue';

defineProps({
  pageTitle: {
    type: String,
    default: '',
  },
});

const mobileMenuOpen = ref(false);
// bahasa dropdown dihapus

const page = usePage();
const notifOpen = ref(false);
const unreadCount = ref(page.props?.counters?.notifications_unread || 0);
const recent = ref([]);
function toggleNotifDropdown(){
  notifOpen.value = !notifOpen.value;
  if (notifOpen.value) {
    fetch('/notifications/recent').then(r => r.json()).then(d => { recent.value = d.items || []; }).catch(() => { recent.value = []; });
  }
}
function markAllReadAndClear(){
  router.post('/notifications/read-all', {}, { onSuccess(){ unreadCount.value = 0; recent.value = []; notifOpen.value=false; }, onError(){ console.error('Mark all read failed'); } });
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
});
function relativeTime(s){
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
const isAdminOrUnit = computed(() => ['super_admin','admin_unit'].includes(roleName.value));
const isSuperAdmin = computed(() => roleName.value === 'super_admin');
const isAdminUnit = computed(() => roleName.value === 'admin_unit');
const isTreasurer = computed(() => roleName.value === 'bendahara');
const isMember = computed(() => roleName.value === 'anggota');
// quick view notifikasi dihapus
const userMenuOpen = ref(false);
function doLogout(){ router.post('/logout', {}, { onFinish(){ userMenuOpen.value=false; } }); }
</script>
