<template>
    <AppLayout page-title="Dashboard">

        <!-- Pinned Announcements -->
        <div v-if="$page.props.features?.announcements !== false && $page.props.announcements_pinned && $page.props.announcements_pinned.length > 0" class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Pengumuman Penting</h3>
                <Link href="/announcements" class="text-sm text-indigo-600 hover:text-indigo-900">Lihat Semua</Link>
            </div>
            <div class="space-y-4">
                <div v-for="item in $page.props.announcements_pinned" :key="item.id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-indigo-500 p-6">
                    <div class="flex justify-between items-start">
                        <div class="w-full">
                            <div class="flex items-center gap-2 mb-2">
                                <span v-if="item.scope_type === 'global_all'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 uppercase">Global</span>
                                <span v-else-if="item.scope_type === 'global_officers'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 uppercase">Pengurus</span>
                                <span v-else-if="item.scope_type === 'unit'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-800 uppercase">
                                    {{ item.organization_unit_name || 'Unit' }}
                                </span>
                                <span class="text-xs text-gray-500">{{ new Date(item.created_at).toLocaleDateString('id-ID') }}</span>
                            </div>
                            <h4 class="font-bold text-gray-800 text-lg">{{ item.title }}</h4>
                            <p class="text-sm text-gray-600 mt-2">{{ item.body_snippet }}</p>
                            
                            <div v-if="item.attachments && item.attachments.length > 0" class="mt-4 flex flex-wrap gap-2">
                                <a 
                                    v-for="file in item.attachments" 
                                    :key="file.id" 
                                    :href="file.download_url" 
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-gray-50 border border-gray-200 text-xs text-gray-700 hover:bg-gray-100 transition-colors"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    {{ file.original_name }}
                                </a>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="ml-4 text-gray-400 hover:text-gray-600 rounded p-1 hover:bg-gray-50"
                            aria-label="Tutup pengumuman"
                            @click="dismissPinnedAnnouncement(item.id)"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

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
                title="Total Anggota"
                :value="$page.props.counters?.members_total || 0"
                icon="users"
                iconColor="amber"
                badgeText="Se-Indonesia"
                badgeColor="amber"
                :href="canOpenTotalMembers ? '/admin/members' : ''"
            />
            <StatCard
                v-if="lettersSummary"
                title="Kotak Masuk"
                :value="lettersSummary.unread || 0"
                icon="inbox"
                iconColor="blue"
                badgeText="Belum dibaca"
                badgeColor="blue"
                href="/letters/inbox"
            />
            <StatCard
                v-if="lettersSummary"
                title="Surat Masuk"
                :value="lettersSummary.this_month || 0"
                icon="mail"
                iconColor="green"
                badgeText="Bulan ini"
                badgeColor="green"
                href="/letters/inbox"
            />
            <StatCard v-if="lettersSummary && showApprovalsCard"
                title="Perlu Persetujuan"
                :value="lettersSummary.approvals || 0"
                icon="check"
                iconColor="green"
                badgeText="Menunggu approval"
                badgeColor="green"
                href="/letters/approvals"
            />
            <StatCard
                v-if="lettersSummary"
                title="Urgensi Tinggi"
                :value="lettersSummary.urgent || 0"
                icon="bolt"
                iconColor="red"
                badgeText="Segera & kilat"
                badgeColor="red"
                href="/letters/inbox"
            />
            <StatCard v-if="showAdminQueues"
                title="Mutasi Pending"
                :value="$page.props.counters?.mutations_pending || 0"
                icon="transfer"
                iconColor="red"
                badgeText="Approve mutasi"
                badgeColor="red"
                href="/admin/mutations"
            />
            <StatCard v-if="showAdminQueues"
                title="Onboarding Pending"
                :value="$page.props.counters?.onboarding_pending || 0"
                icon="user-plus"
                iconColor="amber"
                badgeText="Approve onboarding"
                badgeColor="amber"
                href="/admin/onboarding"
            />
            <StatCard v-if="showAdminQueues"
                title="Update Request"
                :value="$page.props.counters?.updates_pending || 0"
                icon="refresh"
                iconColor="blue"
                badgeText="Approve updates"
                badgeColor="blue"
                href="/admin/updates"
            />
            <StatCard v-if="showAdminQueues"
                title="Aspirasi Baru"
                :value="$page.props.counters?.aspirations_pending || 0"
                icon="chat-alt"
                iconColor="purple"
                badgeText="Needs Attention"
                badgeColor="purple"
                href="/admin/aspirations"
            />
            <StatCard
                v-if="employmentInfo && !isSuperAdmin"
                title="Masa Kerja"
                :value="employmentInfo.duration_string"
                icon="clock"
                iconColor="green"
                :badgeText="employmentInfo.join_date"
                badgeColor="green"
                href="/member/profile"
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

	        <div v-if="isMemberRole" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
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
            <!-- Personal Dues Card -->
            <div v-if="myDues && $page.props.features?.finance !== false" class="bg-white rounded-xl shadow-sm border border-neutral-100 p-6 flex flex-col gap-2">
                <p class="text-sm text-neutral-500">Iuran Saya</p>
                <h3 class="text-lg font-semibold text-neutral-900">
                    <span :class="myDues.current_status === 'paid' ? 'text-green-600' : 'text-red-600'">
                        {{ myDues.current_status === 'paid' ? 'Sudah Bayar ✓' : 'Belum Bayar' }}
                    </span>
                </h3>
                <div class="text-sm text-neutral-500">
                    <template v-if="myDues.unpaid_count > 0">
                        <div class="mb-1">{{ myDues.unpaid_count }} bulan tunggakan:</div>
                         <div class="flex flex-wrap gap-1">
                            <span v-for="p in myDues.unpaid_periods" :key="p" class="px-2 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700 border border-red-200">
                                {{ new Date(p + '-01').toLocaleDateString('id-ID', { month: 'short', year: 'numeric' }) }}
                            </span>
                             <span v-if="myDues.unpaid_count > myDues.unpaid_periods.length" class="px-2 py-0.5 rounded text-[10px] text-neutral-500">
                                +{{ myDues.unpaid_count - myDues.unpaid_periods.length }} lainnya
                            </span>
                        </div>
                    </template>
                    <template v-else>
                        Tidak ada tunggakan
                    </template>
                </div>
                <Link href="/member/dues" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-primary-600 hover:underline mt-2">
                    Lihat Riwayat
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </Link>
            </div>
        </div>


        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6" v-if="showAdminQueues">
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

        <!-- Finance Dashboard Section -->
        <div v-if="finance" class="mb-8 space-y-6">
            <!-- Top Row: Balance & Dues -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Current Balance Card -->
                <div class="rounded-xl shadow-sm border border-neutral-100 overflow-hidden relative bg-blue-600">
                    <div class="absolute inset-0 bg-blue-600"></div>
                    <!-- Pattern overlay -->
                    <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=\'20\' height=\'20\' viewBox=\'0 0 20 20\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\' fill-rule=\'evenodd\'%3E%3Ccircle cx=\'3\' cy=\'3\' r=\'3\'/%3E%3Ccircle cx=\'13\' cy=\'13\' r=\'3\'/%3E%3C/g%3E%3C/svg%3E');"></div>
                    
                    <div class="relative p-6 text-white flex flex-col h-full justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-blue-100 font-medium text-sm uppercase tracking-wider">Saldo Saat Ini</h3>
                                <span v-if="finance.unit_name" class="px-2 py-0.5 rounded text-xs font-semibold bg-white/20 text-white border border-white/20">
                                    {{ finance.unit_name }}
                                </span>
                            </div>
                            <div class="text-3xl font-bold mb-1">{{ formatCurrency(finance.balance) }}</div>
                            <div class="text-blue-100 text-sm flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                <span>Update terakhir hari ini</span>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <Link href="/finance/ledgers" class="inline-flex items-center px-4 py-2 bg-white text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition-colors shadow-sm">
                                Kelola Keuangan
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Dues Progress Card -->
                <div class="bg-white rounded-xl shadow-sm border border-neutral-100 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-neutral-900">Progres Iuran Bulan Ini</h3>
                            <Link href="/finance/dues" class="text-sm font-medium text-brand-primary-600 hover:text-brand-primary-700 hover:underline">Detail</Link>
                        </div>
                        
                        <div class="mb-2 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-neutral-900">{{ formatCurrency(duesCollected) }}</span>
                            <span class="text-sm text-neutral-500">terkumpul dari target {{ formatCurrency(duesTarget) }}</span>
                        </div>

                        <div class="w-full bg-neutral-100 rounded-full h-3 mb-4">
                            <div class="bg-brand-primary-600 h-3 rounded-full transition-all duration-500" :style="{ width: `${duesProgress}%` }"></div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div class="p-3 bg-green-50 rounded-lg border border-green-100">
                                <span class="block text-xs text-green-600 mb-1">Sudah Bayar</span>
                                <span class="text-lg font-bold text-green-700">{{ duesSummary?.paid || 0 }} <span class="text-xs font-normal">Anggota</span></span>
                            </div>
                            <div class="p-3 bg-red-50 rounded-lg border border-red-100">
                                <span class="block text-xs text-red-600 mb-1">Belum Bayar</span>
                                <span class="text-lg font-bold text-red-700">{{ duesSummary?.unpaid || 0 }} <span class="text-xs font-normal">Anggota</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Chart & Recent -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Financial Overview Chart -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-neutral-100 p-6">
                    <h3 class="text-lg font-semibold text-neutral-900 mb-6">Overview Pemasukan & Pengeluaran</h3>
                    
                    <!-- Simple CSS/SVG Chart Implementation -->
                    <div class="h-64 relative flex items-end justify-between gap-2 px-2">
                        <!-- Y-Axis Grid Lines (Simplified) -->
                        <div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-10">
                            <div class="border-t border-neutral-900 w-full"></div>
                            <div class="border-t border-neutral-900 w-full"></div>
                            <div class="border-t border-neutral-900 w-full"></div>
                            <div class="border-t border-neutral-900 w-full"></div>
                            <div class="border-t border-neutral-900 w-full"></div>
                        </div>

                        <template v-for="(stat, index) in finance.ytd" :key="index">
                            <div class="flex-1 flex flex-col justify-end gap-1 h-full group relative">
                                <!-- Tooltip -->
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-10 bg-neutral-800 text-white text-xs rounded p-2 whitespace-nowrap shadow-lg">
                                    <div class="font-bold">{{ stat.month }}</div>
                                    <div class="text-green-300">In: {{ formatShortCurrency(stat.income) }}</div>
                                    <div class="text-red-300">Out: {{ formatShortCurrency(stat.expense) }}</div>
                                </div>
                                
                                <!-- Bars -->
                                <div class="w-full flex gap-0.5 h-full items-end">
                                    <div class="flex-1 bg-green-500 hover:bg-green-600 rounded-t transition-all relative min-h-[4px]" :style="{ height: `${getBarHeight(stat.income)}%` }"></div>
                                    <div class="flex-1 bg-red-500 hover:bg-red-600 rounded-t transition-all relative min-h-[4px]" :style="{ height: `${getBarHeight(stat.expense)}%` }"></div>
                                </div>
                                
                                <!-- X-Axis Label -->
                                <div class="text-[10px] text-neutral-500 text-center truncate w-full mt-2">{{ stat.month.split(' ')[0] }}</div>
                            </div>
                        </template>
                    </div>
                    <div class="mt-4 flex items-center justify-center gap-4 text-xs font-medium">
                        <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-green-500"></span> Pemasukan</div>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-red-500"></span> Pengeluaran</div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="bg-white rounded-xl shadow-sm border border-neutral-100 p-0 flex flex-col">
                    <div class="p-6 border-b border-neutral-100 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">Transaksi Terakhir</h3>
                        <Link href="/finance/ledgers" class="text-xs font-medium text-brand-primary-600 hover:underline">Lihat Semua</Link>
                    </div>
                    <div class="flex-1 overflow-auto max-h-[400px]">
                         <table class="min-w-full divide-y divide-neutral-100">
                             <tbody class="divide-y divide-neutral-100">
                                 <tr v-for="tx in finance.recent" :key="tx.id" class="hover:bg-neutral-50 group transition-colors">
                                     <td class="px-6 py-4">
                                         <div class="flex justify-between items-start mb-1">
                                             <span class="text-sm font-medium text-neutral-900 group-hover:text-brand-primary-600 transition-colors">{{ tx.description }}</span>
                                             <span class="text-sm font-bold" :class="tx.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                                {{ tx.type === 'income' ? '+' : '-' }} {{ formatCurrency(tx.amount) }}
                                             </span>
                                         </div>
                                         <div class="flex justify-between items-center text-xs text-neutral-500">
                                            <span>{{ tx.date }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-neutral-100 text-neutral-600 capitalize">{{ tx.type }}</span>
                                         </div>
                                     </td>
                                 </tr>
                                 <tr v-if="finance.recent.length === 0">
                                     <td class="px-6 py-8 text-center text-sm text-neutral-500">Belum ada transaksi</td>
                                 </tr>
                             </tbody>
                         </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Grid: Mutasi Pending & Recent Activity -->
        <div v-if="showAdminQueues" class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Recent Mutasi Pending Table (Admin Only) -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-neutral-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-neutral-100">
                    <h3 class="text-lg font-semibold text-neutral-900">Recent Mutasi Pending</h3>
                </div>
                
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
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

                <!-- Mobile Data Cards -->
                <div class="md:hidden space-y-3 p-4">
                    <DataCard
                        v-for="mutation in recentMutations"
                        :key="mutation.id"
                        :title="mutation.member_name"
                        :subtitle="`ID: ${mutation.id} • ${mutation.type}`"
                        :status="{ label: mutation.status_label, color: mutation.status }"
                        :meta="[
                            { label: 'Tanggal', value: formatDate(mutation.date) }
                        ]"
                    >
                    </DataCard>
                    <div v-if="recentMutations.length === 0" class="text-center py-4 text-sm text-neutral-500">
                        Tidak ada mutasi pending saat ini
                    </div>
                </div>
            </div>



            <!-- Mobile: Recent Activity Cards -->
            <div v-if="recentActivities.length > 0" class="md:hidden space-y-3">
                <h3 class="text-sm font-medium text-neutral-500 uppercase tracking-wide mb-2">Recent Activity</h3>
                <DataCard
                    v-for="(activity, index) in recentActivities"
                    :key="index"
                    :title="activity.message"
                    :status="activity.type"
                    :meta="[
                        { label: 'Waktu', value: activity.time }
                    ]"
                >
                    <template #actions>
                        <div :class="['w-2 h-2 rounded-full flex-shrink-0', getActivityDotClass(activity.type)]"></div>
                    </template>
                </DataCard>
            </div>
            <div v-if="recentActivities.length === 0" class="md:hidden text-center py-4 text-sm text-neutral-500">
                Belum ada aktivitas terbaru
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
                                <td class="px-4 py-2 text-sm">{{ $toTitleCase(m.full_name) }}</td>
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
import DataCard from '@/Components/Mobile/DataCard.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const page = usePage();
const pg = page.props || {};

