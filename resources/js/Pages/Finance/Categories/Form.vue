<template>
  <AppLayout :page-title="category ? 'Edit Kategori' : 'Tambah Kategori'">
    <CardContainer padding="lg" class="max-w-2xl">
      <AlertBanner v-if="$page.props.flash.error" type="error" :message="$page.props.flash.error" />
      <form @submit.prevent="submit">
        <div class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-neutral-700">Nama</label>
            <InputField v-model="form.name" placeholder="Nama kategori" />
            <p v-if="errors.name" class="text-xs text-status-error mt-1">{{ errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700">Tipe</label>
            <select v-model="form.type" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="income">Pemasukan</option>
              <option value="expense">Pengeluaran</option>
            </select>
            <p v-if="errors.type" class="text-xs text-status-error mt-1">{{ errors.type }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700">Deskripsi</label>
            <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" />
            <p v-if="errors.description" class="text-xs text-status-error mt-1">{{ errors.description }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700">Unit</label>
            <template v-if="isSuperAdmin">
              <select v-model="form.organization_unit_id" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
                <option :value="null">Global</option>
                <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </template>
            <template v-else>
              <InputField :model-value="$page.props.auth.user.organization_unit?.name" readonly />
            </template>
            <p v-if="errors.organization_unit_id" class="text-xs text-status-error mt-1">{{ errors.organization_unit_id }}</p>
          </div>

          <div class="flex justify-end space-x-3">
            <SecondaryButton @click="router.visit('/finance/categories')">Batal</SecondaryButton>
            <PrimaryButton type="submit" :loading="submitting">Simpan</PrimaryButton>
          </div>
        </div>
      </form>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import InputField from '@/Components/UI/InputField.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'

const props = defineProps({ units: Array, category: Object })
const isSuperAdmin = computed(() => (typeof $page.props.auth.user.role?.name === 'string') && $page.props.auth.user.role.name === 'super_admin')

const form = useForm({
  name: props.category?.name || '',
  type: props.category?.type || 'income',
  description: props.category?.description || '',
  organization_unit_id: props.category?.organization_unit_id ?? (isSuperAdmin.value ? null : ($page.props.auth.user.organization_unit_id || null))
})

const submitting = ref(false)
const errors = form.errors

function submit(){
  submitting.value = true
  if (props.category?.id) {
    form.put(`/finance/categories/${props.category.id}`, { onFinish(){ submitting.value=false } })
  } else {
    form.post('/finance/categories', { onFinish(){ submitting.value=false } })
  }
}
</script>

