<template>
  <AppLayout page-title="Profil Anggota">
    <AlertBanner type="info" title="Privasi" message="Data Anda digunakan untuk keperluan keanggotaan. Dengan melanjutkan, Anda menyetujui pengolahan data sesuai kebijakan privasi." />

    <CardContainer padding="lg" shadow="sm">
      <div v-if="member" class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
          <OptimizedImage
            :src="member?.photo_path"
            :alt="$toTitleCase(member?.full_name || 'Member photo')"
            size="medium"
            class="h-16 w-16 rounded-full"
            loading="eager"
          />
          <div class="flex-1">
            <h2 class="text-xl font-semibold text-neutral-900">{{ $toTitleCase(member?.full_name) }}</h2>
            <div class="flex items-center gap-2">
              <Badge variant="brand">{{ member?.nra }}</Badge>
              <Badge :variant="statusVariant(member?.status)">{{ member?.status }}</Badge>
              <span class="text-xs text-neutral-600">{{ member?.unit?.name }}</span>
            </div>
            <div class="mt-3">
              <div class="flex items-center justify-between text-xs text-neutral-600 mb-1"><span>Kelengkapan Profil</span><span>{{ completeness }}%</span></div>
              <div class="h-2 bg-neutral-200 rounded overflow-hidden"><div class="h-2 bg-brand-primary-600" :style="{width: completeness+'%'}"></div></div>
            </div>
          </div>
          <div>
            <PrimaryButton @click="openEdit">Lengkapi Profil</PrimaryButton>
          </div>
        </div>

        <div class="border-b border-neutral-200 mb-4 flex gap-4 text-sm overflow-x-auto whitespace-nowrap">
          <button :class="tab==='profil' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='profil'">Data Pribadi</button>
          <button :class="tab==='riwayat' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='riwayat'">Riwayat Mutasi & Status</button>
          <button :class="tab==='dokumen' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='dokumen'">Checklist Dokumen</button>
          <button :class="tab==='privasi' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='privasi'">Privasi & Data</button>
        </div>

        <div v-show="tab==='profil'" class="space-y-6">
          <!-- Basic Info Section -->
          <div>
            <h3 class="text-sm font-semibold text-neutral-700 mb-3">Informasi Dasar</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="text-xs text-neutral-600">Tempat Lahir</label>
                <p class="mt-1 text-sm text-neutral-900 bg-neutral-50 rounded border px-3 py-2">{{ member?.birth_place || '-' }}</p>
              </div>
              <div>
                <label class="text-xs text-neutral-600">Tanggal Lahir</label>
                <p class="mt-1 text-sm text-neutral-900 bg-neutral-50 rounded border px-3 py-2">{{ formatDate(member?.birth_date) }}</p>
              </div>
              <div>
                <label class="text-xs text-neutral-600">Tanggal Gabung Perusahaan</label>
                <p class="mt-1 text-sm text-neutral-900 bg-neutral-50 rounded border px-3 py-2">{{ formatDate(member?.company_join_date) }}</p>
              </div>
              <div>
                <label class="text-xs text-neutral-600">Masa Kerja</label>
                <p class="mt-1 text-sm text-neutral-900 bg-neutral-50 rounded border px-3 py-2">{{ $page.props.auth.user.employment_info?.duration_string || '-' }}</p>
              </div>
            </div>
          </div>

          <!-- Photo Section - Auto Save -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 sm:p-5">
            <div class="flex items-center gap-2 mb-4">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <h3 class="text-sm font-semibold text-blue-900">Foto Profile</h3>
              <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Otomatis disimpan</span>
            </div>

            <div class="flex flex-col sm:flex-row items-start gap-4">
              <!-- Photo Preview with Status -->
              <div class="relative flex-shrink-0">
                <OptimizedImage
                  :src="photoPreview || member?.photo_path"
                  :alt="member?.full_name || 'Profile photo'"
                  size="medium"
                  class="h-32 w-32 rounded-full object-cover border-4 border-white shadow-lg"
                  loading="eager"
                />
                <!-- Loading Overlay -->
                <div v-if="uploadingPhoto" class="absolute inset-0 bg-white/90 rounded-full flex items-center justify-center">
                  <svg class="w-8 h-8 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none">
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

              <!-- Controls -->
              <div class="flex-1 space-y-3">
                <input
                  type="file"
                  ref="photoInput"
                  accept="image/jpeg,image/png,image/webp"
                  class="hidden"
                  @change="onPhotoSelect"
                />

                <div class="flex flex-wrap gap-2">
                  <SecondaryButton
                    @click="$refs.photoInput.click()"
                    :disabled="uploadingPhoto"
                    size="sm"
                  >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ uploadingPhoto ? 'Mengupload...' : (member?.photo_path ? 'Ganti Foto' : 'Upload Foto') }}
                  </SecondaryButton>

                  <button
                    v-if="member?.photo_path && !photoPreview"
                    type="button"
                    @click="deletePhoto"
                    :disabled="uploadingPhoto"
                    class="px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50"
                  >
                    Hapus
                  </button>
                </div>

                <!-- Selected File Info -->
                <div v-if="selectedPhoto" class="text-xs bg-white rounded border border-blue-200 p-2">
                  <p class="font-medium text-neutral-700">{{ selectedPhoto.name }}</p>
                  <p class="text-neutral-600">{{ (selectedPhoto.size / 1024).toFixed(1) }} KB</p>
                  <p v-if="photoError" class="text-red-600 mt-1">{{ photoError }}</p>
                  <p v-else-if="selectedPhoto.size > 500 * 1024" class="text-amber-600 mt-1">
                    File akan dikompresi otomatis
                  </p>
                </div>

                <!-- Helper Text -->
                <p class="text-xs text-blue-800">
                  <span class="font-medium">Klik tombol di atas</span> untuk memilih foto. Foto akan otomatis disimpan. Format: JPG, PNG, WebP. Maks 5MB.
                </p>
              </div>
            </div>
          </div>

          <!-- Divider -->
          <div class="border-t border-neutral-200"></div>

          <!-- Data Personal Section - Needs Approval -->
          <div class="space-y-4">
            <!-- Editable fields -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2">
                <p class="text-xs text-neutral-500 mb-3">
                  Ubah data di bawah ini. Perubahan memerlukan persetujuan admin sebelum disimpan.
                </p>
              </div>
              <div>
                <label class="text-xs text-neutral-600">Alamat</label>
                <textarea v-model="form.address" class="mt-1 rounded border px-3 py-2 text-sm w-full" rows="2" placeholder="Alamat lengkap"></textarea>
              </div>
              <div>
                <label class="text-xs text-neutral-600">Nomor Telepon</label>
                <input v-model="form.phone" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Nomor Telepon" />
                <div v-if="errors.phone" class="text-xs text-status-error mt-1">{{ errors.phone }}</div>
              </div>
              <div>
                <label class="text-xs text-neutral-600">Kontak Darurat</label>
                <input v-model="form.emergency_contact" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Nama & nomor kontak darurat" />
              </div>
            </div>

            <!-- Update History -->
            <div>
              <h4 class="text-sm font-semibold text-neutral-700 mb-2">Riwayat Perubahan</h4>
              <div v-if="updateRequests.length" class="border rounded-lg p-3 flex items-center justify-between">
                <div class="text-sm">Terakhir: {{ updateRequests[0].status }} • {{ formatDateTime(updateRequests[0].created_at) }}</div>
                <Badge :variant="updateRequests[0].status==='approved'?'success':(updateRequests[0].status==='rejected'?'danger':'warning')">{{ updateRequests[0].status }}</Badge>
              </div>
              <div v-else class="text-xs text-neutral-500">Belum ada riwayat perubahan data.</div>
            </div>

            <!-- Submit Button -->
            <div class="flex flex-col items-end gap-2">
              <PrimaryButton
                @click="submitUpdate"
                :disabled="isPendingRequest"
                :title="isPendingRequest ? 'Permintaan sebelumnya masih menunggu persetujuan' : ''"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ isPendingRequest ? 'Menunggu Persetujuan' : 'Ajukan Perubahan Data' }}
              </PrimaryButton>
              <p class="text-xs text-neutral-500">
                Perubahan alamat, telepon, dan kontak darurat memerlukan persetujuan admin.
              </p>
            </div>
          </div>
        </div>

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
              <div class="text-sm text-neutral-900">{{ h.date }} • {{ h.new_status }}</div>
              <div class="text-xs text-neutral-600">{{ h.notes }}</div>
            </div>
          </div>
        </div>

        <!-- Document Checklist Tab -->
        <div v-show="tab==='dokumen'" class="space-y-3">
          <!-- Surat Pernyataan -->
          <div class="flex items-center justify-between p-3 border rounded-lg">
            <div class="flex items-center gap-2">
              <Badge :variant="hasDoc('surat_pernyataan') ? 'success':'neutral'">Surat Pernyataan</Badge>
              <span class="text-sm text-neutral-500">(Opsional, PDF max 2MB)</span>
            </div>
            <template v-if="hasDoc('surat_pernyataan')">
              <a :href="getDocUrl('surat_pernyataan')" target="_blank" class="text-sm text-brand-primary-600 hover:underline">Download</a>
            </template>
            <template v-else>
              <input type="file" ref="suratInput" accept=".pdf" @change="uploadSurat" class="hidden" />
              <SecondaryButton size="sm" @click="$refs.suratInput.click()" :disabled="uploading">
                {{ uploading ? 'Uploading...' : 'Upload PDF' }}
              </SecondaryButton>
            </template>
          </div>
          
          <div v-if="uploadError" class="text-xs text-status-error mt-2">{{ uploadError }}</div>
        </div>

        <div v-show="tab==='privasi'" class="space-y-3">
          <div class="text-sm font-semibold text-neutral-900">Privasi & Data</div>
          <div class="text-xs text-neutral-600">Anda dapat meminta salinan data atau penghapusan data tertentu.</div>
          <div class="flex items-center gap-3">
            <SecondaryButton @click="requestExport">Minta Export Data</SecondaryButton>
            <SecondaryButton @click="requestDelete">Minta Penghapusan Data</SecondaryButton>
          </div>
        </div>

        <ModalBase v-if="editOpen" @close="editOpen=false" title="Lengkapi Profil">
          <div class="space-y-3">
            <div>
              <label class="text-xs text-neutral-600">Alamat</label>
              <textarea v-model="form.address" class="mt-1 rounded border px-3 py-2 text-sm w-full" rows="2" placeholder="Alamat lengkap"></textarea>
            </div>
            <div>
              <label class="text-xs text-neutral-600">Nomor Telepon</label>
              <input v-model="form.phone" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Nomor Telepon" />
              <div v-if="errors.phone" class="text-xs text-status-error mt-1">{{ errors.phone }}</div>
            </div>
            <div>
              <label class="text-xs text-neutral-600">Kontak Darurat</label>
              <input v-model="form.emergency_contact" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Nama & nomor kontak darurat" />
            </div>
            <div class="flex justify-end"><PrimaryButton @click="submitUpdate">Simpan</PrimaryButton></div>
          </div>
        </ModalBase>
      </div>
      <div v-else class="text-neutral-600">Data belum lengkap. Silakan hubungi Admin Unit untuk melengkapi onboarding.</div>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import Badge from '@/Components/UI/Badge.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import OptimizedImage from '@/Components/OptimizedImage.vue';
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

function statusVariant(s) { 
  switch (s) { 
    case 'aktif': return 'success'; 
    case 'cuti': return 'warning'; 
    case 'suspended': return 'danger'; 
    case 'resign': return 'neutral'; 
    case 'pensiun': return 'neutral'; 
    default: return 'neutral'; 
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
  if (s.includes('mutasi')) return 'bg-brand-secondary-600'; 
  if (s.includes('cuti')) return 'bg-status-warning-dark'; 
  if (s.includes('suspend')) return 'bg-status-error-dark'; 
  if (s.includes('aktif')) return 'bg-status-success-dark'; 
  return 'bg-neutral-400'; 
}
</script>
