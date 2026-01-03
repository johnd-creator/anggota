<template>
  <AppLayout title="Iuran Saya">
    <template #page-title>Iuran Saya</template>

    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6">
      <!-- Empty State: No Member -->
      <div v-if="!hasMember" class="bg-white rounded-lg shadow p-8 text-center">
        <div class="text-neutral-400 mb-4">
          <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h3 class="text-lg font-semibold text-neutral-700 mb-2">Akun Belum Terhubung</h3>
        <p class="text-neutral-500 text-sm">Akun Anda belum terhubung ke data anggota. Silakan hubungi admin unit untuk menghubungkan akun Anda.</p>
      </div>

      <!-- Summary Card -->
      <div v-else-if="summary" class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h3 class="text-sm font-medium text-neutral-500">Status Iuran {{ formatPeriod(summary.current_period) }}</h3>
            <div class="mt-1 flex items-center gap-2">
              <span :class="statusBadgeClass(summary.current_status)" class="px-3 py-1 rounded-full text-sm font-medium">
                {{ summary.current_status === 'paid' ? 'Sudah Bayar' : 'Belum Bayar' }}
              </span>
            </div>
          </div>
          <div v-if="summary.unpaid_count > 0" class="text-left sm:text-right">
            <p class="text-sm text-neutral-500">Tunggakan</p>
            <p class="text-2xl font-bold text-red-600">{{ summary.unpaid_count }} bulan</p>
          </div>
          <div v-else class="text-left sm:text-right">
            <p class="text-sm text-neutral-500">Tunggakan</p>
            <p class="text-2xl font-bold text-green-600">Nihil ✓</p>
          </div>
        </div>

        <!-- Payment Info -->
        <div class="mt-4 pt-4 border-t border-neutral-100">
          <p class="text-sm text-neutral-600">
            💳 Iuran bulanan: <strong>Rp {{ formatCurrency(default_amount) }}</strong>
          </p>
          <p class="text-xs text-neutral-400 mt-1">
            Pembayaran dilakukan ke bendahara unit. Status akan diperbarui setelah bendahara mencatat pembayaran.
          </p>
        </div>
      </div>

      <!-- Payments Table -->
      <div v-if="hasMember" class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-100">
          <h3 class="font-semibold text-neutral-800">Riwayat Iuran (12 Bulan Terakhir)</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Periode</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Jumlah</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tanggal Bayar</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Catatan</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="payment in payments" :key="payment.period">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-neutral-900">
                  {{ formatPeriod(payment.period) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="statusBadgeClass(payment.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ payment.status === 'paid' ? 'Sudah Bayar' : 'Belum Bayar' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-600">
                  Rp {{ formatCurrency(payment.amount) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                  {{ payment.paid_at || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                  {{ payment.notes || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  hasMember: Boolean,
  payments: Array,
  summary: Object,
  default_amount: {
    type: Number,
    default: 30000
  }
})

function formatPeriod(period) {
  if (!period) return '-'
  const [year, month] = period.split('-')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
  return `${months[parseInt(month) - 1]} ${year}`
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID').format(amount)
}

function statusBadgeClass(status) {
  return status === 'paid'
    ? 'bg-green-100 text-green-800'
    : 'bg-red-100 text-red-800'
}
</script>
