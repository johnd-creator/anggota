<template>
  <AppLayout page-title="Import Anggota">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Import Anggota</h2>
          <p class="text-sm text-neutral-500">Upload file CSV/XLSX untuk mengimpor anggota secara batch.</p>
        </div>
        <SecondaryButton href="/admin/members">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          Kembali
        </SecondaryButton>
      </div>

      <!-- Step 1: Upload -->
      <CardContainer v-if="step === 'upload'" padding="md">
        <h3 class="font-medium text-neutral-800 mb-4">1. Upload File</h3>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-sm text-neutral-600">Download template untuk format yang benar:</span>
            <a href="/admin/members/import/template" target="_blank" class="inline-flex items-center px-3 py-1.5 border rounded text-xs hover:bg-neutral-50">
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
              Unduh Template
            </a>
          </div>
          <div class="border-2 border-dashed rounded-lg p-6 text-center" :class="dragOver ? 'border-brand-primary-500 bg-brand-primary-50' : 'border-neutral-300'" @dragover.prevent="dragOver=true" @dragleave.prevent="dragOver=false" @drop.prevent="onDrop">
            <input type="file" ref="fileInput" @change="onFileChange" accept=".csv,.xlsx,.xls" class="hidden" />
            <div v-if="!file" class="space-y-2">
              <svg class="mx-auto w-12 h-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
              <p class="text-sm text-neutral-600">Drag & drop file atau <button @click="$refs.fileInput.click()" class="text-brand-primary-600 hover:underline">pilih file</button></p>
              <p class="text-xs text-neutral-500">CSV, XLSX, atau XLS (max 5MB)</p>
            </div>
            <div v-else class="flex items-center justify-center gap-3">
              <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <div class="text-left">
                <p class="font-medium text-neutral-800">{{ file.name }}</p>
                <p class="text-xs text-neutral-500">{{ formatBytes(file.size) }}</p>
              </div>
              <button @click="file=null" class="p-1 text-neutral-400 hover:text-neutral-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
          </div>
          <div class="flex justify-end">
            <PrimaryButton :disabled="!file || previewing" @click="doPreview">
              <span v-if="previewing" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                Memproses...
              </span>
              <span v-else>Preview Import</span>
            </PrimaryButton>
          </div>
        </div>
      </CardContainer>

      <!-- Step 2: Preview -->
      <CardContainer v-if="step === 'preview'" padding="md">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-medium text-neutral-800">2. Preview Hasil</h3>
          <button @click="resetToUpload" class="text-sm text-brand-primary-600 hover:underline">Upload file lain</button>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-neutral-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-neutral-800">{{ batch.total_rows }}</div>
            <div class="text-sm text-neutral-600">Total Baris</div>
          </div>
          <div class="bg-emerald-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-emerald-600">{{ batch.valid_rows }}</div>
            <div class="text-sm text-emerald-700">Valid</div>
          </div>
          <div class="bg-amber-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-amber-600">{{ batch.invalid_rows }}</div>
            <div class="text-sm text-amber-700">Invalid</div>
          </div>
          <div class="bg-brand-primary-50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-brand-primary-600">{{ batch.original_filename }}</div>
            <div class="text-sm text-brand-primary-700">File</div>
          </div>
        </div>

        <!-- Error Sample -->
        <div v-if="errors.length" class="mb-6">
          <h4 class="font-medium text-amber-800 mb-2">Contoh Error (max 20 baris):</h4>
          
          <!-- Legend -->
          <div class="flex items-center gap-6 mb-3 text-sm text-neutral-600 bg-neutral-50 rounded-lg p-3">
            <span class="font-medium">Keterangan:</span>
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
              Critical (wajib diperbaiki)
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
              Warning (opsional tapi perlu perbaikan)
            </span>
          </div>
          
          <div class="border rounded overflow-hidden">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
              <thead class="bg-amber-50">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-amber-800 w-16">Baris</th>
                  <th class="px-3 py-2 text-left font-medium text-amber-800 w-32">Field</th>
                  <th class="px-3 py-2 text-left font-medium text-amber-800 w-32">Nilai Saat Ini</th>
                  <th class="px-3 py-2 text-left font-medium text-amber-800">Error</th>
                  <th class="px-3 py-2 text-left font-medium text-amber-800 w-48">Format yang Benar</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-100 bg-white">
                <template v-for="(row, rowIdx) in processedErrors.slice(0, 20)" :key="row.row_number">
                  <template v-for="(err, errIdx) in row.errors" :key="`${row.row_number}-${errIdx}`">
                    <tr :class="errIdx > 0 ? 'border-t border-neutral-50' : ''">
                      <td v-if="errIdx === 0" :rowspan="row.errors.length" class="px-3 py-2 text-neutral-600 font-medium align-top border-r border-neutral-100">
                        {{ row.row_number }}
                      </td>
                      <td class="px-3 py-2 text-neutral-700">
                        <span class="flex items-center gap-1.5">
                          <span 
                            class="w-2 h-2 rounded-full flex-shrink-0" 
                            :class="err.severity === 'critical' ? 'bg-red-500' : 'bg-amber-500'"
                          ></span>
                          {{ err.field || '-' }}
                        </span>
                      </td>
                      <td class="px-3 py-2 text-neutral-500 font-mono text-xs">
                        {{ err.current_value || '(kosong)' }}
                      </td>
                      <td 
                        class="px-3 py-2"
                        :class="err.severity === 'critical' ? 'text-red-700' : 'text-amber-700'"
                      >
                        {{ err.message }}
                      </td>
                      <td class="px-3 py-2 text-neutral-500 text-xs">
                        {{ err.expected_format || '-' }}
                      </td>
                    </tr>
                  </template>
                </template>
              </tbody>
            </table>
          </div>
          
          <!-- Total error count and download link -->
          <div v-if="batch.invalid_rows > 20" class="mt-2 text-sm text-neutral-600">
            Menampilkan 20 dari {{ batch.invalid_rows }} baris error. 
            <button @click="downloadErrors" class="text-brand-primary-600 hover:underline">
              Download semua errors
            </button>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-3 justify-end">
          <SecondaryButton v-if="batch.invalid_rows > 0" @click="downloadErrors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Download Errors CSV
          </SecondaryButton>
          <PrimaryButton v-if="batch.valid_rows > 0" :disabled="committing" @click="doCommit">
            <span v-if="committing" class="flex items-center gap-2">
              <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
              Memproses...
            </span>
            <span v-else>Commit Import ({{ batch.valid_rows }} baris valid)</span>
          </PrimaryButton>
        </div>
      </CardContainer>

      <!-- Step 3: Completed -->
      <CardContainer v-if="step === 'completed'" padding="md">
        <div class="text-center py-8">
          <svg class="mx-auto w-16 h-16 text-emerald-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <h3 class="text-xl font-semibold text-neutral-800 mb-2">Import Selesai!</h3>
          <div class="grid grid-cols-3 gap-4 max-w-md mx-auto mb-6">
            <div class="bg-emerald-50 rounded-lg p-3">
              <div class="text-2xl font-bold text-emerald-600">{{ result.created_count }}</div>
              <div class="text-xs text-emerald-700">Dibuat</div>
            </div>
            <div class="bg-blue-50 rounded-lg p-3">
              <div class="text-2xl font-bold text-blue-600">{{ result.updated_count }}</div>
              <div class="text-xs text-blue-700">Diperbarui</div>
            </div>
            <div class="bg-amber-50 rounded-lg p-3">
              <div class="text-2xl font-bold text-amber-600">{{ result.error_count }}</div>
              <div class="text-xs text-amber-700">Gagal</div>
            </div>
          </div>
          <div class="flex justify-center gap-3">
            <SecondaryButton @click="resetToUpload">Import Lagi</SecondaryButton>
            <PrimaryButton href="/admin/members">Lihat Daftar Anggota</PrimaryButton>
          </div>
        </div>
      </CardContainer>

      <!-- Error Alert -->
      <AlertBanner v-if="errorMessage" type="error" :message="errorMessage" dismissible @dismiss="errorMessage=''" />
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import { ref, reactive, computed } from 'vue';
import axios from 'axios';

