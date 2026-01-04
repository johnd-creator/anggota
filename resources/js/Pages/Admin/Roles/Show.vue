<template>
  <AppLayout page-title="Detail Role">
    <AlertBanner v-if="$page.props.flash.success" type="success" :message="$page.props.flash.success" dismissible @dismiss="$page.props.flash.success = null" />
    <AlertBanner v-if="$page.props.flash.error" type="error" :message="$page.props.flash.error" dismissible @dismiss="$page.props.flash.error = null" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-4">
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
              <th class="px-4 py-2 text-right text-xs text-neutral-500">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="u in users.data" :key="u.id">
              <td class="px-4 py-2 text-sm">{{ u.name }}</td>
              <td class="px-4 py-2 text-sm">{{ u.email }}</td>
              <td class="px-4 py-2 text-sm text-right">
                <button @click="confirmRemove(u)" class="text-status-error hover:text-red-800" title="Hapus dari role">
                  <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="mt-4">
          <Pagination :paginator="users" />
        </div>
      </CardContainer>
    </div>

    <!-- Remove User Modal -->
    <ModalBase v-model:show="showRemoveModal" title="Hapus User dari Role" size="md">
      <div class="space-y-4">
        <p class="text-neutral-600">Anda yakin ingin menghapus <strong>{{ userToRemove?.name }}</strong> dari role <strong>{{ role.label }}</strong>?</p>
        <p class="text-sm text-neutral-500">User akan dikembalikan ke role reguler.</p>
      </div>
      <template #footer>
        <div class="flex justify-end space-x-3">
          <SecondaryButton @click="showRemoveModal = false">Batal</SecondaryButton>
          <PrimaryButton class="bg-status-error hover:bg-status-error-dark focus:ring-status-error" @click="doRemove" :loading="removing">Hapus</PrimaryButton>
        </div>
      </template>
    </ModalBase>
  </AppLayout>
</template>

<script setup>
import Pagination from '@/Components/UI/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import { usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const page = usePage();
const role = page.props.role;
const users = page.props.users;
const assignEmail = ref('');
const unitOptions = (page.props.units||[]).map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
const assignUnit = ref('');

// Remove user state
const showRemoveModal = ref(false);
const userToRemove = ref(null);
const removing = ref(false);

function assign(){
  const payload = { email: assignEmail.value };
  if (role.name==='admin_unit') payload.organization_unit_id = assignUnit.value;
  router.post(`/admin/roles/${role.id}/assign`, payload);
}

function back(){ router.get('/admin/roles'); }

function confirmRemove(user) {
  userToRemove.value = user;
  showRemoveModal.value = true;
}

function doRemove() {
  if (!userToRemove.value) return;
  removing.value = true;
  router.delete(`/admin/roles/${role.id}/users/${userToRemove.value.id}`, {
    preserveScroll: true,
    onSuccess() {
      showRemoveModal.value = false;
      userToRemove.value = null;
    },
    onFinish() {
      removing.value = false;
    },
  });
}
</script>

