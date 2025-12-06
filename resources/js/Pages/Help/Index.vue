<template>
  <AppLayout page-title="Pusat Bantuan">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <CardContainer padding="lg" shadow="sm">
        <div class="text-sm font-semibold text-neutral-900 mb-2">Mutasi</div>
        <ul class="text-sm text-brand-primary-700 space-y-2">
          <li><a href="/docs/help/mutasi">Panduan Mutasi Anggota</a></li>
        </ul>
      </CardContainer>
      <CardContainer padding="lg" shadow="sm">
        <div class="text-sm font-semibold text-neutral-900 mb-2">Kartu Digital</div>
        <ul class="text-sm text-brand-primary-700 space-y-2">
          <li><a href="/docs/help/kartu">Kartu Anggota Digital</a></li>
        </ul>
      </CardContainer>
      <CardContainer padding="lg" shadow="sm">
        <div class="text-sm font-semibold text-neutral-900 mb-2">Login</div>
        <ul class="text-sm text-brand-primary-700 space-y-2">
          <li><a href="/docs/help/login">Masalah Login & SSO</a></li>
        </ul>
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
</script>