const step = ref('upload');
const file = ref(null);
const dragOver = ref(false);
const previewing = ref(false);
const committing = ref(false);
const errorMessage = ref('');

const batch = reactive({ id: null, total_rows: 0, valid_rows: 0, invalid_rows: 0, original_filename: '' });
const errors = ref([]);
const result = reactive({ created_count: 0, updated_count: 0, error_count: 0 });

// Computed property to process errors for display
const processedErrors = computed(() => {
  return errors.value.map(row => {
    // Ensure errors is always an array of structured objects
    const normalizedErrors = (row.errors || []).map(err => {
      // Handle new structured format
      if (typeof err === 'object' && err.field) {
        return {
          field: err.field || '',
          severity: err.severity || 'warning',
          current_value: err.current_value,
          message: err.message || '',
          expected_format: err.expected_format || '',
        };
      }
      // Handle legacy string format
      return {
        field: '',
        severity: 'warning',
        current_value: null,
        message: String(err),
        expected_format: '',
      };
    });
    
    return {
      row_number: row.row ?? row.row_number,
      errors: normalizedErrors,
    };
  });
});

function onFileChange(e) {
  file.value = e.target.files[0] || null;
}

function onDrop(e) {
  dragOver.value = false;
  const f = e.dataTransfer.files[0];
  if (f && /\.(csv|xlsx|xls)$/i.test(f.name)) {
    file.value = f;
  }
}

function formatBytes(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}

function resetToUpload() {
  step.value = 'upload';
  file.value = null;
  Object.assign(batch, { id: null, total_rows: 0, valid_rows: 0, invalid_rows: 0, original_filename: '' });
  errors.value = [];
  Object.assign(result, { created_count: 0, updated_count: 0, error_count: 0 });
  errorMessage.value = '';
}

async function doPreview() {
  if (!file.value) return;
  previewing.value = true;
  errorMessage.value = '';
  
  try {
    const fd = new FormData();
    fd.append('file', file.value);
    
    const response = await axios.post('/admin/members/import/preview', fd);
    const data = response.data;
    
    Object.assign(batch, data.batch);
    errors.value = data.errors || [];
    step.value = 'preview';
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Preview gagal. Periksa format file.';
  } finally {
    previewing.value = false;
  }
}

async function doCommit() {
  if (!batch.id) return;
  committing.value = true;
  errorMessage.value = '';
  
  try {
    const response = await axios.post(`/admin/members/import/${batch.id}/commit`);
    const data = response.data;
    
    Object.assign(result, data);
    step.value = 'completed';
  } catch (err) {
    if (err.response?.status === 409) {
      errorMessage.value = 'Batch sudah di-commit sebelumnya.';
    } else {
      errorMessage.value = err.response?.data?.error || 'Commit gagal.';
    }
  } finally {
    committing.value = false;
  }
}

function downloadErrors() {
  if (batch.id) {
    window.location.href = `/admin/members/import/${batch.id}/errors`;
  }
}
</script>
