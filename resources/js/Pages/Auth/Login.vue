<template>
  <div class="min-h-screen flex bg-neutral-50">
    <!-- Left Side - Visual -->
    <div class="hidden lg:flex lg:w-1/2 relative items-center justify-center bg-red-900 overflow-hidden">
      <!-- Abstract Background -->
      <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-br from-red-900 to-red-700 opacity-90"></div>
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-red-500 rounded-full blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-rose-600 rounded-full blur-3xl opacity-20 animate-pulse" style="animation-delay: 2s"></div>

        <!-- Grid Pattern -->
        <div class="absolute inset-0 bg-[url('/img/grid.svg')] opacity-10"></div>
      </div>

      <!-- Content -->
      <div class="relative z-10 max-w-xl px-12 text-center">
        <div class="mb-8 inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-white/10 backdrop-blur-lg border border-white/20 shadow-2xl">
          <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
        <h2 class="text-4xl font-bold text-white mb-6 tracking-tight">
          Satu Platform untuk<br/>
          <span class="text-neutral-100">Organisasi yang Lebih Cerdas</span>
        </h2>
        <p class="text-lg text-neutral-300 leading-relaxed">
          Integrasi administrasi anggota serikat dan pengelolaan bendahara secara efisien. Dapatkan visibilitas penuh atas seluruh entitas serikat Anda dalam satu dashboard terpadu.
        </p>

        <!-- Stats Cards -->
        <div class="mt-12 grid grid-cols-2 gap-4">
          <div class="bg-white/5 backdrop-blur-md rounded-xl p-4 border border-white/10 text-left">
            <p class="text-sm text-neutral-400">Anggota Terdata</p>
            <p class="text-2xl font-bold text-white mt-1">1.248</p>
          </div>
          <div class="bg-white/5 backdrop-blur-md rounded-xl p-4 border border-white/10 text-left">
            <p class="text-sm text-neutral-400">Aktivitas Harian</p>
            <p class="text-2xl font-bold text-white mt-1">8.204</p>
          </div>
        </div>

        <!-- Tagline -->
<div class="mt-12 text-white text-center">
  <div class="flex items-center justify-center gap-2">
    <div class="text-3xl font-extrabold">
      #Bersama #Kuat #Sejahtera
      <br/>
    </div>
  </div>
  <div class="mt-3 text-xs tracking-wide text-neutral-100">
    Serikat Pekerja PT PLN Indonesia Power Services
  </div>
