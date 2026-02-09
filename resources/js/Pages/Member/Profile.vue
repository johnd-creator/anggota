<template>
  <AppLayout page-title="Profil Anggota">
    <!-- Privacy Alert Banner -->
    <div class="bg-[#EEF3FF] border-l-4 border-[#2F5BD1] p-4 rounded-r-lg shadow-sm flex items-start space-x-3 mb-6">
      <span class="material-icons-round text-[#2F5BD1] mt-0.5">info</span>
      <div>
        <h3 class="text-sm font-semibold text-[#1A2B63]">Privasi</h3>
        <p class="text-sm text-[#2F3E7A] mt-1">
          Data Anda digunakan untuk keperluan keanggotaan. Dengan melanjutkan, Anda menyetujui pengolahan data sesuai kebijakan privasi.
        </p>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-soft p-6 sm:p-8 mb-6">
      <div v-if="member" class="space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row items-center md:items-start space-y-4 md:space-y-0 md:space-x-6">
          <!-- Profile Photo with Edit Button -->
          <div class="relative group">
            <div class="w-24 h-24 rounded-full bg-gray-200 overflow-hidden flex items-center justify-center shadow-inner">
              <OptimizedImage
                v-if="member?.photo_path"
                :src="member?.photo_path"
                :alt="$toTitleCase(member?.full_name || 'Member photo')"
                size="medium"
                class="w-full h-full object-cover"
                loading="eager"
              />
              <span v-else class="material-icons-round text-gray-400 text-5xl">person</span>
            </div>
            <button
              @click="$refs.photoInput.click()"
              class="absolute bottom-0 right-0 bg-white rounded-full p-1.5 shadow-md border border-gray-100 hover:bg-gray-50 transition-colors"
              title="Ganti foto profil"
            >
              <span class="material-icons-round text-gray-600 text-sm">edit</span>
            </button>
          </div>

          <!-- Member Info -->
          <div class="flex-grow text-center md:text-left w-full">
            <div class="flex flex-col md:flex-row justify-between items-start mb-2">
              <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $toTitleCase(member?.full_name) }}</h2>
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 mt-1 text-sm text-gray-500">
                  <span class="font-medium text-gray-700">{{ member?.nra || member?.kta_number || '-' }}</span>
                  <span
                    class="px-2 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wide border"
                    :class="statusBadgeClass(member?.status)"
                  >
                    {{ member?.status }}
                  </span>
                  <span>{{ member?.unit?.name }}</span>
                </div>

                <!-- Profile Completeness -->
                <div class="mt-4 max-w-md w-full">
                  <div class="flex justify-between items-center mb-1">
                    <span class="text-xs font-medium text-gray-500">Kelengkapan Profil</span>
                    <span class="text-xs font-bold text-[#1A2B63]">{{ completeness }}%</span>
                  </div>
                  <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-[#1A2B63] h-2 rounded-full transition-all duration-300" :style="{width: completeness+'%'}"></div>
                  </div>
                </div>
              </div>
              <PrimaryButton @click="openEdit" class="mt-4 md:mt-0">
                Lengkapi Profil
              </PrimaryButton>
            </div>
          </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="border-b border-gray-200">
          <nav aria-label="Tabs" class="-mb-px flex space-x-8 overflow-x-auto">
            <a
              href="#"
              :class="tab==='profil' ? 'border-[#1A2B63] text-[#1A2B63]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
              class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
              @click.prevent="tab='profil'"
            >
              Data Pribadi
            </a>
            <a
              href="#"
              :class="tab==='riwayat' ? 'border-[#1A2B63] text-[#1A2B63]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
              class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
              @click.prevent="tab='riwayat'"
            >
              Riwayat Mutasi & Status
            </a>
            <a
              href="#"
              :class="tab==='dokumen' ? 'border-[#1A2B63] text-[#1A2B63]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
              class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
              @click.prevent="tab='dokumen'"
            >
              Checklist Dokumen
            </a>
            <a
              href="#"
              :class="tab==='privasi' ? 'border-[#1A2B63] text-[#1A2B63]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
              class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
              @click.prevent="tab='privasi'"
            >
              Privasi & Data
            </a>
          </nav>
        </div>
      </div>

      <!-- Tab Content -->
      <div v-if="member" class="space-y-10 mt-8">
        <!-- Data Pribadi Tab -->
        <div v-show="tab==='profil'" class="space-y-10">
          <!-- Informasi Dasar Section -->
          <section>
            <h3 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
              <span class="w-1 h-6 bg-[#1A2B63] rounded-full mr-3"></span>
              Informasi Dasar
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="group">
                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="birthplace">Tempat Lahir</label>
                <input
                  class="block w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm focus:border-[#1A2B63] focus:ring-[#1A2B63] sm:text-sm py-2.5 px-3 transition-shadow"
                  id="birthplace"
                  type="text"
                  :value="member?.birth_place || '-'"
                  readonly
                />
              </div>
              <div class="group">
                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="birthdate">Tanggal Lahir</label>
                <input
                  class="block w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm focus:border-[#1A2B63] focus:ring-[#1A2B63] sm:text-sm py-2.5 px-3 transition-shadow"
                  id="birthdate"
                  type="text"
                  :value="formatDate(member?.birth_date)"
                  readonly
                />
              </div>
              <div class="group">
                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="joinDate">Tanggal Gabung Perusahaan</label>
                <div class="relative">
                  <input
                    class="block w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm focus:border-[#1A2B63] focus:ring-[#1A2B63] sm:text-sm py-2.5 px-3 transition-shadow"
                    id="joinDate"
                    type="text"
                    :value="formatDate(member?.company_join_date)"
                    readonly
                  />
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <span class="material-icons-round text-gray-400 text-sm">calendar_today</span>
                  </div>
                </div>
              </div>
              <div class="group">
                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="tenure">Masa Kerja</label>
                <input
                  class="block w-full rounded-lg border-gray-200 bg-gray-50 text-gray-500 shadow-sm sm:text-sm py-2.5 px-3 cursor-not-allowed"
                  disabled
                  id="tenure"
                  type="text"
                  :value="$page.props.auth.user.employment_info?.duration_string || '-'"
                />
              </div>
            </div>
          </section>

          <!-- Foto Profil Section -->
          <section class="bg-[#F3F6FF] rounded-xl p-6 border border-[#DCE6FF]">
            <div class="flex items-center space-x-2 mb-4">
              <span class="material-icons-round text-[#1A2B63]">image</span>
              <h4 class="text-base font-semibold text-gray-900">Foto Profil</h4>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-[#E3ECFF] text-[#2F3E7A]">
                Otomatis disimpan
              </span>
            </div>

            <div class="flex flex-col md:flex-row items-start md:items-center space-y-4 md:space-y-0 md:space-x-6">
              <!-- Photo Preview -->
              <div class="flex-shrink-0">
                <div class="h-24 w-24 rounded-full bg-white border-4 border-white shadow-md flex items-center justify-center overflow-hidden relative">
                  <OptimizedImage
                    v-if="photoPreview || member?.photo_path"
                    :src="photoPreview || member?.photo_path"
                    :alt="member?.full_name || 'Profile photo'"
                    size="medium"
                    class="h-full w-full object-cover"
                    loading="eager"
                  />
                  <span v-else class="material-icons-round text-gray-300 text-5xl">person</span>

                  <!-- Loading Overlay -->
                  <div v-if="uploadingPhoto" class="absolute inset-0 bg-white/90 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 animate-spin text-[#2F5BD1]" viewBox="0 0 24 24" fill="none">
                      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-linecap="round"/>
                    </svg>
                  </div>

                  <!-- Success Checkmark -->
                  <div v-if="uploadSuccess && !uploadingPhoto" class="absolute -bottom-1 -right-1 bg-green-500 rounded-full p-1.5 shadow-lg">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                  </div>
                </div>
              </div>

              <!-- Controls -->
              <div class="flex-1">
                <input
                  type="file"
                  ref="photoInput"
                  accept="image/jpeg,image/png,image/webp"
                  class="hidden"
                  @change="onPhotoSelect"
                />

                <div class="flex flex-wrap gap-3 mb-2">
                  <button
                    @click="$refs.photoInput.click()"
                    :disabled="uploadingPhoto"
                    class="inline-flex items-center px-4 py-2 border border-[#D3DCF7] shadow-sm text-sm font-medium rounded-md text-[#1A2B63] bg-white hover:bg-[#EFF3FF] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#2F5BD1] transition-colors disabled:opacity-50"
                    type="button"
                  >
                    <span class="material-icons-round text-sm mr-2 text-[#2F5BD1]">upload</span>
                    {{ uploadingPhoto ? 'Mengupload...' : (member?.photo_path ? 'Ganti Foto' : 'Upload Foto') }}
                  </button>

                  <button
                    v-if="member?.photo_path && !photoPreview"
                    @click="deletePhoto"
                    :disabled="uploadingPhoto"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-red-600 hover:bg-red-50 focus:outline-none transition-colors disabled:opacity-50"
                    type="button"
                  >
                    Hapus
                  </button>
                </div>

                <p class="text-xs text-[#2F3E7A] leading-relaxed">
                  Klik tombol di atas untuk memilih foto. Foto akan otomatis disimpan.<br class="hidden md:inline"/>
                  Format: JPG, PNG, WebP. Maks 5MB.
                </p>

                <!-- Selected File Info -->
                <div v-if="selectedPhoto" class="mt-3 text-xs bg-white rounded border border-[#D7E2FF] p-3">
                  <p class="font-medium text-gray-700">{{ selectedPhoto.name }}</p>
                  <p class="text-gray-600">{{ (selectedPhoto.size / 1024).toFixed(1) }} KB</p>
                  <p v-if="photoError" class="text-red-600 mt-1">{{ photoError }}</p>
                  <p v-else-if="selectedPhoto.size > 500 * 1024" class="text-amber-600 mt-1">
                    File akan dikompresi otomatis
                  </p>
                </div>
              </div>
            </div>
          </section>

          <!-- Divider -->
          <div class="border-t border-gray-100"></div>

          <!-- Editable Data Section -->
          <section>
            <p class="text-sm text-gray-500 mb-6 italic">
              Ubah data di bawah ini. Perubahan memerlukan persetujuan admin sebelum disimpan.
            </p>

            <div class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="group md:col-span-1">
                  <label class="block text-sm font-medium text-gray-700 mb-1.5" for="address">Alamat</label>
                  <textarea
                    v-model="form.address"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-[#1A2B63] focus:ring-[#1A2B63] sm:text-sm py-2.5 px-3 transition-shadow"
                    id="address"
                    placeholder="Alamat lengkap"
                    rows="3"
                  ></textarea>
                </div>
                <div class="group">
                  <label class="block text-sm font-medium text-gray-700 mb-1.5" for="phone">Nomor Telepon</label>
                  <input
                    v-model="form.phone"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-[#1A2B63] focus:ring-[#1A2B63] sm:text-sm py-2.5 px-3 transition-shadow"
                    id="phone"
                    placeholder="Nomor Telepon"
                    type="tel"
                  />
                  <div v-if="errors.phone" class="text-xs text-red-600 mt-1">{{ errors.phone }}</div>
                </div>
              </div>

              <div class="group max-w-full">
                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="emergency">Kontak Darurat</label>
                <input
                  v-model="form.emergency_contact"
                  class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-[#1A2B63] focus:ring-[#1A2B63] sm:text-sm py-2.5 px-3 transition-shadow"
                  id="emergency"
                  placeholder="Nama & nomor kontak darurat"
                  type="text"
                />
              </div>
            </div>
          </section>

          <!-- Update History -->
          <div class="bg-gray-50 rounded-lg p-5 border border-gray-100">
            <h4 class="text-sm font-semibold text-gray-900 mb-1">Riwayat Perubahan</h4>
            <div v-if="updateRequests.length" class="mt-3 space-y-2">
              <div v-for="req in updateRequests.slice(0, 3)" :key="req.id" class="flex items-center justify-between text-sm">
                <span class="text-gray-600">{{ formatDateTime(req.created_at) }}</span>
                <span
                  class="px-2 py-0.5 rounded text-xs font-medium"
                  :class="requestStatusClass(req.status)"
                >
                  {{ req.status }}
                </span>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500">Belum ada riwayat perubahan data.</p>
          </div>

          <!-- Submit Button -->
          <div class="flex flex-col items-end space-y-3 pt-4 border-t border-gray-100">
            <PrimaryButton
              @click="submitUpdate"
              :disabled="isPendingRequest"
              :title="isPendingRequest ? 'Permintaan sebelumnya masih menunggu persetujuan' : ''"
              class="flex items-center space-x-2"
            >
              <span class="material-icons-round text-sm">save_as</span>
              <span>{{ isPendingRequest ? 'Menunggu Persetujuan' : 'Ajukan Perubahan Data' }}</span>
            </PrimaryButton>
            <p class="text-xs text-gray-400 text-right">
              Perubahan alamat, telepon, dan kontak darurat memerlukan persetujuan admin.
            </p>
          </div>
        </div>

        <!-- Riwayat Tab -->
        <div v-show="tab==='riwayat'" class="space-y-3">
          <div class="flex items-center gap-3 mb-2">
            <input v-model="historySearch" placeholder="Cari event" class="rounded border px-3 py-2 text-sm" />
            <select v-model="historyYear" class="rounded border px-3 py-2 text-sm">
              <option value="">Semua Tahun</option>
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div v-for="h in filteredHistory" :key="h.id" class="flex items-start gap-3">
            <div :class="iconClass(h)" class="w-3 h-3 rounded-full mt-1.5"></div>
            <div>
              <div class="text-sm text-gray-900">{{ h.date }} • {{ h.new_status }}</div>
              <div class="text-xs text-gray-600">{{ h.notes }}</div>
            </div>
          </div>
        </div>

        <!-- Dokumen Tab -->
        <div v-show="tab==='dokumen'" class="space-y-3">
          <!-- Surat Pernyataan -->
          <div class="flex items-center justify-between p-3 border rounded-lg">
            <div class="flex items-center gap-2">
              <span
                class="px-2 py-0.5 rounded text-xs font-medium"
                :class="hasDoc('surat_pernyataan') ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
              >
                Surat Pernyataan
              </span>
              <span class="text-sm text-gray-500">(Opsional, PDF max 2MB)</span>
            </div>
            <template v-if="hasDoc('surat_pernyataan')">
              <a :href="getDocUrl('surat_pernyataan')" target="_blank" class="text-sm text-brand-primary-600 hover:underline">Download</a>
            </template>
            <template v-else>
              <input type="file" ref="suratInput" accept=".pdf" @change="uploadSurat" class="hidden" />
              <SecondaryButton
                @click="$refs.suratInput.click()"
                :disabled="uploading"
                size="sm"
              >
                {{ uploading ? 'Uploading...' : 'Upload PDF' }}
              </SecondaryButton>
            </template>
          </div>

          <div v-if="uploadError" class="text-xs text-red-600 mt-2">{{ uploadError }}</div>
        </div>

        <!-- Privasi Tab -->
        <div v-show="tab==='privasi'" class="space-y-3">
          <div class="text-sm font-semibold text-gray-900">Privasi & Data</div>
          <div class="text-xs text-gray-600">Anda dapat meminta salinan data atau penghapusan data tertentu.</div>
          <div class="flex items-center gap-3">
            <SecondaryButton @click="requestExport">
              Minta Export Data
            </SecondaryButton>
            <SecondaryButton @click="requestDelete">
              Minta Penghapusan Data
            </SecondaryButton>
          </div>
        </div>
      </div>

      <div v-else class="text-gray-600">Data belum lengkap. Silakan hubungi Admin Unit untuk melengkapi onboarding.</div>
    </div>

    <!-- Edit Modal -->
    <ModalBase v-if="editOpen" @close="editOpen=false" title="Lengkapi Profil">
      <div class="space-y-3">
        <div>
          <label class="text-xs text-gray-600">Alamat</label>
          <textarea v-model="form.address" class="mt-1 rounded border px-3 py-2 text-sm w-full" rows="2" placeholder="Alamat lengkap"></textarea>
        </div>
        <div>
          <label class="text-xs text-gray-600">Nomor Telepon</label>
          <input v-model="form.phone" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Nomor Telepon" />
          <div v-if="errors.phone" class="text-xs text-red-600 mt-1">{{ errors.phone }}</div>
        </div>
        <div>
          <label class="text-xs text-gray-600">Kontak Darurat</label>
          <input v-model="form.emergency_contact" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Nama & nomor kontak darurat" />
        </div>
        <div class="flex justify-end">
          <PrimaryButton @click="submitUpdate">
            Simpan
          </PrimaryButton>
        </div>
      </div>
    </ModalBase>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import OptimizedImage from '@/Components/OptimizedImage.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import { usePage, router } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

const page = usePage();
const member = page.props.member;
const updateRequests = page.props.updateRequests || [];
const tab = ref('profil');
const photoInput = ref(null);
const selectedPhoto = ref(null);
const photoPreview = ref(null);
const photoError = ref('');
const uploadingPhoto = ref(false);
const uploadSuccess = ref(false);

try {
  const q = (page.url.split('?')[1] || '').split('&').reduce((a,p)=>{ const [k,v] = p.split('='); if (k) a[k]=decodeURIComponent(v||''); return a; }, {});
  if (q.tab) tab.value = q.tab;
} catch(e) {}

const form = reactive({
  address: member?.address || '',
  phone: member?.phone || '',
  emergency_contact: member?.emergency_contact || ''
});
const errors = reactive({ phone: '' });
const editOpen = ref(false);
const uploading = ref(false);
const uploadError = ref('');

// Latest request for status banner
const latestRequest = computed(() => updateRequests.length > 0 ? updateRequests[0] : null);

// Check if there's a pending request to disable submit button
const isPendingRequest = computed(() => latestRequest.value?.status === 'pending');

function openEdit() { editOpen.value = true; }

function submitUpdate() {
  if (form.phone && !/^\+?[1-9]\d{7,14}$/.test(form.phone)) {
    errors.phone = 'Format nomor tidak valid';
    return;
  } else {
    errors.phone = '';
  }
  router.post('/member/portal/request-update', form, {
    onSuccess: () => {
      editOpen.value = false;
    },
  });
}

function hasDoc(type) {
  const docs = member?.documents || [];
  return docs.some(d => (d.type || '').toLowerCase() === type.toLowerCase() ||
                        (d.original_name || '').toLowerCase().includes(type.toLowerCase()));
}

function getDocUrl(type) {
  const docs = member?.documents || [];
  const doc = docs.find(d => (d.type || '').toLowerCase() === type.toLowerCase() ||
                             (d.original_name || '').toLowerCase().includes(type.toLowerCase()));
  return doc ? '/storage/' + doc.path : null;
}

function uploadSurat(event) {
  const file = event.target.files[0];
  if (!file) return;

  uploadError.value = '';

  // Validate file type
  if (file.type !== 'application/pdf') {
    uploadError.value = 'File harus berformat PDF';
    return;
  }

  // Validate file size (max 2MB)
  if (file.size > 2 * 1024 * 1024) {
    uploadError.value = 'Ukuran file maksimal 2MB';
    return;
  }

  uploading.value = true;

  const formData = new FormData();
  formData.append('file', file);
  formData.append('type', 'surat_pernyataan');

  router.post('/member/document/upload', formData, {
    forceFormData: true,
    onSuccess: () => {
      uploading.value = false;
    },
    onError: (errors) => {
      uploading.value = false;
      uploadError.value = errors.file || 'Gagal upload file';
    }
  });
}

function statusBadgeClass(s) {
  switch (s) {
    case 'aktif': return 'bg-status-success-light text-status-success-dark border-status-success';
    case 'cuti': return 'bg-status-warning-light text-status-warning-dark border-status-warning';
    case 'suspended': return 'bg-status-error-light text-status-error-dark border-status-error';
    default: return 'bg-neutral-100 text-neutral-700 border-neutral-200';
  }
}

function requestStatusClass(status) {
  switch (status) {
    case 'approved': return 'bg-status-success-light text-status-success-dark';
    case 'rejected': return 'bg-status-error-light text-status-error-dark';
    case 'pending': return 'bg-status-warning-light text-status-warning-dark';
    default: return 'bg-neutral-100 text-neutral-800';
  }
}

function requestExport() { router.post('/member/data/export-request'); }
function requestDelete() { router.post('/member/data/delete-request'); }

function onPhotoSelect(event) {
  const file = event.target.files[0];
  if (!file) return;

  photoError.value = '';
  uploadSuccess.value = false;

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    photoError.value = 'Format harus JPG, PNG, atau WebP';
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    photoError.value = 'Ukuran file maksimal 5MB';
    return;
  }

  selectedPhoto.value = file;
  photoPreview.value = URL.createObjectURL(file);

  uploadPhoto();
}

function uploadPhoto() {
  if (!selectedPhoto.value) return;

  uploadingPhoto.value = true;
  photoError.value = '';

  const formData = new FormData();
  formData.append('photo', selectedPhoto.value);

  router.post('/member/profile/photo', formData, {
    forceFormData: true,
    onSuccess: () => {
      uploadingPhoto.value = false;
      uploadSuccess.value = true;

      // Clear selected file and preview after showing success
      setTimeout(() => {
        selectedPhoto.value = null;
        photoPreview.value = null;
        uploadSuccess.value = false;
      }, 3000);

      // Reload page data without full page refresh to get updated photo
      router.reload({ only: ['member'] });
    },
    onError: (errors) => {
      uploadingPhoto.value = false;
      console.error('Upload error:', errors);
      photoError.value = errors.photo || errors.message || 'Gagal upload foto. Silakan coba lagi.';
      uploadSuccess.value = false;
    }
  });
}

function deletePhoto() {
  if (!confirm('Hapus foto profile?')) return;

  router.delete('/member/profile/photo', {
    onSuccess: () => {
      router.reload();
    },
    onError: (errors) => {
      console.error('Delete error:', errors);
      photoError.value = errors.message || 'Gagal menghapus foto';
    }
  });
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
}

function formatDateTime(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

const completeness = computed(() => {
  if (!member) return 0;
  const items = [member.address, member.phone, member.photo_path, (member.documents||[]).length>0, member.emergency_contact];
  const filled = items.filter(Boolean).length;
  return Math.round((filled/items.length)*100);
});

const years = computed(() => {
  const ys = new Set((member?.status_logs||[]).map(h => new Date(h.date).getFullYear()));
  return Array.from(ys).sort();
});
const historySearch = ref('');
const historyYear = ref('');
const filteredHistory = computed(() => {
  let arr = member?.status_logs || [];
  if (historyYear.value) arr = arr.filter(h => new Date(h.date).getFullYear() == historyYear.value);
  if (historySearch.value) arr = arr.filter(h => (h.new_status+h.notes).toLowerCase().includes(historySearch.value.toLowerCase()));
  return arr;
});

function iconClass(h) {
  const s = (h.new_status||'').toLowerCase();
  if (s.includes('mutasi')) return 'bg-blue-600';
  if (s.includes('cuti')) return 'bg-yellow-600';
  if (s.includes('suspend')) return 'bg-red-600';
  if (s.includes('aktif')) return 'bg-green-600';
  return 'bg-gray-400';
}
</script>
