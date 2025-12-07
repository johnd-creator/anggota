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

          <!-- Recurring Fields -->
          <div class="border-t border-neutral-200 pt-4">
            <div class="flex items-center space-x-3">
              <input type="checkbox" v-model="form.is_recurring" id="is_recurring" class="rounded border-neutral-300 text-brand-primary-600 focus:ring-brand-primary-500" />
              <label for="is_recurring" class="text-sm font-medium text-neutral-700">
                Iuran Berulang (Recurring)
              </label>
            </div>
            <p class="text-xs text-neutral-500 mt-1">Centang jika kategori ini digunakan untuk iuran bulanan anggota.</p>
          </div>

          <div v-if="form.is_recurring">
            <label class="block text-sm font-medium text-neutral-700">Tarif Default (Rp)</label>
            <input type="number" v-model.number="form.default_amount" min="0" step="1000" placeholder="Contoh: 30000" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" />
            <p class="text-xs text-neutral-500 mt-1">Nominal otomatis saat memproses iuran batch.</p>
            <p v-if="errors.default_amount" class="text-xs text-status-error mt-1">{{ errors.default_amount }}</p>
          </div>

          <!-- System category notice -->
          <div v-if="category?.is_system" class="bg-yellow-50 border border-yellow-200 rounded-md p-3">
            <p class="text-sm text-yellow-800">
              <strong>Kategori Sistem:</strong> Kategori ini adalah kategori default dan tidak dapat dihapus.
            </p>
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
import AlertBanner from '@/Components/UI/AlertBanner.vue'

const props = defineProps({ units: Array, category: Object })
const isSuperAdmin = computed(() => (typeof $page.props.auth.user.role?.name === 'string') && $page.props.auth.user.role.name === 'super_admin')

const form = useForm({
  name: props.category?.name || '',
  type: props.category?.type || 'income',
  description: props.category?.description || '',
  organization_unit_id: props.category?.organization_unit_id ?? (isSuperAdmin.value ? null : ($page.props.auth.user.organization_unit_id || null)),
  is_recurring: props.category?.is_recurring || false,
  default_amount: props.category?.default_amount || null,
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
