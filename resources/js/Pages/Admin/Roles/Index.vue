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
    <CardContainer padding="none" class="overflow-hidden">
      <div v-if="!roles.data.length" class="p-8 text-center text-neutral-600">Belum ada role.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wide">Name</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wide">Label</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wide">Deskripsi</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wide">Pengguna</th>
              <th class="px-5 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wide">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="r in roles.data" :key="r.id">
              <td class="px-5 py-3 text-sm">{{ r.name }}</td>
              <td class="px-5 py-3 text-sm">{{ r.label }}</td>
              <td class="px-5 py-3 text-sm">{{ r.description || '-' }}</td>
              <td class="px-5 py-3 text-sm">{{ r.users_count }}</td>
              <td class="px-5 py-3 text-right text-sm">
                <div class="flex justify-end gap-2">
                  <IconButton :aria-label="`Detail ${r.name}`" size="sm" @click="goShow(r)">
                    <svg class="w-5 h-5 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </IconButton>
                  <IconButton :aria-label="`Edit ${r.name}`" size="sm" @click="goEdit(r)">
                    <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </IconButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :paginator="roles" />
    </CardContainer>
  </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import CtaButton from '@/Components/UI/CtaButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import IconButton from '@/Components/UI/IconButton.vue';
import { usePage, router } from '@inertiajs/vue3';
const page = usePage();
const roles = page.props.roles;
function goCreate(){ router.get('/admin/roles/create'); }
function goEdit(r){ router.get(`/admin/roles/${r.id}/edit`); }
function goShow(r){ router.get(`/admin/roles/${r.id}`); }
</script>
