<template>
  <div class="min-h-screen bg-neutral-100 flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-6">
      <!-- Logo / Header -->
      <div class="text-center mb-6">
        <img :src="logo" alt="Logo" class="w-16 h-16 mx-auto mb-2" />
        <h1 class="text-lg font-bold text-neutral-800">Verifikasi Surat</h1>
        <p class="text-sm text-neutral-500">Serikat Pekerja PT PLN Indonesia Power Services</p>
      </div>

      <!-- Valid -->
      <div v-if="valid" class="text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
          <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
        </div>
        <h2 class="text-xl font-semibold text-green-700 mb-2">Surat Valid</h2>
        <p class="text-sm text-neutral-600 mb-4">Surat ini terdaftar dalam sistem.</p>

        <!-- Letter Info -->
        <div class="bg-neutral-50 rounded-lg p-4 text-left text-sm space-y-2">
          <div class="flex justify-between">
            <span class="text-neutral-500">Nomor Surat</span>
            <span class="font-mono font-medium">{{ letter.letter_number || '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500">Kategori</span>
            <span>{{ letter.category_code }} - {{ letter.category }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500">Dari</span>
            <span>{{ letter.from_unit }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500">Tanggal</span>
            <span>{{ letter.approved_at || letter.created_at }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-500">Status</span>
            <span class="capitalize">{{ letter.status }}</span>
          </div>
          <div v-if="letter.subject && !isConfidential" class="flex justify-between">
            <span class="text-neutral-500">Perihal</span>
            <span class="text-right flex-1 ml-4">{{ letter.subject }}</span>
          </div>
        </div>

        <!-- Confidential Notice -->
        <div v-if="isConfidential" class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-700">
          <strong>Catatan:</strong> Surat ini bersifat <span class="capitalize">{{ letter.confidentiality }}</span>.
          Detail isi surat tidak dapat ditampilkan untuk alasan keamanan.
        </div>
      </div>

      <!-- Not Final (draft/submitted) -->
      <div v-else-if="notFinal" class="text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-yellow-100 flex items-center justify-center">
          <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
          </svg>
        </div>
        <h2 class="text-xl font-semibold text-yellow-700 mb-2">Belum Final</h2>
        <p class="text-sm text-neutral-600">Surat ditemukan tapi belum disetujui/dikirim.</p>
        <p class="text-xs text-neutral-500 mt-2">Status: <span class="capitalize">{{ letter?.status }}</span></p>
      </div>

      <!-- Invalid -->
      <div v-else class="text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-100 flex items-center justify-center">
          <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </div>
        <h2 class="text-xl font-semibold text-red-700 mb-2">Tidak Valid</h2>
        <p class="text-sm text-neutral-600">Surat dengan token ini tidak ditemukan dalam sistem.</p>
      </div>

      <!-- Footer -->
      <div class="mt-6 text-center text-xs text-neutral-400">
        Sistem Surat Digital SP PLN IP Services
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  valid: Boolean,
  notFinal: Boolean,
  letter: Object,
  isConfidential: Boolean,
})

const logo = new URL('../../../images/logo.png', import.meta.url).href
</script>

