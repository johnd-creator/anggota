<template>
  <div class="min-h-screen bg-neutral-50 pb-20">
    <!-- Mobile Header -->
    <header class="sticky top-0 z-30 bg-white border-b border-neutral-200 h-14 px-4 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <img src="/img/logo.png" alt="Logo" class="w-7 h-7 object-contain" />
        <span class="text-lg font-bold text-brand-primary-900">SP-PIPS</span>
      </div>

      <div class="flex items-center gap-3">
        <Link href="/notifications" class="relative p-2 rounded-full text-neutral-600 hover:bg-neutral-100">
             <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
             </svg>
             <span v-if="$page.props.counters?.notifications_unread" class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
        </Link>

        <Link href="/member/profile">
            <UserAvatar :src="$page.props.auth.user.avatar" :name="$page.props.auth.user.name" size="h-8 w-8" />
        </Link>
      </div>
    </header>

    <!-- Main Content -->
    <main class="p-4">
       <!-- Flash Messages (Simplified for Mobile) -->
       <div v-if="$page.props.flash?.success" class="mb-4 bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded-lg text-sm">
          {{ $page.props.flash.success }}
       </div>
       <div v-if="$page.props.flash?.error" class="mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg text-sm">
          {{ $page.props.flash.error }}
       </div>

       <slot />
    </main>

    <!-- Full Screen Menu Overlay -->
    <div v-if="menuOpen" class="fixed inset-0 z-40 slide-in-bottom overflow-y-auto" style="background-color: #1A2B63;">
        <div class="sticky top-0 flex items-center justify-between px-4 h-14 border-b" style="border-color: #2E4080; background-color: #1A2B63;">
            <span class="font-bold text-lg text-white">Menu</span>
            <button @click="menuOpen = false" class="p-2 text-white hover:opacity-70">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-4 space-y-6 pb-24">
            <!-- User Info -->
            <div class="flex items-center gap-3 pb-6 border-b" style="border-color: #2E4080;">
                 <UserAvatar :src="$page.props.auth.user.avatar" :name="$page.props.auth.user.name" size="h-12 w-12" />
                 <div>
                     <div class="font-bold text-white">{{ $page.props.auth.user.name }}</div>
                     <div class="text-sm opacity-70">{{ $page.props.auth.user.email }}</div>
                 </div>
            </div>

            <!-- Menu Groups -->
            <div class="space-y-1">
                <Link href="/member/portal" class="flex items-center gap-3 p-3 rounded-xl text-white font-medium" style="background-color: #2E4080;">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0c0 .883-.393 1.627-1.019 2.122C8.627 8.718 7.373 9 6 9v-.001c1.373 0 2.627.282 3.618.828C10.22 10.327 10.607 11.071 10.607 11.954v6.09c0 .883-.393 1.627-1.019 2.122" />
                    </svg>
                    KTA Digital
                </Link>
                <Link href="/announcements" class="flex items-center gap-3 p-3 rounded-xl text-white hover:opacity-90">
                    <MegaphoneIcon class="h-5 w-5" />
                    Pengumuman
                </Link>
                <Link href="/member/aspirations" class="flex items-center gap-3 p-3 rounded-xl text-white hover:opacity-90">
                     <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                     </svg>
                     Aspirasi Saya
                </Link>
                 <Link href="/settings" class="flex items-center gap-3 p-3 rounded-xl text-white hover:opacity-90">
                     <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                     </svg>
                     Pengaturan
                 </Link>
                 <Link href="/help" class="flex items-center gap-3 p-3 rounded-xl text-white hover:opacity-90">
                     <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                     </svg>
                     Bantuan
                 </Link>
            </div>

            <div class="pt-4 border-t" style="border-color: #2E4080;">
                <button @click="doLogout" class="flex w-full items-center gap-3 p-3 rounded-xl text-red-400 hover:text-white font-medium">
                     <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                     </svg>
                     Keluar
                </button>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <BottomNav :menu-active="menuOpen" @toggle-menu="menuOpen = !menuOpen" />
    </Teleport>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BottomNav from '@/Components/Mobile/BottomNav.vue';
import UserAvatar from '@/Components/UI/UserAvatar.vue';
import { MegaphoneIcon } from '@heroicons/vue/24/outline';

const menuOpen = ref(false);

function doLogout() {
  router.post('/logout');
}
</script>

<style scoped>
.slide-in-bottom {
    animation: slideUp 0.3s ease-out;
}
@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
</style>
