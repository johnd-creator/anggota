<template>
  <AppLayout :page-title="isEdit ? 'Edit Approver' : 'Tambah Approver'">
    <div class="max-w-2xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">{{ isEdit ? 'Edit Approver' : 'Tambah Approver' }}</h2>
          <p class="text-sm text-neutral-500">{{ isEdit ? 'Ubah delegasi persetujuan surat.' : 'Tambah delegasi persetujuan surat baru.' }}</p>
        </div>
        <Link href="/admin/letter-approvers" class="text-sm text-neutral-600 hover:text-neutral-900 flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
          Kembali
        </Link>
      </div>

      <CardContainer>
        <form @submit.prevent="submit" class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Unit <span class="text-neutral-400">(kosongkan untuk Pusat)</span></label>
            <select v-model="form.organization_unit_id" class="w-full px-3 py-2 border border-neutral-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
              <option :value="null">Pusat (Admin Pusat)</option>
              <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
            </select>
            <p v-if="form.errors.organization_unit_id" class="mt-1 text-sm text-red-600">{{ form.errors.organization_unit_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Tipe Penandatangan</label>
            <select v-model="form.signer_type" class="w-full px-3 py-2 border border-neutral-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" required>
              <option value="">-- Pilih --</option>
              <option v-for="st in signerTypes" :key="st.value" :value="st.value">{{ st.label }}</option>
            </select>
            <p v-if="form.errors.signer_type" class="mt-1 text-sm text-red-600">{{ form.errors.signer_type }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">User</label>
            <select v-model="form.user_id" class="w-full px-3 py-2 border border-neutral-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" required>
              <option value="">-- Pilih User --</option>
              <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
            <p v-if="form.errors.user_id" class="mt-1 text-sm text-red-600">{{ form.errors.user_id }}</p>
          </div>

          <div class="flex items-center gap-3">
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
              <div class="w-11 h-6 bg-neutral-200 peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
            <span class="text-sm font-medium text-neutral-700">Aktif</span>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <Link href="/admin/letter-approvers" class="px-4 py-2 text-sm font-medium text-neutral-700 bg-neutral-100 rounded-lg hover:bg-neutral-200">
              Batal
            </Link>
            <PrimaryButton type="submit" :loading="form.processing">
              {{ isEdit ? 'Simpan Perubahan' : 'Tambah Approver' }}
            </PrimaryButton>
          </div>
        </form>
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'

const props = defineProps({
  approver: Object,
  units: Array,
  users: Array,
  signerTypes: Array,
})

const isEdit = computed(() => !!props.approver)

const form = useForm({
  organization_unit_id: props.approver?.organization_unit_id || null,
  signer_type: props.approver?.signer_type || '',
  user_id: props.approver?.user_id || '',
  is_active: props.approver?.is_active ?? true,
})

function submit() {
  if (isEdit.value) {
    form.put(`/admin/letter-approvers/${props.approver.id}`)
  } else {
    form.post('/admin/letter-approvers')
  }
}
</script>
