<template>
  <nav class="fixed bottom-0 left-0 right-0 pb-safe z-50" style="background-color: #1A2B63; border-top: 1px solid #2E4080;">
    <div class="grid grid-cols-4 h-16">
      <Link href="/dashboard" :class="navItemClass('/dashboard')">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span class="text-[10px] mt-1 font-medium">Beranda</span>
      </Link>

      <Link href="/letters/inbox" :class="navItemClass('/letters')">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
        <span class="text-[10px] mt-1 font-medium">Surat</span>
      </Link>

      <Link href="/member/aspirations" :class="navItemClass('/member/aspirations')">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        </svg>
        <span class="text-[10px] mt-1 font-medium">Aspirasi</span>
      </Link>

      <button @click="$emit('toggle-menu')" :class="['flex flex-col items-center justify-center w-full h-full transition-colors', menuActive ? 'text-white' : 'text-[#CBD5E1]']">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
        <span class="text-[10px] mt-1 font-medium">Menu</span>
      </button>
    </div>
  </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    menuActive: Boolean
});

const page = usePage();

function navItemClass(path) {
  const isActive = page.url === path || page.url.startsWith(path + '/');
  return [
    'flex flex-col items-center justify-center w-full h-full transition-colors',
    isActive ? 'text-white' : 'text-[#CBD5E1] hover:text-white'
  ].join(' ');
}
</script>

<style scoped>
.pb-safe {
    padding-bottom: env(safe-area-inset-bottom);
}
</style>
