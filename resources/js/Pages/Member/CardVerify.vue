<template>
  <AppLayout page-title="Verifikasi Kartu">
    <CardContainer padding="lg" shadow="sm">
      <div class="flex items-center justify-between mb-3">
        <div class="text-lg font-semibold text-neutral-900">{{ $toTitleCase(member.full_name) }}</div>
        <Badge :variant="statusVariant(member.status)">{{ member.status }}</Badge>
      </div>
      <div class="text-sm text-neutral-700">Unit: {{ member.unit }}</div>
      <div class="text-sm text-neutral-700">Masa berlaku: {{ member.valid_until || '-' }}</div>
      <div class="mt-3 text-xs text-neutral-600">Data valid per {{ scanned_at }}</div>
      <div class="mt-3 text-xs" :class="member.status==='aktif' ? 'text-status-success-dark' : 'text-status-danger-dark'">
        {{ member.status==='aktif' ? 'Keanggotaan aktif' : 'Keanggotaan tidak aktif. Hubungi Serikat untuk bantuan.' }}
      </div>
      <div class="mt-4"><a href="mailto:admin@example.com" class="text-brand-primary-600 text-sm">Hubungi Serikat</a></div>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePage } from '@inertiajs/vue3';
const page = usePage();
const member = page.props.member;
const scanned_at = page.props.scanned_at;

function statusVariant(s){
  switch (s) {
    case 'aktif': return 'success'
    case 'cuti': return 'warning'
    case 'suspended': return 'danger'
    case 'resign': return 'neutral'
    case 'pensiun': return 'neutral'
    default: return 'neutral'
  }
}
</script>
