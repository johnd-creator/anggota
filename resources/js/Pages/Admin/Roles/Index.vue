<template>
  <AppLayout page-title="Role & Access">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Role & Access</h2>
          <p class="text-sm text-neutral-500">Kelola role dan akses pengguna.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <CtaButton @click="goCreate">
            <template #icon>
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </template>
            Tambah Role
          </CtaButton>
        </div>
      </div>
    <CardContainer padding="lg" shadow="sm">
      <div v-if="!roles.data.length" class="p-8 text-center text-neutral-600">Belum ada role.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Name</th>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Label</th>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Deskripsi</th>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Pengguna</th>
              <th class="px-4 py-2 text-right text-xs text-neutral-500">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="r in roles.data" :key="r.id">
              <td class="px-4 py-2 text-sm">{{ r.name }}</td>
              <td class="px-4 py-2 text-sm">{{ r.label }}</td>
              <td class="px-4 py-2 text-sm">{{ r.description || '-' }}</td>
              <td class="px-4 py-2 text-sm">{{ r.users_count }}</td>
              <td class="px-4 py-2 text-right text-sm">
                <SecondaryButton @click="goShow(r)">Detail</SecondaryButton>
                <SecondaryButton class="ml-2" @click="goEdit(r)">Edit</SecondaryButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </CardContainer>
  </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import CtaButton from '@/Components/UI/CtaButton.vue';
import { usePage, router } from '@inertiajs/vue3';
const page = usePage();
const roles = page.props.roles;
function goCreate(){ router.get('/admin/roles/create'); }
function goEdit(r){ router.get(`/admin/roles/${r.id}/edit`); }
function goShow(r){ router.get(`/admin/roles/${r.id}`); }
</script>

