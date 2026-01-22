<script setup>
import { ref, watch, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const showRestrictionModal = ref(false);
const restrictionMessage = ref('');
const page = usePage();

const checkFlash = () => {
    if (page.props.flash?.restriction) {
        restrictionMessage.value = page.props.flash.restriction;
        showRestrictionModal.value = true;
        // Clear flash manually to prevent showing again on navigation if persisted? 
        // Inertia usually handles flash clearing.
    }
};

watch(() => page.props.flash, (newFlash) => {
    if (newFlash?.restriction) {
        restrictionMessage.value = newFlash.restriction;
        showRestrictionModal.value = true;
    }
}, { deep: true });

onMounted(() => {
    checkFlash();
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 font-sans antialiased text-gray-900">
        <!-- Desktop Sidebar/Nav (Hidden on Mobile) -->
        <nav class="hidden sm:block border-b border-gray-100 bg-white sticky top-0 z-50">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between items-center">
                    <div class="flex items-center gap-8">
                        <Link :href="route('dashboard')" class="text-xl font-black tracking-tight text-blue-800">
                             OFFICEHUB_
                        </Link>
                        <div class="flex gap-4">
                            <Link :href="route('dashboard')" :class="[route().current('dashboard') ? 'text-blue-800 font-bold' : 'text-gray-500 hover:text-gray-700']" class="text-sm">
                                Dashboard
                            </Link>
                            <Link :href="route('contacts.index')" :class="[route().current('contacts.index') ? 'text-blue-800 font-bold' : 'text-gray-500 hover:text-gray-700']" class="text-sm">
                                Team
                            </Link>
                            <Link :href="route('files.index')" :class="[route().current('files.index') ? 'text-blue-800 font-bold' : 'text-gray-500 hover:text-gray-700']" class="text-sm">
                                Files
                            </Link>
                            <Link v-if="$page.props.auth.user && $page.props.auth.user.role === 'superadmin'" :href="route('users.index')" :class="[route().current('users.index') ? 'text-blue-800 font-bold' : 'text-gray-500 hover:text-gray-700']" class="text-sm">
                                Users
                            </Link>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="text-right" v-if="$page.props.auth.user">
                            <p class="text-xs font-bold text-gray-900">{{ $page.props.auth.user.name }}</p>
                            <Link :href="route('logout')" method="post" as="button" class="text-[10px] text-gray-400 hover:text-red-500 uppercase tracking-widest font-bold">
                                Sign Out
                            </Link>
                        </div>
                        <div v-if="$page.props.auth.user" class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-800 font-bold">
                            {{ $page.props.auth.user.name.charAt(0) }}
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main>
            <slot />
        </main>
        
        <!-- Restriction Modal -->
        <div v-if="showRestrictionModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 transform transition-all scale-100 animate-bounce-in text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 mb-4">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <h3 class="text-lg font-black text-gray-900 mb-2">Access Restricted</h3>
                <p class="text-sm text-gray-500 mb-6">{{ restrictionMessage }}</p>
                <button 
                    @click="showRestrictionModal = false"
                    class="w-full inline-flex justify-center rounded-xl bg-gray-900 px-3 py-3 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 transition"
                >
                    Dismiss
                </button>
            </div>
        </div>

        <!-- Optimized Mobile Bottom Tab Bar -->
        <nav class="sm:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 pb-safe pt-1 z-50 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
            <div class="grid h-14" :class="$page.props.auth.user && $page.props.auth.user.role === 'superadmin' ? 'grid-cols-5' : 'grid-cols-4'">
                
                <!-- Home -->
                <Link :href="route('dashboard')" class="flex flex-col items-center justify-center gap-0.5 active:scale-95 transition-transform">
                    <div :class="[route().current('dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-400', 'p-1.5 rounded-xl transition-colors']">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                    </div>
                    <span class="text-[9px] font-bold" :class="route().current('dashboard') ? 'text-blue-700' : 'text-gray-400'">Home</span>
                </Link>

                <!-- Directory -->
                <Link :href="route('contacts.index')" class="flex flex-col items-center justify-center gap-0.5 active:scale-95 transition-transform">
                    <div :class="[route().current('contacts.index') ? 'bg-blue-50 text-blue-700' : 'text-gray-400', 'p-1.5 rounded-xl transition-colors']">
                         <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    </div>
                     <span class="text-[9px] font-bold" :class="route().current('contacts.index') ? 'text-blue-700' : 'text-gray-400'">Team</span>
                </Link>

                <!-- Files -->
                <Link :href="route('files.index')" class="flex flex-col items-center justify-center gap-0.5 active:scale-95 transition-transform">
                    <div :class="[route().current('files.index') ? 'bg-blue-50 text-blue-700' : 'text-gray-400', 'p-1.5 rounded-xl transition-colors']">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" /></svg>
                    </div>
                     <span class="text-[9px] font-bold" :class="route().current('files.index') ? 'text-blue-700' : 'text-gray-400'">Files</span>
                </Link>

                <!-- Users (Admin) -->
                <Link v-if="$page.props.auth.user && $page.props.auth.user.role === 'superadmin'" :href="route('users.index')" class="flex flex-col items-center justify-center gap-0.5 active:scale-95 transition-transform">
                    <div :class="[route().current('users.index') ? 'bg-blue-50 text-blue-700' : 'text-gray-400', 'p-1.5 rounded-xl transition-colors']">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </div>
                     <span class="text-[9px] font-bold" :class="route().current('users.index') ? 'text-blue-700' : 'text-gray-400'">Admin</span>
                </Link>
                
                <!-- Profile -->
                <Link v-if="$page.props.auth.user" :href="route('profile.edit')" class="flex flex-col items-center justify-center gap-0.5 active:scale-95 transition-transform">
                     <div :class="[route().current('profile.edit') ? 'ring-2 ring-blue-700 ring-offset-2' : '', 'h-7 w-7 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden transition-all']">
                        <span class="text-xs font-bold text-gray-600">{{ $page.props.auth.user.name.charAt(0) }}</span>
                     </div>
                     <span class="text-[9px] font-bold" :class="route().current('profile.edit') ? 'text-blue-700' : 'text-gray-400'">Profile</span>
                </Link>

            </div>
        </nav>
    </div>
</template>
<style scoped>
.pb-safe {
    padding-bottom: env(safe-area-inset-bottom);
}
@keyframes fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes bounce-in {
    0% { transform: scale(0.9); opacity: 0; }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); opacity: 1; }
}
.animate-fade-in {
    animation: fade-in 0.2s ease-out;
}
.animate-bounce-in {
    animation: bounce-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
</style>
