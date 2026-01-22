<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-gray-50 pb-20">
             <!-- Header Area -->
            <div class="bg-indigo-600 px-4 py-8 shadow-sm">
                <div class="mx-auto max-w-7xl">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-white">Your Profile</h1>
                            <p class="text-indigo-200 text-sm">Manage your account settings and preferences.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8 px-4 mt-8">
                <!-- Profile Information -->
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8 rounded-xl border border-gray-100">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <!-- Update Password -->
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8 rounded-xl border border-gray-100">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <!-- Logout Button (Bottom) -->
                <div class="mt-8 flex justify-end md:justify-start">
                    <Link 
                        :href="route('logout')" 
                        method="post" 
                        as="button" 
                        class="w-full md:w-auto bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-red-200 flex items-center justify-center gap-2 transition-all active:scale-95"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                        Sign Out
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
