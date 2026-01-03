<template>
  <AppLayout :page-title="category ? 'Edit Kategori Surat' : 'Tambah Kategori Surat'">
    <CardContainer padding="lg" class="max-w-3xl mx-auto">
      <AlertBanner v-if="$page.props.flash?.error" type="error" :message="$page.props.flash.error" />
      
      <form @submit.prevent="submit">
        <div class="space-y-6">
          <!-- Basic Info Section -->
          <div class="border-b border-neutral-200 pb-6">
            <h3 class="text-sm font-semibold text-neutral-800 mb-4">Informasi Dasar</h3>
            
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-neutral-700">Nama Kategori <span class="text-red-500">*</span></label>
                <InputField v-model="form.name" placeholder="Contoh: Undangan" class="mt-1" />
                <p v-if="form.errors.name" class="text-xs text-status-error mt-1">{{ form.errors.name }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-neutral-700">Kode <span class="text-red-500">*</span></label>
                <InputField 
                  v-model="form.code" 
                  placeholder="Contoh: UND" 
                  class="mt-1 font-mono uppercase"
                  maxlength="10"
                  @input="handleCodeInput"
                />
                <p class="text-xs text-neutral-500 mt-1">Maksimal 10 karakter. Huruf kapital, angka, dan underscore saja.</p>
                <p v-if="form.errors.code" class="text-xs text-status-error mt-1">{{ form.errors.code }}</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-neutral-700">Warna Kategori</label>
                  <select v-model="form.color" class="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
                    <option v-for="c in allowedColors" :key="c" :value="c">{{ colorLabel(c) }}</option>
                  </select>
                  <p v-if="form.errors.color" class="text-xs text-status-error mt-1">{{ form.errors.color }}</p>
                  <div class="mt-2">
                    <ColorBadge :color="form.color" :dot="true">{{ form.code || 'KODE' }}</ColorBadge>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-neutral-700">Urutan</label>
                  <InputField v-model="form.sort_order" type="number" min="0" max="9999" placeholder="0" class="mt-1" />
                  <p class="text-xs text-neutral-500 mt-1">Urutan lebih kecil tampil lebih dulu.</p>
                  <p v-if="form.errors.sort_order" class="text-xs text-status-error mt-1">{{ form.errors.sort_order }}</p>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-neutral-700">Deskripsi</label>
                <textarea 
                  v-model="form.description" 
                  rows="2" 
                  placeholder="Deskripsi opsional untuk kategori ini..."
                  class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500 focus:border-brand-primary-500" 
                />
                <p v-if="form.errors.description" class="text-xs text-status-error mt-1">{{ form.errors.description }}</p>
              </div>

              <div class="flex items-center space-x-3">
                <input 
                  type="checkbox" 
                  v-model="form.is_active" 
                  id="is_active" 
                  class="rounded border-neutral-300 text-brand-primary-600 focus:ring-brand-primary-500" 
                />
                <label for="is_active" class="text-sm font-medium text-neutral-700">
                  Kategori Aktif
                </label>
              </div>
              <p class="text-xs text-neutral-500 -mt-4 ml-7">Kategori nonaktif tidak akan muncul saat membuat surat baru.</p>
            </div>
          </div>

          <!-- Template Section -->
          <div class="border-b border-neutral-200 pb-6">
            <h3 class="text-sm font-semibold text-neutral-800 mb-4">Template Surat</h3>
            <p class="text-xs text-neutral-500 mb-4">
              Template akan otomatis mengisi field saat kategori dipilih. Variabel yang didukung: 
              <code class="bg-neutral-100 px-1 rounded">{{unit_name}}</code>,
              <code class="bg-neutral-100 px-1 rounded">{{today}}</code>,
              <code class="bg-neutral-100 px-1 rounded">{{letter_number}}</code>,
              <code class="bg-neutral-100 px-1 rounded">{{recipient_name}}</code>
            </p>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-neutral-700">Template Perihal</label>
                <textarea 
                  v-model="form.template_subject" 
                  rows="2" 
                  placeholder="Contoh: Undangan Rapat {{unit_name}}"
                  class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500 focus:border-brand-primary-500" 
                />
                <p v-if="form.errors.template_subject" class="text-xs text-status-error mt-1">{{ form.errors.template_subject }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-neutral-700">Template Isi Surat</label>
                <textarea 
                  v-model="form.template_body" 
                  rows="6" 
                  placeholder="Contoh: Dengan hormat, bersama ini kami sampaikan..."
                  class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500 focus:border-brand-primary-500" 
                />
                <p v-if="form.errors.template_body" class="text-xs text-status-error mt-1">{{ form.errors.template_body }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-neutral-700">Template Tembusan</label>
                <textarea 
                  v-model="form.template_cc_text" 
                  rows="2" 
                  placeholder="Contoh: Arsip"
                  class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500 focus:border-brand-primary-500" 
                />
                <p v-if="form.errors.template_cc_text" class="text-xs text-status-error mt-1">{{ form.errors.template_cc_text }}</p>
              </div>
            </div>
          </div>

          <!-- Default Values Section -->
          <div class="pb-6">
            <h3 class="text-sm font-semibold text-neutral-800 mb-4">Nilai Default</h3>
            <p class="text-xs text-neutral-500 mb-4">Nilai default akan otomatis terisi saat kategori dipilih.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-neutral-700">Sifat Surat</label>
                <select v-model="form.default_confidentiality" class="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
                  <option :value="null">-- Tidak ada default --</option>
                  <option value="biasa">Biasa</option>
                  <option value="terbatas">Terbatas</option>
                  <option value="rahasia">Rahasia</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-neutral-700">Urgensi</label>
                <select v-model="form.default_urgency" class="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
                  <option :value="null">-- Tidak ada default --</option>
                  <option value="biasa">Biasa</option>
                  <option value="segera">Segera</option>
                  <option value="kilat">Kilat</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-neutral-700">Penandatangan</label>
                <select v-model="form.default_signer_type" class="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
                  <option :value="null">-- Tidak ada default --</option>
                  <option value="ketua">Ketua</option>
                  <option value="sekretaris">Sekretaris</option>
                </select>
              </div>
            </div>
          </div>

          <div class="flex justify-end space-x-3 pt-4 border-t border-neutral-200">
            <SecondaryButton @click="router.visit('/admin/letter-categories')">Batal</SecondaryButton>
            <PrimaryButton type="submit" :loading="submitting">
              {{ category ? 'Perbarui' : 'Simpan' }}
            </PrimaryButton>
          </div>
        </div>
      </form>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import InputField from '@/Components/UI/InputField.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'
import AlertBanner from '@/Components/UI/AlertBanner.vue'
import ColorBadge from '@/Components/UI/ColorBadge.vue'

const props = defineProps({
  category: Object,
  allowedColors: {
    type: Array,
    default: () => ['neutral', 'blue', 'cyan', 'indigo', 'green', 'amber', 'red', 'purple', 'teal'],
  },
})

const form = useForm({
  name: props.category?.name || '',
  code: props.category?.code || '',
  color: props.category?.color || 'neutral',
  sort_order: props.category?.sort_order ?? 0,
  description: props.category?.description || '',
  is_active: props.category?.is_active ?? true,
  // Template fields
  template_subject: props.category?.template_subject || '',
  template_body: props.category?.template_body || '',
  template_cc_text: props.category?.template_cc_text || '',
  default_confidentiality: props.category?.default_confidentiality || null,
  default_urgency: props.category?.default_urgency || null,
  default_signer_type: props.category?.default_signer_type || null,
})

const submitting = ref(false)

function handleCodeInput(e) {
  // Auto-uppercase and replace spaces with underscore
  form.code = e.target.value.toUpperCase().replace(/\s+/g, '_').replace(/[^A-Z0-9_]/g, '')
}

function submit() {
  submitting.value = true
  if (props.category?.id) {
    form.put(`/admin/letter-categories/${props.category.id}`, {
      onFinish() { submitting.value = false }
    })
  } else {
    form.post('/admin/letter-categories', {
      onFinish() { submitting.value = false }
    })
  }
}

function colorLabel(color) {
  const labels = {
    green: 'Hijau (ORG)',
    cyan: 'Biru Muda (AGT)',
    indigo: 'Biru Tua/Navy (HI)',
    red: 'Merah (ADV)',
    amber: 'Kuning/Oranye (EKS)',
    blue: 'Biru',
    purple: 'Ungu',
    teal: 'Toska',
    neutral: 'Netral',
  }
  return labels[color] || color
}
</script>
