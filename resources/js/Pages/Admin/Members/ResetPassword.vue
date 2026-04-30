<template>
  <AppLayout page-title="Reset Password Anggota">
    <div class="max-w-3xl mx-auto space-y-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Reset Password Anggota</h2>
          <p class="text-sm text-neutral-500">Atur password manual untuk akun anggota yang sudah terhubung.</p>
        </div>
        <SecondaryButton type="button" @click="router.get(`/admin/members/${member.id}`)">Kembali</SecondaryButton>
      </div>

      <CardContainer padding="lg">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <div class="text-sm font-medium text-neutral-500">Nama</div>
            <div class="mt-1 font-semibold text-neutral-900">{{ member.full_name }}</div>
          </div>
          <div>
            <div class="text-sm font-medium text-neutral-500">No KTA</div>
            <div class="mt-1 font-semibold text-neutral-900">{{ member.kta_number || '-' }}</div>
          </div>
          <div>
            <div class="text-sm font-medium text-neutral-500">Unit</div>
            <div class="mt-1 font-semibold text-neutral-900">{{ member.unit?.name || '-' }}</div>
          </div>
          <div>
            <div class="text-sm font-medium text-neutral-500">Email Akun</div>
            <div class="mt-1 font-semibold text-neutral-900 break-all">{{ member.user?.email || '-' }}</div>
          </div>
        </div>

        <div v-if="member.user" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
          Login Google SSO tetap aktif setelah password manual direset.
        </div>
        <div v-else class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          Password belum bisa direset karena anggota belum memiliki akun user yang terhubung.
        </div>
      </CardContainer>

      <CardContainer v-if="member.user" padding="lg">
        <form class="space-y-5" @submit.prevent="submit">
          <InputField
            v-model="form.password"
            type="password"
            label="Password Baru"
            required
            :error="form.errors.password"
            helper="Minimal 8 karakter."
          />
          <InputField
            v-model="form.password_confirmation"
            type="password"
            label="Konfirmasi Password Baru"
            required
            :error="form.errors.password_confirmation"
          />

          <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
            <SecondaryButton type="button" @click="router.get(`/admin/members/${member.id}`)">Batal</SecondaryButton>
            <PrimaryButton type="submit" :loading="form.processing" :disabled="form.processing">
              Simpan Password Baru
            </PrimaryButton>
          </div>
        </form>
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';

const props = defineProps({
  member: {
    type: Object,
    required: true,
  },
});

const form = useForm({
  password: '',
  password_confirmation: '',
});

function submit() {
  form.post(`/admin/members/${props.member.id}/reset-password`, {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
}
</script>
