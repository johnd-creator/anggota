<template>
  <AppLayout :page-title="ledger ? 'Edit Transaksi' : 'Tambah Transaksi'">
    <CardContainer padding="lg" class="max-w-2xl">
      <AlertBanner v-if="$page.props.flash.error" type="error" :message="$page.props.flash.error" />
      
      <!-- Workflow info banner -->
      <div v-if="workflowEnabled && !ledger" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
        <p class="text-sm text-blue-800">
          <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Transaksi akan otomatis dikirim untuk persetujuan Admin Unit.
        </p>
      </div>

      <!-- Status banner for existing ledger -->
      <div v-if="ledger && workflowEnabled" class="mb-4 p-3 rounded-lg" :class="statusBannerClass">
        <p class="text-sm">
          <strong>Status:</strong> {{ statusLabel(ledger.status) }}
          <span v-if="ledger.rejected_reason" class="block mt-1 text-red-700">Alasan: {{ ledger.rejected_reason }}</span>
        </p>
      </div>

      <form @submit.prevent="submit">
        <div class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-neutral-700">Tanggal</label>
            <input type="date" v-model="form.date" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" />
            <p v-if="errors.date" class="text-xs text-status-error mt-1">{{ errors.date }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700">Kategori</label>
            <select v-model="form.finance_category_id" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Pilih kategori</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }} ({{ c.type==='income'?'Pemasukan':'Pengeluaran' }})</option>
            </select>
            <p v-if="errors.finance_category_id" class="text-xs text-status-error mt-1">{{ errors.finance_category_id }}</p>
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
            <label class="block text-sm font-medium text-neutral-700">Nominal</label>
            <InputField v-model="form.amount" placeholder="0" type="number" />
            <p v-if="errors.amount" class="text-xs text-status-error mt-1">{{ errors.amount }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700">Deskripsi</label>
            <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" />
            <p v-if="errors.description" class="text-xs text-status-error mt-1">{{ errors.description }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700">Lampiran Bukti (gambar/PDF)</label>
            <input type="file" @change="onFileChange" accept="image/*,application/pdf" class="mt-1 block w-full text-sm" />
            <p v-if="errors.attachment" class="text-xs text-status-error mt-1">{{ errors.attachment }}</p>
            <div v-if="form.attachment_path" class="mt-2">
              <a :href="`/storage/${form.attachment_path}`" target="_blank" class="text-brand-primary-700 underline">Lihat lampiran</a>
            </div>
          </div>

          <!-- Status field - hidden when workflow enabled for non-super_admin -->
          <div v-if="!workflowEnabled || isSuperAdmin">
            <label class="block text-sm font-medium text-neutral-700">Status</label>
            <select v-model="form.status" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" :disabled="workflowEnabled && !isSuperAdmin">
              <option value="draft">Draft</option>
              <option value="submitted">Submitted</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
            <p v-if="errors.status" class="text-xs text-status-error mt-1">{{ errors.status }}</p>
          </div>

          <!-- Unit field - only for super_admin -->
          <div v-if="isSuperAdmin">
            <label class="block text-sm font-medium text-neutral-700">Unit</label>
            <select v-model="form.organization_unit_id" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Pilih unit</option>
              <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <p v-if="errors.organization_unit_id" class="text-xs text-status-error mt-1">{{ errors.organization_unit_id }}</p>
          </div>
          <div v-else>
            <label class="block text-sm font-medium text-neutral-700">Unit</label>
            <InputField :model-value="$page.props.auth.user.organization_unit?.name || 'N/A'" readonly />
          </div>

          <div class="flex justify-end space-x-3">
            <SecondaryButton @click="router.visit('/finance/ledgers')">Batal</SecondaryButton>
            <PrimaryButton type="submit" :loading="submitting">Simpan</PrimaryButton>
          </div>
        </div>
      </form>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import InputField from '@/Components/UI/InputField.vue'
import AlertBanner from '@/Components/UI/AlertBanner.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'

const props = defineProps({
  units: Array,
  categories: Array,
  ledger: Object,
  workflowEnabled: { type: Boolean, default: true },
})

const page = usePage()
const isSuperAdmin = computed(() => {
  const roles = page.props.auth?.user?.roles || []
  return roles.some(r => r.name === 'super_admin')
})

const form = useForm({
  date: props.ledger?.date || '',
  finance_category_id: props.ledger?.finance_category_id || '',
  type: props.ledger?.type || 'income',
  amount: props.ledger?.amount || '',
  description: props.ledger?.description || '',
  status: props.ledger?.status || 'submitted',
  attachment: null,
  attachment_path: props.ledger?.attachment_path || null,
  organization_unit_id: props.ledger?.organization_unit_id ?? (isSuperAdmin.value ? '' : (page.props.auth?.user?.organization_unit_id || '')),
})

const submitting = ref(false)
const errors = form.errors

const statusBannerClass = computed(() => {
  if (!props.ledger) return ''
  switch (props.ledger.status) {
    case 'draft': return 'bg-neutral-50 border border-neutral-200'
    case 'submitted': return 'bg-yellow-50 border border-yellow-200'
    case 'approved': return 'bg-green-50 border border-green-200'
    case 'rejected': return 'bg-red-50 border border-red-200'
    default: return 'bg-neutral-50 border border-neutral-200'
  }
})

function statusLabel(s) {
  switch (s) {
    case 'draft': return 'Draft'
    case 'submitted': return 'Menunggu Persetujuan'
    case 'approved': return 'Disetujui'
    case 'rejected': return 'Ditolak'
    default: return s
  }
}

function onFileChange(e) {
  const f = e.target.files?.[0]
  form.attachment = f || null
}

function submit() {
  submitting.value = true
  if (props.ledger?.id) {
    form.put(`/finance/ledgers/${props.ledger.id}`, {
      forceFormData: true,
      onFinish() { submitting.value = false },
    })
  } else {
    form.post('/finance/ledgers', {
      forceFormData: true,
      onFinish() { submitting.value = false },
    })
  }
}
</script>
