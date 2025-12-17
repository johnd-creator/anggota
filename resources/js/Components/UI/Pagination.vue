<template>
  <div v-if="paginator?.links?.length > 3" class="bg-white px-4 py-3 border-t border-neutral-200 flex items-center justify-between sm:px-6">
    <!-- Mobile: simple prev/next -->
    <div class="flex flex-1 items-center justify-between sm:hidden">
      <Link
        v-if="paginator.prev_page_url"
        :href="paginator.prev_page_url"
        class="relative inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
      >
        Previous
      </Link>
      <span
        v-else
        class="relative inline-flex items-center rounded-md border border-neutral-300 bg-neutral-100 px-4 py-2 text-sm font-medium text-neutral-400 cursor-not-allowed"
      >
        Previous
      </span>

      <Link
        v-if="paginator.next_page_url"
        :href="paginator.next_page_url"
        class="relative ml-3 inline-flex items-center rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
      >
        Next
      </Link>
      <span
        v-else
        class="relative ml-3 inline-flex items-center rounded-md border border-neutral-300 bg-neutral-100 px-4 py-2 text-sm font-medium text-neutral-400 cursor-not-allowed"
      >
        Next
      </span>
    </div>

    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-neutral-700">
           Menampilkan <span class="font-medium">{{ paginator.from }}</span> sampai <span class="font-medium">{{ paginator.to }}</span> dari <span class="font-medium">{{ paginator.total }}</span> data
        </p>
      </div>
      <div>
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
          <template v-for="(link, key) in paginator.links" :key="key">
            <Link
              v-if="link.url"
              :href="link.url"
              v-html="link.label"
              class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
              :class="{
                'z-10 bg-brand-primary-50 border-brand-primary-500 text-brand-primary-600': link.active,
                'bg-white border-neutral-300 text-neutral-500 hover:bg-neutral-50': !link.active
              }"
            />
            <span
              v-else
              v-html="link.label"
              class="relative inline-flex items-center px-4 py-2 border text-sm font-medium bg-neutral-100 border-neutral-300 text-neutral-400 cursor-not-allowed"
            />
          </template>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  paginator: {
    type: Object,
    required: true,
  }
})
</script>
