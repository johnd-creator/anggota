<template>
  <AppLayout :page-title="title">
    <CardContainer padding="lg" shadow="sm">
      <form @submit.prevent="submit" class="space-y-4">
        <InputField label="Nama Jabatan" v-model="form.name" required />
        <InputField label="Kode/Alias" v-model="form.code" />
        <InputField label="Deskripsi" v-model="form.description" />
        <div class="flex justify-end gap-2">
          <SecondaryButton type="button" @click="router.visit('/admin/union-positions')">Batal</SecondaryButton>
          <PrimaryButton type="submit">Simpan</PrimaryButton>
        </div>
      </form>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import { router, usePage } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';

const page = usePage();
const item = page.props.item || null;
const form = reactive({ name: item?.name || '', code: item?.code || '', description: item?.description || '' });
const title = computed(()=> item ? 'Edit Jabatan Serikat' : 'Tambah Jabatan Serikat');

function submit(){
  if(item){ router.put(`/admin/union-positions/${item.id}`, form); }
  else { router.post('/admin/union-positions', form); }
}
</script>
