<template>
  <AppLayout page-title="Profil Anggota">
    <AlertBanner type="info" title="Privasi" message="Data Anda digunakan untuk keperluan keanggotaan. Dengan melanjutkan, Anda menyetujui pengolahan data sesuai kebijakan privasi." />

    <!-- Success Message Banner -->
    <transition name="fade">
      <AlertBanner 
        v-if="successMessage" 
        type="success" 
        :message="successMessage" 
        class="mb-4"
      />
    </transition>

    <!-- Error Message Banner -->
    <transition name="fade">
      <AlertBanner 
        v-if="errorMessage" 
        type="error" 
        :message="errorMessage" 
        class="mb-4"
      />
    </transition>

    <CardContainer padding="lg" shadow="sm">
      <div v-if="member" class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
          <img :src="member?.photo_path ? '/storage/' + member.photo_path : `https://ui-avatars.com/api/?name=${member?.full_name || 'A'}&background=random`" class="h-16 w-16 rounded-full object-cover object-center" />
          <div class="flex-1">
            <h2 class="text-xl font-semibold text-neutral-900">{{ member?.full_name }}</h2>
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

        <div v-show="tab==='profil'" class="space-y-4">
          <!-- Read-only fields -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-neutral-600">Tempat Lahir</label>
              <p class="mt-1 text-sm text-neutral-900 bg-neutral-50 rounded border px-3 py-2">{{ member?.birth_place || '-' }}</p>
            </div>
            <div>
              <label class="text-xs text-neutral-600">Tanggal Lahir</label>
              <p class="mt-1 text-sm text-neutral-900 bg-neutral-50 rounded border px-3 py-2">{{ formatDate(member?.birth_date) }}</p>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-neutral-600">Tanggal Gabung Perusahaan</label>
                <p class="mt-1 text-sm text-neutral-900 bg-neutral-50 rounded border px-3 py-2">{{ formatDate(member?.company_join_date) }}</p>
            </div>
            <div>
                <label class="text-xs text-neutral-600">Masa Kerja</label>
                <p class="mt-1 text-sm text-neutral-900 bg-neutral-50 rounded border px-3 py-2">{{ $page.props.auth.user.employment_info?.duration_string || '-' }}</p>
            </div>
          </div>
          
          <!-- Editable fields -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-neutral-600">Alamat</label>
              <textarea v-model="form.address" class="mt-1 rounded border px-3 py-2 text-sm w-full" rows="2" placeholder="Alamat lengkap"></textarea>
            </div>
            <div>
              <label class="text-xs text-neutral-600">Nomor Telepon</label>
              <input v-model="form.phone" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Nomor Telepon" />
              <div v-if="errors.phone" class="text-xs text-status-error mt-1">{{ errors.phone }}</div>
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
          <div class="flex flex-col items-end gap-2">
            <PrimaryButton 
              @click="submitUpdate" 
              :disabled="isPendingRequest"
              :title="isPendingRequest ? 'Permintaan sebelumnya masih menunggu persetujuan' : ''"
            >
              {{ isPendingRequest ? 'Menunggu Persetujuan' : 'Ajukan Perubahan' }}
            </PrimaryButton>
            <p class="text-xs text-neutral-500">
              Permintaan perubahan akan muncul di halaman <strong>Admin Updates</strong> untuk disetujui.
            </p>
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
import { usePage, router } from '@inertiajs/vue3';
import { reactive, ref, computed, onMounted, watch } from 'vue';

const page = usePage();
const member = page.props.member;
const updateRequests = page.props.updateRequests || [];
const tab = ref('profil');

// Message handling
const successMessage = ref('');
const errorMessage = ref('');

// Auto-hide messages after 4 seconds
function showSuccess(message) {
  successMessage.value = message;
  errorMessage.value = '';
  setTimeout(() => {
    successMessage.value = '';
  }, 4000);
}

function showError(message) {
  errorMessage.value = message;
  successMessage.value = '';
  setTimeout(() => {
    errorMessage.value = '';
  }, 4000);
}

// Read flash messages on mount
onMounted(() => {
  const flash = page.props.flash;
  if (flash?.success) {
    showSuccess(flash.success);
  }
  if (flash?.error) {
    showError(flash.error);
  }
});

// Watch for flash changes (Inertia page reload)
watch(() => page.props.flash?.success, (newVal) => {
  if (newVal) {
    showSuccess(newVal);
  }
});

watch(() => page.props.flash?.error, (newVal) => {
  if (newVal) {
    showError(newVal);
  }
});

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
    onSuccess: (page) => {
      editOpen.value = false;
      // Show message immediately from flash
      const flash = page?.props?.flash;
      if (flash?.success) {
        showSuccess(flash.success);
      }
      if (flash?.error) {
        showError(flash.error);
      }
    }
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
