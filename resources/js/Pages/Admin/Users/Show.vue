<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
  user: Object,
});
</script>

<template>
  <AppLayout page-title="Detail User">
    <Head title="Detail User" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Detail User</h2>
          <p class="text-sm text-neutral-500">Informasi akun dan keterkaitan anggota.</p>
        </div>
        <Link
          href="/admin/roles"
          class="text-sm text-brand-primary-600 hover:underline"
        >
          Kelola Role
        </Link>
      </div>

      <CardContainer padding="sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p class="text-xs text-neutral-500">Nama</p>
            <p class="text-base font-medium text-neutral-900">{{ user.name }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Email</p>
            <p class="text-base font-medium text-neutral-900">{{ user.email }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Role</p>
            <p class="text-base font-medium text-neutral-900">{{ user.role?.label || user.role?.name || '-' }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Unit (User)</p>
            <p class="text-base font-medium text-neutral-900">{{ user.organization_unit?.name || '-' }}</p>
          </div>
        </div>
      </CardContainer>

      <CardContainer padding="sm">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base font-semibold text-neutral-900">Keterkaitan Anggota</h3>
          <Link
            v-if="user.member?.id"
            :href="`/admin/members/${user.member.id}`"
            class="text-sm text-brand-primary-600 hover:underline"
          >
            Lihat Anggota
          </Link>
        </div>

        <div v-if="user.member" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <p class="text-xs text-neutral-500">Nama Anggota</p>
            <p class="text-base font-medium text-neutral-900">{{ user.member.full_name }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">KTA</p>
            <p class="text-base font-medium text-neutral-900">{{ user.member.kta_number }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Unit (Anggota)</p>
            <p class="text-base font-medium text-neutral-900">{{ user.member.unit?.name || '-' }}</p>
          </div>
        </div>
        <div v-else class="text-sm text-neutral-500">
          User ini belum terhubung ke data anggota.
        </div>
      </CardContainer>
    </div>
  </AppLayout>
</template>

