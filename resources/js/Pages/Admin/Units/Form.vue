<template>
  <AppLayout :page-title="isEditing ? 'Edit Unit' : 'Tambah Unit'">
    <div class="max-w-4xl mx-auto space-y-6">
      <!-- Main Form -->
      <CardContainer padding="lg">
        <form @submit.prevent="submit" class="space-y-8">
          <!-- Basic Information -->
          <div>
            <h3 class="text-lg font-semibold text-neutral-900 mb-4">Informasi Dasar</h3>
            <div class="space-y-4">
              <!-- Code -->
              <InputField
                v-model="form.code"
                label="Kode Unit"
                placeholder="001 atau PST"
                :error="form.errors.code"
                :disabled="isEditing"
                required
                helper="Kode unik unit (3 karakter). Contoh: 001, 010, PST."
              />

              <!-- Organization Type & Abbreviation -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="w-full">
                  <label class="block text-sm font-medium text-neutral-700 mb-1">
                    Tipe Organisasi <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="form.organization_type"
                    class="block w-full rounded-lg border border-neutral-300 px-3 py-2 text-neutral-900 placeholder-neutral-400 focus:border-brand-primary-500 focus:ring-brand-primary-500/20 focus:outline-none focus:ring-2 transition-colors duration-200"
                    :class="{ 'border-status-error focus:border-status-error focus:ring-status-error/20': form.errors.organization_type }"
                    required
                  >
                    <option value="DPP">DEWAN PIMPINAN PUSAT (DPP)</option>
                    <option value="DPD">DEWAN PIMPINAN DAERAH (DPD)</option>
                  </select>
                  <p v-if="form.errors.organization_type" class="mt-1 text-sm text-status-error">
                    {{ form.errors.organization_type }}
                  </p>
                  <p v-else class="mt-1 text-sm text-neutral-500">
                    Digunakan untuk kop surat.
                  </p>
                </div>

                <InputField
                  v-model="form.abbreviation"
                  label="Singkatan Unit (untuk No. Surat)"
                  placeholder="Contoh: DPP, TSK, SRL"
                  :error="form.errors.abbreviation"
                  required
                  helper="Akan dipakai untuk penomoran surat (bukan kode unit)."
                />
              </div>

              <!-- Name -->
              <InputField
                v-model="form.name"
                label="Nama Unit"
                placeholder="Contoh: Teluk Sirih"
                :error="form.errors.name"
                required
              />

              <!-- Address -->
              <div class="w-full">
                <label class="block text-sm font-medium text-neutral-700 mb-1">
                  Alamat
                </label>
                <textarea
                  v-model="form.address"
                  rows="3"
                  class="block w-full rounded-lg border border-neutral-300 px-3 py-2 text-neutral-900 placeholder-neutral-400 focus:border-brand-primary-500 focus:ring-brand-primary-500/20 focus:outline-none focus:ring-2 transition-colors duration-200"
                  :class="{ 'border-status-error focus:border-status-error focus:ring-status-error/20': form.errors.address }"
                  placeholder="Masukkan alamat lengkap"
                ></textarea>
                <p v-if="form.errors.address" class="mt-1 text-sm text-status-error">
                  {{ form.errors.address }}
                </p>
                <p v-else class="mt-1 text-sm text-neutral-500">
                  Minimal 10 karakter.
                </p>
              </div>

              <!-- Unit Phone & Email -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <InputField
                  v-model="form.phone"
                  label="No Telepon Unit"
                  placeholder="0813xxxxxxx"
                  :error="form.errors.phone"
                  helper="Akan tampil di kop surat (jika diisi)."
                />
                <InputField
                  v-model="form.email"
                  label="Email Unit"
                  placeholder="contoh@domain.com"
                  :error="form.errors.email"
                  helper="Akan tampil di kop surat (jika diisi)."
                />
              </div>
            </div>
          </div>

          <!-- Letterhead Section -->
          <div class="border-t border-neutral-200 pt-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold text-neutral-900">Kop Surat (Letterhead)</h3>
                <p class="text-sm text-neutral-500">Pengaturan untuk kop surat resmi unit ini. Field opsional.</p>
              </div>
              <button
                type="button"
                @click="showLetterhead = !showLetterhead"
                class="text-sm text-brand-primary-600 hover:text-brand-primary-700 font-medium"
              >
                {{ showLetterhead ? 'Sembunyikan' : 'Tampilkan' }}
              </button>
            </div>

            <div v-show="showLetterhead" class="space-y-4">
              <!-- Letterhead Name -->
              <InputField
                v-model="form.letterhead_name"
                label="Nama Organisasi (Kop)"
                placeholder="e.g. Serikat Pekerja PT PLN Indonesia Power Services"
                :error="form.errors.letterhead_name"
                helper="Nama yang akan ditampilkan di kop surat"
              />

              <!-- Letterhead Address -->
              <div class="w-full">
                <label class="block text-sm font-medium text-neutral-700 mb-1">
                  Alamat Kop Surat
                </label>
                <textarea
                  v-model="form.letterhead_address"
                  rows="2"
                  class="block w-full rounded-lg border border-neutral-300 px-3 py-2 text-neutral-900 placeholder-neutral-400 focus:border-brand-primary-500 focus:ring-brand-primary-500/20 focus:outline-none focus:ring-2 transition-colors duration-200"
                  :class="{ 'border-status-error focus:border-status-error focus:ring-status-error/20': form.errors.letterhead_address }"
                  placeholder="Jl. Contoh No. 123, Gedung A Lt. 5"
                ></textarea>
                <p v-if="form.errors.letterhead_address" class="mt-1 text-sm text-status-error">
                  {{ form.errors.letterhead_address }}
                </p>
              </div>

              <!-- City & Postal Code -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <InputField
                  v-model="form.letterhead_city"
                  label="Kota"
                  placeholder="Jakarta"
                  :error="form.errors.letterhead_city"
                />
                <InputField
                  v-model="form.letterhead_postal_code"
                  label="Kode Pos"
                  placeholder="12345"
                  :error="form.errors.letterhead_postal_code"
                />
              </div>

              <!-- Phone & Fax -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <InputField
                  v-model="form.letterhead_phone"
                  label="Telepon"
                  placeholder="(021) 123-4567"
                  :error="form.errors.letterhead_phone"
                />
                <InputField
                  v-model="form.letterhead_fax"
                  label="Fax"
                  placeholder="(021) 123-4568"
                  :error="form.errors.letterhead_fax"
                />
              </div>

              <!-- Email & Website -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <InputField
                  v-model="form.letterhead_email"
                  label="Email"
                  type="email"
                  placeholder="unit@sppips.org"
                  :error="form.errors.letterhead_email"
                />
                <InputField
                  v-model="form.letterhead_website"
                  label="Website"
                  placeholder="https://www.sppips.org"
                  :error="form.errors.letterhead_website"
                />
              </div>

              <!-- WhatsApp -->
              <InputField
                v-model="form.letterhead_whatsapp"
                label="WhatsApp"
                placeholder="08123456789"
                :error="form.errors.letterhead_whatsapp"
              />

              <!-- Footer Text -->
              <div class="w-full">
                <label class="block text-sm font-medium text-neutral-700 mb-1">
                  Teks Footer (opsional)
                </label>
                <textarea
                  v-model="form.letterhead_footer_text"
                  rows="2"
                  class="block w-full rounded-lg border border-neutral-300 px-3 py-2 text-neutral-900 placeholder-neutral-400 focus:border-brand-primary-500 focus:ring-brand-primary-500/20 focus:outline-none focus:ring-2 transition-colors duration-200"
                  :class="{ 'border-status-error focus:border-status-error focus:ring-status-error/20': form.errors.letterhead_footer_text }"
                  placeholder="Teks tambahan di bagian bawah surat"
                ></textarea>
                <p v-if="form.errors.letterhead_footer_text" class="mt-1 text-sm text-status-error">
                  {{ form.errors.letterhead_footer_text }}
                </p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end space-x-4 pt-4 border-t border-neutral-200">
            <SecondaryButton @click="router.visit('/admin/units')">
              Batal
            </SecondaryButton>
            <PrimaryButton type="submit" :loading="form.processing">
              {{ isEditing ? 'Simpan Perubahan' : 'Tambah Unit' }}
            </PrimaryButton>
          </div>
        </form>
      </CardContainer>

      <!-- Letterhead Preview -->
      <CardContainer v-if="showLetterhead" padding="lg">
        <h3 class="text-sm font-medium text-neutral-700 mb-3">Preview Kop Surat</h3>
        <div class="border border-neutral-200 rounded-lg p-6 bg-white">
          <!-- Header with Logo and Title -->
          <div class="flex items-start border-b-2 border-neutral-900 pb-4 mb-4">
            <!-- Logo placeholder (left) -->
            <div class="w-16 h-16 bg-neutral-100 rounded flex items-center justify-center mr-4 flex-shrink-0">
              <svg class="w-8 h-8 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <!-- Text content -->
            <div class="flex-1 text-center">
              <h4 class="text-lg font-bold text-neutral-900">
                {{ form.letterhead_name || 'SERIKAT PEKERJA PT PLN INDONESIA POWER SERVICES' }}
              </h4>
              <h5 class="text-md font-semibold text-neutral-800">{{ form.name || 'Nama Unit' }}</h5>
              <p v-if="displayAddress" class="text-sm text-neutral-600 mt-1">{{ displayAddress }}</p>
              <p v-if="displayContact" class="text-sm text-neutral-600">{{ displayContact }}</p>
            </div>
          </div>
          <!-- Body placeholder -->
          <div class="h-20 flex items-center justify-center text-neutral-400 text-sm">
            [Isi Surat]
          </div>
          <!-- Footer -->
          <div v-if="form.letterhead_footer_text" class="border-t border-neutral-200 pt-3 mt-4 text-center text-xs text-neutral-500">
            {{ form.letterhead_footer_text }}
          </div>
        </div>
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';

