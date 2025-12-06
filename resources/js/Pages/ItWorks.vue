<template>
  <div class="min-h-screen bg-gradient-to-br from-brand-primary-50 via-white to-brand-secondary-50 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-2xl">
      <!-- Header -->
      <div class="text-center mb-8 animate-fade-in">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-brand-primary-100 mb-4">
          <svg class="w-10 h-10 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h1 class="text-3xl font-bold text-neutral-900 mb-2">
          Welcome to SIM-SP!
        </h1>
        <p class="text-lg text-neutral-600">
          Your account is active
        </p>
      </div>

      <!-- Main Card -->
      <CardContainer shadow="xl" padding="lg" class="animate-slide-up">
        <div class="space-y-6">
          <!-- Success Alert -->
          <AlertBanner
            v-if="$page.props.onboarding && $page.props.onboarding.status==='pending'"
            type="info"
            title="Pengajuan Onboarding Pending"
            :message="`Permintaan Anda sedang ditinjau. Status: pending. Catatan: ${$page.props.onboarding.notes || '-'}`"
          />
          <AlertBanner
            v-else-if="$page.props.onboarding && $page.props.onboarding.status==='rejected'"
            type="error"
            title="Pengajuan Ditolak"
            :message="`Pengajuan ditolak. Catatan: ${$page.props.onboarding.notes || '-'}`"
          />
          <AlertBanner
            v-else
            type="success"
            title="Login Successful"
            message="You are logged in as a Reguler user."
          />

          <!-- Info Section -->
          <div class="bg-neutral-50 rounded-lg p-6 border border-neutral-200">
            <h2 class="text-lg font-semibold text-neutral-900 mb-3">
              Limited Access
            </h2>
            <p class="text-neutral-700 mb-4">
              Your account currently has limited access to the system. To unlock full features and capabilities, please contact your Unit Administrator.
            </p>
            
            <!-- Illustration Placeholder -->
            <div class="bg-white rounded-lg p-8 border-2 border-dashed border-neutral-300 text-center my-6">
              <svg class="w-24 h-24 mx-auto text-neutral-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              <p class="text-sm text-neutral-500">Access Restricted</p>
            </div>
          </div>

          <!-- What you can do -->
          <div>
            <h3 class="text-md font-semibold text-neutral-900 mb-3">
              What you can do:
            </h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-status-success mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-neutral-700">View this welcome page</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-status-success mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-neutral-700">Update your profile information</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                </svg>
                <span class="text-neutral-500 line-through">Access dashboard and reports</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                </svg>
                <span class="text-neutral-500 line-through">Manage union member data</span>
              </li>
            </ul>
          </div>

          <!-- CTA Section -->
          <div class="bg-brand-primary-50 rounded-lg p-6 border border-brand-primary-200">
            <h3 class="text-md font-semibold text-brand-primary-900 mb-2">
              Need Full Access?
            </h3>
            <p class="text-sm text-brand-primary-800 mb-4">
              Contact your Unit Administrator to request an upgrade to your account permissions.
            </p>
            <div class="mb-3">
              <a href="/admin/onboarding" class="text-brand-primary-600 text-sm">Lihat status onboarding</a>
            </div>
            <a
              href="mailto:admin@example.com"
              class="inline-flex items-center px-4 py-2 bg-brand-primary-600 text-white rounded-lg hover:bg-brand-primary-700 transition-colors duration-200 text-sm font-medium"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              Contact Admin Unit
            </a>
          </div>

          <div class="text-center">
            <button
              type="button"
              @click="handleLogout"
              class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-brand-primary-600 rounded-lg hover:bg-brand-primary-700 transition-colors"
            >
              Logout
            </button>
          </div>
        </div>
      </CardContainer>

      <!-- Footer -->
      <div class="mt-8 text-center">
        <p class="text-sm text-neutral-500">
          Questions? Read our <a href="#" class="text-brand-primary-600 hover:text-brand-primary-700">FAQ</a> or <a href="#" class="text-brand-primary-600 hover:text-brand-primary-700">Help Center</a>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import CardContainer from '@/Components/UI/CardContainer.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';

const handleLogout = () => {
  router.post('/logout');
};
</script>
