<template>
  <AppLayout :page-title="title">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <div class="lg:col-span-1">
        <CardContainer padding="lg" shadow="sm">
          <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-neutral-900">Daftar Isi</div>
            <Badge v-if="updated" variant="brand">Updated</Badge>
          </div>
          <ul class="mt-3 text-sm space-y-2">
            <li v-for="h in toc" :key="h.line"><a href="#" @click.prevent="scrollTo(h.line)" class="text-brand-primary-700">{{ h.text }}</a></li>
          </ul>
        </CardContainer>
        <CardContainer padding="lg" shadow="sm" class="mt-4">
          <div class="text-sm font-semibold text-neutral-900">Cari</div>
          <InputField v-model="q" placeholder="Cari..." aria-label="Cari" />
        </CardContainer>
      </div>
      <div class="lg:col-span-3">
        <CardContainer padding="lg" shadow="sm">
          <div class="text-sm whitespace-pre-wrap" ref="docEl">{{ filtered }}</div>
        </CardContainer>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import Badge from '@/Components/UI/Badge.vue';
import { ref, computed, onMounted } from 'vue';
const props = defineProps({ title: String, content: String, updated_at: String });
const title = props.title || 'Dokumen';
const content = props.content || '';
const updated = !!props.updated_at;
const lines = content.split('\n');
const toc = lines.map((l, idx) => ({ text: l.replace(/^#\s*/,'').trim(), line: idx })).filter(x => lines[x.line].startsWith('#'));
const docEl = ref(null);
function scrollTo(line){ try { const nodes = docEl.value?.childNodes || []; if (nodes.length) window.scrollTo({ top: docEl.value.offsetTop + (line*16), behavior: 'smooth' }); } catch(e) {} }
const q = ref('');
const filtered = computed(() => { if (!q.value) return content; const re = new RegExp(q.value, 'ig'); return content.split('\n').filter(l => l.match(re) || l.startsWith('#')).join('\n'); });
</script>
