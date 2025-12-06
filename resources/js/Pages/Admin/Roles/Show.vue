<template>
  <AppLayout page-title="Detail Role">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <CardContainer padding="lg" shadow="sm">
        <div class="text-sm"><span class="font-semibold">Name:</span> {{ role.name }}</div>
        <div class="text-sm"><span class="font-semibold">Label:</span> {{ role.label }}</div>
        <div class="text-sm"><span class="font-semibold">Deskripsi:</span> {{ role.description || '-' }}</div>
        <div class="text-sm"><span class="font-semibold">Domain Whitelist:</span> {{ (role.domain_whitelist||[]).join(', ') || '-' }}</div>
        <div class="mt-4">
          <div class="text-sm font-semibold mb-2">Assign ke User</div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <InputField v-model="assignEmail" label="Email pengguna" />
            <SelectField v-if="role.name==='admin_unit'" v-model="assignUnit" :options="unitOptions" label="Unit Pembangkit" />
            <SecondaryButton class="md:justify-self-end" @click="assign">Assign</SecondaryButton>
          </div>
        </div>
      </CardContainer>

      <CardContainer padding="lg" shadow="sm" class="lg:col-span-2">
        <div class="flex items-center justify-between">
          <div class="text-sm font-semibold">Pengguna dengan role ini</div>
          <SecondaryButton @click="back">Kembali</SecondaryButton>
        </div>
        <div class="mt-3" v-if="!users.data.length">Belum ada pengguna.</div>
        <table v-else class="min-w-full divide-y divide-neutral-200 mt-3">
          <thead class="bg-neutral-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Nama</th>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Email</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="u in users.data" :key="u.id">
              <td class="px-4 py-2 text-sm">{{ u.name }}</td>
              <td class="px-4 py-2 text-sm">{{ u.email }}</td>
            </tr>
          </tbody>
        </table>
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import { usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';
const page = usePage();
const role = page.props.role;
const users = page.props.users;
const assignEmail = ref('');
const unitOptions = (page.props.units||[]).map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
const assignUnit = ref('');
function assign(){
  const payload = { email: assignEmail.value };
  if (role.name==='admin_unit') payload.organization_unit_id = assignUnit.value;
  router.post(`/admin/roles/${role.id}/assign`, payload);
}
function back(){ router.get('/admin/roles'); }
</script>
