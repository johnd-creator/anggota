<template>
  <AppLayout page-title="Member Detail">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Action Buttons -->
      <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
        <!-- Back to List Button -->
        <button type="button" @click="router.get('/admin/members')" class="group flex items-center text-sm font-medium text-slate-500 hover:text-[#1E3A8A] transition-colors">
          <span class="material-icons-round mr-1 text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
          Back to List
        </button>

        <!-- Right Side Actions -->
        <div class="flex flex-wrap justify-end gap-3">
        <!-- Edit Button - Navy -->
        <button
          v-if="$page.props.auth.user.role?.name!=='pengurus'"
          @click="router.get(`/admin/members/${member.id}/edit`)"
          class="inline-flex items-center px-5 py-2.5 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-[#1E3A8A] hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all"
          type="button"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
          Edit
        </button>

        <!-- Ubah Status Button - Amber -->
        <button
          v-if="$page.props.auth.user.role?.name!=='pengurus'"
          @click="openStatusModal"
          class="inline-flex items-center px-5 py-2.5 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-[#D97706] hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all"
          type="button"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Ubah Status
        </button>

        <!-- Ajukan Mutasi Button - Teal -->
        <button
          v-if="$page.props.auth.user.role?.name!=='pengurus'"
          @click="router.get('/admin/mutations/create', { member_id: member.id })"
          class="inline-flex items-center px-5 py-2.5 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-[#0D9488] hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-all"
          type="button"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
          </svg>
          Ajukan Mutasi
        </button>
        </div>
      </div>

      <!-- Main Grid Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column (2/3) - Member Info -->
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
            <!-- Header Section with Avatar -->
            <div class="flex flex-col sm:flex-row items-center sm:items-start space-y-4 sm:space-y-0 sm:space-x-6">
              <OptimizedImage
                :src="member.photo_path"
                :alt="$toTitleCase(member.full_name || 'Member photo')"
                size="medium"
                class="h-20 w-20 rounded-full object-cover ring-4 ring-gray-50"
                loading="eager"
              />
              <div class="text-center sm:text-left">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $toTitleCase(member.full_name) }}</h1>
                <div class="mt-1 flex flex-wrap justify-center sm:justify-start items-center gap-3 text-sm text-gray-600">
                  <span class="font-medium">{{ member.nra || member.kta_number || '-' }}</span>
                  <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="statusBadgeClass(member.status)"
                  >
                    {{ member.status }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="mt-8 border-b border-gray-200">
              <nav aria-label="Tabs" class="-mb-px flex space-x-8">
                <a
                  href="#"
                  :class="tab==='profil' ? 'border-[#0F766E] text-[#0F766E]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                  class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                  @click.prevent="tab='profil'"
                >
                  Profil
                </a>
                <a
                  href="#"
                  :class="tab==='dokumen' ? 'border-[#0F766E] text-[#0F766E]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                  class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                  @click.prevent="tab='dokumen'"
                >
                  Dokumen
                </a>
                <a
                  href="#"
                  :class="tab==='riwayat' ? 'border-[#0F766E] text-[#0F766E]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                  class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                  @click.prevent="tab='riwayat'"
                >
                  Riwayat
                </a>
              </nav>
            </div>

            <!-- Tab Content -->
            <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-12">
              <!-- Profil Tab -->
              <template v-if="tab==='profil'">
                <div>
                  <dt class="text-sm font-medium text-gray-500">Unit</dt>
                  <dd class="mt-1 text-base font-semibold text-gray-900">{{ member.unit?.name || '-' }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Join Date</dt>
                  <dd class="mt-1 text-base font-semibold text-gray-900">{{ formatDate(member.join_date) }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Tanggal Gabung Perusahaan</dt>
                  <dd class="mt-1 text-base font-semibold text-gray-900">{{ formatDate(member.company_join_date) }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Email</dt>
                  <dd class="mt-1 text-base font-semibold text-gray-900 break-all">{{ member.email || '-' }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">Jabatan Serikat</dt>
                  <dd class="mt-1 text-base font-semibold text-gray-900">{{ member.union_position?.name || '-' }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">KTA Number</dt>
                  <dd class="mt-1 text-base font-semibold text-gray-900">{{ member.kta_number || '-' }}</dd>
                </div>
                <div>
                  <dt class="text-sm font-medium text-gray-500">NIP</dt>
                  <dd class="mt-1 text-base font-semibold text-gray-900">{{ member.nip || '-' }}</dd>
                </div>
              </template>

              <!-- Dokumen Tab -->
              <template v-if="tab==='dokumen' && member.documents && member.documents.length">
                <div v-for="d in member.documents" :key="d.id" class="col-span-full border rounded-lg p-4">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 2h8l4 4v12a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/>
                      </svg>
                      <div>
                        <div class="text-sm font-medium text-gray-900">{{ d.original_name || d.type }}</div>
                        <div class="text-xs text-gray-500">{{ d.size ? (d.size/1024).toFixed(1)+' KB' : '' }}</div>
                      </div>
                    </div>
                    <a
                      :href="'/storage/' + d.path"
                      target="_blank"
                      class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0F766E]"
                    >
                      Unduh
                    </a>
                  </div>
                </div>
              </template>

              <!-- Riwayat Tab (Timeline View) -->
              <template v-if="tab==='riwayat'">
                <div class="col-span-full space-y-4">
                  <div v-for="h in member.status_logs" :key="h.id" class="flex items-start gap-3">
                    <div class="w-2 h-2 rounded-full bg-[#0F766E] mt-1.5"></div>
                    <div>
                      <div class="text-sm font-medium text-gray-900">{{ h.date }} • {{ h.new_status }}</div>
                      <div class="text-xs text-gray-600">{{ h.notes }}</div>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- Right Column (1/3) - Photo Card -->
        <div class="lg:col-span-1">
          <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 h-full flex flex-col">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Foto Profile</h2>
            <div class="flex-grow flex items-center justify-center p-4 bg-gray-50 rounded-xl border border-gray-100">
              <div class="relative w-full aspect-[3/4] rounded-lg overflow-hidden shadow-sm">
                <OptimizedImage
                  v-if="member.photo_path"
                  :src="member.photo_path"
                  :alt="$toTitleCase(member.full_name)"
                  size="large"
                  class="absolute inset-0 w-full h-full object-cover hover:grayscale-0 transition-all duration-500"
                  loading="eager"
                />
                <div v-else class="absolute inset-0 flex items-center justify-center bg-gray-200">
                  <svg class="w-24 h-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Status History Table -->
      <div class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200">
          <h3 class="text-lg leading-6 font-semibold text-gray-900">Status History</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider" scope="col">Date</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider" scope="col">From</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider" scope="col">To</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider" scope="col">Notes</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!member.status_logs || member.status_logs.length === 0">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 italic" colspan="4">
                  No history available yet.
                </td>
              </tr>
              <tr v-else v-for="h in member.status_logs" :key="h.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ h.date }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ h.old_status || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ h.new_status }}</td>
                <td class="px-6 py-4 text-sm text-gray-600">{{ h.notes }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Status Change Modal -->
      <div v-if="showStatusModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
          <!-- Backdrop -->
          <div class="fixed inset-0 bg-black/50 transition-opacity" @click="closeStatusModal"></div>

          <!-- Modal -->
          <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
            <div class="mb-4">
              <h3 class="text-lg font-semibold text-gray-900">Ubah Status Member</h3>
              <p class="text-sm text-gray-500 mt-1">
                {{ $toTitleCase(member.full_name) }} - {{ member.nra || member.kta_number }}
              </p>
            </div>

            <form @submit.prevent="submitStatusChange">
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status Baru</label>
                <select
                  v-model="statusForm.new_status"
                  class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-[#0F766E] focus:ring-[#0F766E] sm:text-sm py-2.5 px-3 border"
                  required
                >
                  <option value="">Pilih Status</option>
                  <option value="aktif">Aktif</option>
                  <option value="cuti">Cuti</option>
                  <option value="suspended">Suspended</option>
                  <option value="resign">Resign</option>
                  <option value="pensiun">Pensiun</option>
                </select>
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Catatan (Opsional)</label>
                <textarea
                  v-model="statusForm.notes"
                  class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-[#0F766E] focus:ring-[#0F766E] sm:text-sm py-2.5 px-3 border"
                  rows="3"
                  placeholder="Tambahkan catatan untuk perubahan status ini..."
                ></textarea>
              </div>

              <div class="flex justify-end gap-3">
                <button
                  type="button"
                  @click="closeStatusModal"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0F766E]"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  :disabled="!statusForm.new_status || submitting"
                  class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-[#D97706] hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg v-if="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ submitting ? 'Menyimpan...' : 'Simpan Status' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import OptimizedImage from '@/Components/OptimizedImage.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

const page = usePage();
const member = page.props.member;
const tab = ref('profil');

// Status Modal
const showStatusModal = ref(false);
const submitting = ref(false);
const statusForm = reactive({
  new_status: '',
  notes: ''
});

function statusBadgeClass(s) {
  switch (s) {
    case 'aktif': return 'bg-green-100 text-green-800';
    case 'cuti': return 'bg-yellow-100 text-yellow-800';
    case 'suspended': return 'bg-red-100 text-red-800';
    case 'resign': return 'bg-gray-100 text-gray-800';
    case 'pensiun': return 'bg-gray-100 text-gray-800';
    default: return 'bg-gray-100 text-gray-800';
  }
}

function formatDate(d) {
  if (!d) return '-';
  const date = new Date(d);
  if (Number.isNaN(date.getTime())) return d;
  return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function openStatusModal() {
  statusForm.new_status = member.status || '';
  statusForm.notes = '';
  showStatusModal.value = true;
}

function closeStatusModal() {
  showStatusModal.value = false;
  statusForm.new_status = '';
  statusForm.notes = '';
}

function submitStatusChange() {
  if (!statusForm.new_status) return;

  submitting.value = true;

  router.put(`/admin/members/${member.id}`, {
    status: statusForm.new_status,
    notes: statusForm.notes
  }, {
    onSuccess: () => {
      closeStatusModal();
      submitting.value = false;
    },
    onError: () => {
      submitting.value = false;
    }
  });
}
</script>
