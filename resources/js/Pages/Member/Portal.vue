<template>
  <AppLayout page-title="Kartu Tanda Anggota">
    <CardContainer padding="lg" shadow="sm">
      <div v-if="member" class="space-y-8 flex flex-col items-center">
        <!-- ID Card Container -->
        <div class="relative group perspective-1000">
          <!-- Card Body -->
          <div class="w-[320px] h-[504px] bg-white rounded-2xl shadow-2xl overflow-hidden relative flex flex-col transition-transform duration-500 transform group-hover:scale-[1.02]">

            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10 pointer-events-none">
              <div class="absolute -top-24 -right-24 w-64 h-64 rounded-full bg-red-600 blur-3xl"></div>
              <div class="absolute top-1/2 -left-24 w-48 h-48 rounded-full bg-blue-600 blur-3xl"></div>
              <div class="absolute -bottom-12 right-0 w-56 h-56 rounded-full bg-red-600 blur-3xl"></div>
              <div class="w-full h-full bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9IiMwMDAiIGZpbGwtb3BhY2l0eT0iMC4wNSIvPjwvc3ZnPg==')]"></div>
            </div>

            <!-- Header Design -->
            <div class="relative h-32 bg-gradient-to-br from-red-700 via-red-600 to-red-800 clip-header">
              <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-white to-transparent"></div>
              <div class="absolute top-5 left-6 text-white z-10">
                <div class="flex items-center gap-3">
                  <!-- Logo Placeholder -->
                  <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center border border-white/30 shadow-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                  </div>
                  <div>
                    <h1 class="font-bold text-lg tracking-wide leading-tight">SP-PIPS</h1>
                    <p class="text-[10px] text-red-100 uppercase tracking-wider font-medium">Kartu Tanda Anggota</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Photo Section -->
            <div class="relative -mt-12 flex justify-center z-20">
              <div class="relative">
                <div class="w-32 h-32 rounded-full p-1 bg-white shadow-lg">
                  <img
                    :src="member?.photo_path ? '/storage/' + member.photo_path : `https://ui-avatars.com/api/?name=${member?.full_name || 'A'}&background=random&size=256`"
                    class="w-full h-full rounded-full object-cover border-4 border-red-50"
                    alt="Member Photo"
                  />
                </div>
                <!-- Status Indicator -->
                <div
                  class="absolute bottom-2 right-2 w-6 h-6 rounded-full border-2 border-white flex items-center justify-center shadow-sm"
                  :class="{
                    'bg-green-500': member?.status === 'aktif',
                    'bg-yellow-500': member?.status === 'cuti',
                    'bg-red-500': member?.status === 'suspended',
                    'bg-gray-400': ['resign', 'pensiun'].includes(member?.status)
                  }"
                  :title="member?.status"
                >
                  <svg v-if="member?.status === 'aktif'" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- Member Details -->
            <div class="flex-1 flex flex-col items-center pt-4 px-6 text-center space-y-1">
              <h2 class="text-xl font-bold text-neutral-800 leading-tight line-clamp-2">{{ member?.full_name }}</h2>
              <p class="text-sm font-medium text-red-600">{{ member?.unit?.name }}</p>

              <div class="w-full mt-6 space-y-3">
                <div class="flex justify-between items-center border-b border-dashed border-neutral-200 pb-2">
                  <span class="text-xs text-neutral-400 uppercase tracking-wider font-semibold">KTA</span>
                  <span class="text-sm font-mono font-bold text-neutral-700">{{ member?.kta_number }}</span>
                </div>
                <div class="flex justify-between items-center border-b border-dashed border-neutral-200 pb-2">
                  <span class="text-xs text-neutral-400 uppercase tracking-wider font-semibold">Bergabung</span>
                  <span class="text-sm font-medium text-neutral-700">{{ formatDate(member?.join_date) }}</span>
                </div>
              </div>
            </div>

            <!-- Footer / Barcode -->
            <div class="mt-auto pb-6 px-6 w-full flex flex-col items-center space-y-3">
              <div class="w-full h-12 bg-neutral-100 rounded flex items-center justify-center overflow-hidden relative">
                 <!-- CSS Barcode Simulation -->
                 <div class="barcode h-8 w-full opacity-80"></div>
                 <div class="absolute inset-0 flex items-center justify-center">
                    <span class="bg-white/90 px-2 text-[10px] font-mono tracking-[0.2em] text-neutral-900 font-bold shadow-sm rounded">
                      {{ member?.kta_number || member?.nra }}
                    </span>
                 </div>
              </div>
              <p class="text-[9px] text-neutral-400 text-center leading-relaxed">
                Kartu ini adalah bukti keanggotaan resmi SP-PIPS. <br>
                Jika ditemukan, harap kembalikan ke kantor sekretariat.
              </p>
            </div>

            <!-- Bottom Accent -->
            <div class="h-2 bg-gradient-to-r from-red-700 via-red-600 to-red-800 w-full"></div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-4 w-full max-w-xs justify-center">
          <SecondaryButton @click="downloadPdf" class="w-full justify-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Unduh PDF
          </SecondaryButton>

          <a v-if="member?.qr_token" :href="`/verify-card/${member?.qr_token}`" class="flex items-center justify-center gap-2 px-4 py-2 bg-white border border-neutral-300 rounded-md font-semibold text-xs text-neutral-700 uppercase tracking-widest shadow-sm hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150 w-full text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-5.367 3 3 0 00-5.367 5.367h-.001zn" />
            </svg>
            Verifikasi
          </a>
        </div>
      </div>

      <div v-else class="text-center py-12">
        <div class="w-16 h-16 bg-neutral-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-medium text-neutral-900">Data Belum Lengkap</h3>
        <p class="mt-1 text-sm text-neutral-500 max-w-sm mx-auto">Silakan hubungi Admin Unit untuk melengkapi proses onboarding dan penerbitan kartu anggota.</p>
      </div>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const member = page.props.member;

function downloadPdf(){ window.location.href = '/member/card/pdf'; }

function formatDate(dateString) {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}
</script>

<style scoped>
.clip-header {
  clip-path: ellipse(150% 100% at 50% 0%);
}
.barcode {
  background: repeating-linear-gradient(90deg,
    #000 0, #000 2px,
    transparent 2px, transparent 4px,
    #000 4px, #000 5px,
    transparent 5px, transparent 8px,
    #000 8px, #000 10px
  );
}
.perspective-1000 {
  perspective: 1000px;
}
</style>
