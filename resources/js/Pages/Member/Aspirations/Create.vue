<template>
  <AppLayout page-title="Buat Aspirasi">
    <div class="max-w-2xl mx-auto">
      <CardContainer padding="lg">
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-neutral-900">Buat Aspirasi Baru</h1>
          <p class="text-neutral-600 text-sm mt-1">Sampaikan saran atau masukan untuk kemajuan unit</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
          <!-- Category -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Kategori <span class="text-red-500">*</span></label>
            <select
              v-model="form.category_id"
              class="w-full border border-neutral-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-500': form.errors.category_id }"
            >
              <option value="">Pilih kategori</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
            <p v-if="form.errors.category_id" class="text-red-500 text-xs mt-1">{{ form.errors.category_id }}</p>
          </div>

          <!-- Title -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Judul <span class="text-red-500">*</span></label>
            <input
              v-model="form.title"
              type="text"
              maxlength="255"
              placeholder="Ringkasan singkat aspirasi Anda"
              class="w-full border border-neutral-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-500': form.errors.title }"
            />
            <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
          </div>

          <!-- Body -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Isi Aspirasi <span class="text-red-500">*</span></label>
            <textarea
              v-model="form.body"
              rows="6"
              placeholder="Jelaskan aspirasi Anda secara detail..."
              class="w-full border border-neutral-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
              :class="{ 'border-red-500': form.errors.body }"
            ></textarea>
            <p v-if="form.errors.body" class="text-red-500 text-xs mt-1">{{ form.errors.body }}</p>
            <p class="text-xs text-neutral-500 mt-1">Minimal 10 karakter</p>
          </div>

          <!-- Tags -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Tag (Opsional)</label>
            <div class="flex flex-wrap gap-2 mb-2">
              <span
                v-for="(tag, i) in form.tags"
                :key="i"
                class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"
              >
                #{{ tag }}
                <button type="button" @click="removeTag(i)" class="hover:text-blue-600">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </span>
            </div>
            <div class="flex gap-2">
              <input
                v-model="newTag"
                type="text"
                maxlength="50"
                placeholder="Tambah tag..."
                @keydown.enter.prevent="addTag"
                class="flex-1 border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
              <button type="button" @click="addTag" class="px-4 py-2 bg-neutral-100 text-neutral-700 rounded-lg hover:bg-neutral-200 transition text-sm">
                Tambah
              </button>
            </div>
            <div v-if="existingTags.length > 0" class="mt-2">
              <p class="text-xs text-neutral-500 mb-1">Tag populer:</p>
              <div class="flex flex-wrap gap-1">
                <button
                  v-for="tag in existingTags.slice(0, 10)"
                  :key="tag"
                  type="button"
                  @click="addExistingTag(tag)"
                  :disabled="form.tags.includes(tag)"
                  :class="[
                    'px-2 py-1 text-xs rounded-full border transition',
                    form.tags.includes(tag) ? 'bg-blue-100 border-blue-300 text-blue-600' : 'bg-white border-neutral-200 text-neutral-600 hover:border-blue-300'
                  ]"
                >
                  #{{ tag }}
                </button>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-3 pt-4 border-t border-neutral-200">
            <button
              type="submit"
              :disabled="form.processing"
              class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="form.processing">Mengirim...</span>
              <span v-else>Kirim Aspirasi</span>
            </button>
            <Link href="/member/aspirations" class="px-6 py-2.5 bg-neutral-100 text-neutral-700 rounded-lg hover:bg-neutral-200 transition text-sm font-medium">
              Batal
            </Link>
          </div>
        </form>
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';

const props = defineProps({
  categories: Array,
  existingTags: Array,
});

const form = useForm({
  category_id: '',
  title: '',
  body: '',
  tags: [],
});

const newTag = ref('');

function addTag() {
  const tag = newTag.value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
  if (tag && !form.tags.includes(tag) && form.tags.length < 5) {
    form.tags.push(tag);
    newTag.value = '';
  }
}

function addExistingTag(tag) {
  if (!form.tags.includes(tag) && form.tags.length < 5) {
    form.tags.push(tag);
  }
}

function removeTag(index) {
  form.tags.splice(index, 1);
}

function submit() {
  form.post('/member/aspirations');
}
</script>
