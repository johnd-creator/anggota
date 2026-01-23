<template>
  <AppLayout page-title="Kotak Masuk Surat">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Kotak Masuk</h2>
          <p class="text-sm text-neutral-500">Surat yang ditujukan kepada Anda.</p>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <SummaryCard title="Total Surat" :value="stats.total" color="blue" icon="inbox" />
        <SummaryCard title="Belum Dibaca" :value="stats.unread" color="yellow" icon="document" />
        <SummaryCard title="Minggu Ini" :value="stats.this_week" color="green" icon="clock" />
      </div>

      <!-- Filters -->
      <CardContainer padding="sm">
        <div class="flex flex-wrap items-center gap-3">
          <div class="w-full max-w-md">
            <InputField v-model="search" placeholder="Cari perihal surat..." class="w-full" />
          </div>
          <div>
            <select v-model="status" class="rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Semua Status</option>
              <option value="submitted">Diajukan</option>
              <option value="approved">Disetujui</option>
              <option value="sent">Terkirim</option>
              <option value="archived">Diarsipkan</option>
            </select>
          </div>
          <div>
            <select v-model="categoryId" class="rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Semua Kategori</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.code }} - {{ cat.name }}</option>
            </select>
          </div>
        </div>
      </CardContainer>

      <!-- Letters Table (Desktop) -->
      <CardContainer padding="none" class="hidden md:block overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">No. Surat</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Kategori</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Perihal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Dari</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="letter in letters.data" :key="letter.id" class="hover:bg-neutral-50">
                <td class="px-6 py-4 text-sm font-mono text-neutral-600">{{ letter.letter_number || '-' }}</td>
                <td class="px-6 py-4 text-sm text-neutral-900">
                  <ColorBadge :color="letter.category?.color || 'neutral'" :dot="true">
                    {{ letter.category?.code || '-' }}
                  </ColorBadge>
                </td>
                <td class="px-6 py-4 text-sm font-medium text-neutral-900">{{ letter.subject }}</td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ letter.from_unit?.name || 'Pusat' }}</td>
                <td class="px-6 py-4">
                  <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge :status="letter.status" />
                    <StatusBadge :status="letter.confidentiality" size="sm" :showDot="false" />
                    <StatusBadge :status="letter.urgency" size="sm" :showDot="false" />
                  </div>
                </td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ formatDate(letter.created_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-3">
                    <Link :href="`/letters/${letter.id}/preview`" class="text-neutral-600 hover:text-neutral-800">
                      Preview
                    </Link>
                    <Link :href="`/letters/${letter.id}`" class="text-brand-primary-600 hover:text-brand-primary-700">
                      Detail
                    </Link>
                  </div>
                </td>
              </tr>
              <tr v-if="letters.data.length === 0">
                <td colspan="7" class="px-6 py-10 text-center text-neutral-500">Tidak ada surat masuk.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <!-- Pagination -->
        <Pagination :paginator="letters" />
      </CardContainer>

      <!-- Letters Cards (Mobile) -->
      <div v-if="letters.data.length > 0" class="md:hidden space-y-3">
        <DataCard
            v-for="letter in letters.data"
            :key="letter.id"
            :title="letter.subject"
            :subtitle="letter.letter_number || `Kategori: ${letter.category?.code || '-'}`"
            :status="letter.status"
            :meta="[
                { label: 'Dari', value: letter.from_unit?.name || 'Pusat' },
                { label: 'Tanggal', value: formatDate(letter.created_at) }
            ]"
        >
            <template #actions>
                <Link :href="`/letters/${letter.id}`" class="text-brand-primary-600 text-sm font-medium">
                    Detail
                </Link>
            </template>
        </DataCard>
        <div v-if="letters.data.length === 0" class="text-center py-8 text-neutral-500">
            Tidak ada surat masuk.
        </div>
      </div>

      <!-- Mobile Pagination -->
      <div v-if="letters.data.length > 0" class="md:hidden mt-4">
        <Pagination :paginator="letters" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import SummaryCard from '@/Components/UI/SummaryCard.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import InputField from '@/Components/UI/InputField.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import ColorBadge from '@/Components/UI/ColorBadge.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import DataCard from '@/Components/Mobile/DataCard.vue'

const props = defineProps({
  letters: Object,
  categories: Array,
  filters: Object,
  stats: Object,
})

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')
const categoryId = ref(props.filters?.category_id || '')

watch([search, status, categoryId], ([s, st, cat]) => {
  router.get('/letters/inbox', { search: s, status: st, category_id: cat }, { preserveState: true, replace: true })
})

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