const roleName = computed(() => pg.auth?.user?.role?.name || '');
const isMemberRole = computed(() => ['anggota', 'admin_unit', 'bendahara'].includes(roleName.value));
const isSuperAdmin = computed(() => roleName.value === 'super_admin');
const showAdminQueues = computed(() => ['super_admin', 'admin_unit', 'admin_pusat'].includes(roleName.value));
const canOpenTotalMembers = computed(() => !['anggota', 'bendahara'].includes(roleName.value));

const showUnpaidModal = ref(false);
const currentPeriod = new Date().toISOString().slice(0, 7);

function dismissPinnedAnnouncement(id) {
    router.post(`/announcements/${id}/dismiss`, {}, { preserveScroll: true });
}

// Dues data
	const duesSummary = computed(() => pg.dues_summary || null);
	const unpaidMembers = computed(() => pg.unpaid_members || []);
		const finance = computed(() => pg.finance || null);
		const employmentInfo = computed(() => pg.auth?.user?.employment_info || null);
	    const lettersSummary = computed(() => pg.letters || null);
		const myDues = computed(() => pg.my_dues || null);

// Chart helpers
const maxChartValue = computed(() => {
    if (!finance.value || !finance.value.ytd) return 1000;
    let max = 0;
    finance.value.ytd.forEach(d => {
        if (d.income > max) max = d.income;
        if (d.expense > max) max = d.expense;
    });
    return max || 1000;
});