const props = defineProps({
  unit: {
    type: Object,
    default: null,
  },
});

const isEditing = computed(() => !!props.unit);
const showLetterhead = ref(!!props.unit?.letterhead_name || false);

const form = useForm({
  code: props.unit?.code || '',
  name: props.unit?.name || '',
  organization_type: props.unit?.organization_type || 'DPD',
  abbreviation: props.unit?.abbreviation || '',
  address: props.unit?.address || '',
  phone: props.unit?.phone || '',
  email: props.unit?.email || '',
  // Letterhead fields
  letterhead_name: props.unit?.letterhead_name || '',
  letterhead_address: props.unit?.letterhead_address || '',
  letterhead_city: props.unit?.letterhead_city || '',
  letterhead_postal_code: props.unit?.letterhead_postal_code || '',
  letterhead_phone: props.unit?.letterhead_phone || '',
  letterhead_email: props.unit?.letterhead_email || '',
  letterhead_website: props.unit?.letterhead_website || '',
  letterhead_fax: props.unit?.letterhead_fax || '',
  letterhead_whatsapp: props.unit?.letterhead_whatsapp || '',
  letterhead_footer_text: props.unit?.letterhead_footer_text || '',
  letterhead_logo_path: props.unit?.letterhead_logo_path || '',
});

// Computed for preview
const displayAddress = computed(() => {
  const parts = [];
  if (form.letterhead_address) parts.push(form.letterhead_address);
  if (form.letterhead_city) parts.push(form.letterhead_city);
  if (form.letterhead_postal_code) parts.push(form.letterhead_postal_code);
  return parts.join(', ') || (form.address || '');
});

const displayContact = computed(() => {
  const parts = [];
  if (form.letterhead_phone) parts.push(`Telp: ${form.letterhead_phone}`);
  if (form.letterhead_fax) parts.push(`Fax: ${form.letterhead_fax}`);
  if (form.letterhead_email) parts.push(form.letterhead_email);
  if (form.letterhead_whatsapp) parts.push(`WA: ${form.letterhead_whatsapp}`);
  return parts.join(' | ');
});

const submit = () => {
  if (isEditing.value) {
    form.put(`/admin/units/${props.unit.id}`);
  } else {
    form.post('/admin/units');
  }
};
</script>