</div>

      </div>
    </div>

    <!-- Right Side - Login Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-12 relative">
      <div class="w-full max-w-md space-y-8">
        <!-- Mobile Logo -->
        <div class="lg:hidden text-center mb-8">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-brand-primary-600 text-white mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-neutral-900">SIM-SP</h1>
        </div>

        <!-- Desktop Logo -->
        <div class="hidden lg:flex text-center justify-center">
          <img src="/img/logo.png" alt="Logo" class="h-28 w-auto" />
        </div>

        <!-- Header -->
        <div class="text-center lg:text-left">
          <h2 class="text-3xl font-bold text-neutral-900 tracking-tight">Selamat Datang Kembali</h2>
          <p class="mt-2 text-neutral-600">Masukkan kredensial Anda untuk mengakses SIM-SPPIPS.</p>
        </div>

        <!-- Error Alert -->
        <AlertBanner
          v-if="$page.props.errors?.email || $page.props.errors?.password"
          type="error"
          :message="$page.props.errors.email || $page.props.errors.password || 'Login gagal. Silakan periksa ulang kredensial Anda.'"
          :dismissible="false"
          class="animate-fade-in-down"
        />

        <!-- Login Form -->
        <form @submit.prevent="handleLogin" class="space-y-6">
          <div class="space-y-4">
            <InputField
              v-model="form.email"
              type="email"
              label="Alamat Email"
              placeholder="nama@organisasi.com"
              required
              :error="form.errors?.email"
            />

            <div class="space-y-1">
              <InputField
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                label="Kata Sandi"
                placeholder="••••••••"
                required
                :error="form.errors?.password"
              />
              <div class="flex justify-end">
                 <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="text-xs text-neutral-500 hover:text-brand-primary-600 font-medium transition-colors"
                >
                  {{ showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi' }}
                </button>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <label class="flex items-center cursor-pointer group">
              <div class="relative flex items-center">
                <input
                  type="checkbox"
                  v-model="form.remember"
                  class="peer h-4 w-4 rounded border-neutral-300 text-brand-primary-600 focus:ring-brand-primary-600 transition-colors"
                >
              </div>
              <span class="ml-2 text-sm text-neutral-600 group-hover:text-neutral-900 transition-colors">Ingat saya</span>
            </label>

            <a href="#" class="text-sm font-semibold text-brand-primary-600 hover:text-brand-primary-700 transition-colors">
              Lupa kata sandi?
            </a>
          </div>

          <PrimaryButton
            type="submit"
            :loading="loading"
            class="w-full justify-center py-3 text-base"
          >
            Masuk
          </PrimaryButton>
        </form>

        <!-- Divider -->
        <div class="relative">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-neutral-200"></div>
          </div>
          <div class="relative flex justify-center text-sm">
            <span class="px-4 bg-neutral-50 text-neutral-500 font-medium">Atau lanjutkan dengan</span>
          </div>
        </div>

        <!-- Social Login -->
        <div>
          <a href="/auth/google" class="flex items-center justify-center gap-3 w-full px-4 py-2.5 border border-neutral-200 rounded-xl bg-white hover:bg-neutral-50 hover:border-neutral-300 transition-all duration-200 shadow-sm group">
            <svg class="h-5 w-5 group-hover:scale-110 transition-transform duration-200" viewBox="0 0 24 24">
              <path fill="#EA4335" d="M12 10.2v3.8h5.4c-.2 1.1-.9 2.4-2.1 3.3l3.4 2.6c2-1.8 3.3-4.4 3.3-7.7 0-.7-.1-1.4-.2-2H12z"/>
              <path fill="#34A853" d="M12 22c3 0 5.5-1 7.4-2.7l-3.4-2.6c-.9.6-2.1 1-4 1-3 0-5.6-2-6.5-4.7H2.1v3c1.9 3.8 5.9 6 9.9 6z"/>
              <path fill="#4A90E2" d="M5.5 13c-.2-.6-.4-1.2-.4-2s.2-1.4.4-2V6h-3C1.2 8 1 9 1 11s.2 3 .5 4l4-2z"/>
              <path fill="#FBBC05" d="M12 5c1.6 0 3 .6 4.1 1.7l3.1-3.1C17.5 1.7 15 1 12 1 8 1 4.9 3.3 3.5 7l4 2C8.4 7.1 9.9 5 12 5z"/>
            </svg>
            <span class="text-sm font-semibold text-neutral-700">Masuk dengan Google</span>
          </a>
        </div>

        <!-- Footer -->
        <p class="text-center text-xs text-neutral-500">
          Dengan masuk, Anda menyetujui
          <a href="#" class="text-brand-primary-600 hover:text-brand-primary-700 font-medium hover:underline">Ketentuan Layanan</a>
          dan
          <a href="#" class="text-brand-primary-600 hover:text-brand-primary-700 font-medium hover:underline">Kebijakan Privasi</a>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import InputField from '@/Components/UI/InputField.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';


const showPassword = ref(false);
const loading = ref(false);

const form = reactive({
  email: '',
  password: '',
  remember: false,
  errors: {}, // Add errors object to form state
});

const handleLogin = () => {
  loading.value = true;
  form.errors = {}; // Clear previous errors

  router.post('/login', form, {
    onError: (errors) => {
      form.errors = errors;
      loading.value = false;
    },
    onFinish: () => {
      loading.value = false;
    },
  });
};
</script>

<style scoped>
.animate-fade-in-down {
  animation: fadeInDown 0.5s ease-out;
}

@keyframes fadeInDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