const getBarHeight = (value) => {
    return Math.round((value / maxChartValue.value) * 100);
};

const formatShortCurrency = (value) => {
    if (value >= 1000000000) return (value / 1000000000).toFixed(1) + 'M';
    if (value >= 1000000) return (value / 1000000).toFixed(1) + 'jt';
    if (value >= 1000) return (value / 1000).toFixed(0) + 'rb';
    return value;
};

		const showUnitMembersCard = computed(() => ['admin_unit', 'bendahara', 'anggota'].includes(roleName.value));
const showApprovalsCard = computed(() => {
    if (roleName.value === 'super_admin') return true;
    return pg.auth?.user?.union_position && ['ketua', 'sekretaris'].includes((pg.auth?.user?.union_position?.name || '').toLowerCase());
});
const showDuesCard = computed(() => {
    // Hide old card if finance dashboard is shown
    if (finance.value) return false;
    return ['admin_unit', 'bendahara', 'super_admin'].includes(roleName.value);
});
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
    return duesSummary.value.paid_amount || duesSummary.value.paid * 30000 || 0;
});

const duesTarget = computed(() => {
    if (!duesSummary.value) return 30000 * 50; // Fallback dummy target
    return duesSummary.value.target_amount || duesSummary.value.total * 30000 || 30000 * 50;
});

const duesProgress = computed(() => {
    if (duesTarget.value <= 0) return 0;
    return Math.round((duesCollected.value / duesTarget.value) * 100);
});

// Recent activities - from props
const recentActivities = computed(() => {
    return pg.dashboard?.recent_activities || [];
});

// Recent mutations - from props
const recentMutations = computed(() => {
    return pg.dashboard?.recent_mutations || [];
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

const getActivityDotClass = (type) => {
    const classes = {
        success: 'bg-green-500',
        info: 'bg-blue-500',
        warning: 'bg-amber-500',
        error: 'bg-red-500',
    };
    return classes[type] || classes.info;
};
</script>
