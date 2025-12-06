<template>
  <AppLayout page-title="Role & Access">
    <div class="flex items-center justify-between mb-4">
      <div class="text-sm text-neutral-600">Kelola role dan akses pengguna.</div>
      <!-- <PrimaryButton @click="goCreate">Tambah Role</PrimaryButton> -->
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
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import { usePage, router } from '@inertiajs/vue3';
const page = usePage();
const roles = page.props.roles;
function goCreate(){ router.get('/admin/roles/create'); }
function goEdit(r){ router.get(`/admin/roles/${r.id}/edit`); }
function goShow(r){ router.get(`/admin/roles/${r.id}`); }
</script>

