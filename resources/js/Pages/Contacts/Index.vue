<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    contacts: Array,
});

const searchQuery = ref('');
const viewMode = ref('list'); // 'list' or 'grid'
const showAddModal = ref(false);

const form = useForm({
    name: '',
    position: '',
    phone: '',
});

const countryCode = ref('62');
const localPhone = ref('');

const countryCodes = [
    { code: '62', country: 'Indonesia' },
    { code: '1', country: 'United States' },
    { code: '44', country: 'United Kingdom' },
    { code: '61', country: 'Australia' },
    { code: '65', country: 'Singapore' },
    { code: '60', country: 'Malaysia' },
    { code: '81', country: 'Japan' },
    { code: '86', country: 'China' },
];

const filteredContacts = computed(() => {
    return props.contacts.filter(contact => {
        const search = searchQuery.value.toLowerCase();
        return (
            contact.name.toLowerCase().includes(search) ||
            contact.position.toLowerCase().includes(search) ||
            contact.phone.includes(search)
        );
    });
});

const submit = () => {
    // Format Phone Number
    // Remove leading '0' if present in localPhone
    let cleanedLocal = localPhone.value.replace(/\D/g, ''); // Remove non-digits
    if (cleanedLocal.startsWith('0')) {
        cleanedLocal = cleanedLocal.substring(1);
    }
    form.phone = countryCode.value + cleanedLocal;

    form.post(route('contacts.store'), {
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
            localPhone.value = '';
            countryCode.value = '62'; // Reset to default
        },
    });
};

const deleteContact = (id) => {
    if (confirm('Are you sure you want to delete this contact?')) {
        useForm({}).delete(route('contacts.destroy', id));
    }
};

const openWhatsApp = (phone) => {
    const cleanPhone = phone.replace(/\D/g, '');
    window.open(`https://wa.me/${cleanPhone}`, '_blank');
};
</script>

<template>
    <Head title="Office Directory" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-gray-50 pb-20">
             <!-- Header Area -->
            <!-- Header Area (Glossy Glass Theme) -->
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-blue-800 to-sky-500 bg-[length:300%_300%] animate-gradient-flow px-4 py-10 shadow-lg border-b border-white/10">
                <!-- Dot Pattern -->
                <div class="absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.15)_1px,transparent_1px)] [background-size:20px_20px]"></div>

                <!-- Glossy Effects -->
                <div class="absolute inset-0 bg-white/5 backdrop-blur-[1px]"></div>
                <div class="absolute -top-20 -right-20 h-80 w-80 rounded-full bg-sky-400/20 blur-3xl mix-blend-overlay"></div>
                <div class="absolute -bottom-20 -left-20 h-80 w-80 rounded-full bg-blue-900/40 blur-3xl"></div>

                <div class="relative mx-auto max-w-7xl">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                        <div>
                            <h1 class="text-4xl font-extrabold text-white tracking-tight drop-shadow-md">Employee Directory</h1>
                            <p class="text-blue-100 text-sm mt-1 font-medium opacity-90">Connect with your colleagues.</p>
                        </div>
                        <div v-if="$page.props.auth.user" class="flex gap-3">
                            <button 
                                @click="showAddModal = true"
                                class="bg-white text-blue-900 hover:bg-blue-50 px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md hover:shadow-lg flex items-center gap-2"
                            >
                                + Add Contact
                            </button>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="relative max-w-2xl">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Search name or position..." 
                            class="block w-full rounded-2xl border border-white/20 bg-white/10 backdrop-blur-md py-4 pl-12 pr-12 text-white placeholder-blue-200 shadow-xl focus:border-white/40 focus:bg-white/20 focus:ring-0 focus:outline-none transition-all"
                        >
                         <button class="absolute inset-y-0 right-2 flex items-center px-4 text-white hover:text-blue-100 transition-colors">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="mx-auto max-w-7xl px-4 mt-8">
                <!-- Main Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="px-6 py-5 border-b border-gray-100 flex flex-row items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-1 h-5 bg-orange-500 rounded-sm"></div>
                            <h2 class="text-lg font-bold text-gray-900">All Employees</h2>
                        </div>
                         <!-- View Toggles -->
                        <div class="flex items-center">
                            <div class="bg-gray-100 rounded-lg flex p-1">
                                <button 
                                    @click="viewMode = 'list'"
                                    :class="[
                                        'p-1.5 rounded-md transition-all',
                                        viewMode === 'list' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-400 hover:text-gray-600'
                                    ]"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                                </button>
                                <button 
                                    @click="viewMode = 'grid'"
                                    :class="[
                                        'p-1.5 rounded-md transition-all',
                                        viewMode === 'grid' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-400 hover:text-gray-600'
                                    ]"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-0">
                        <!-- Contact List Table -->
                        <div v-if="viewMode === 'list'" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone / WhatsApp</th>
                                <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="contact in filteredContacts" :key="contact.id" class="hover:bg-gray-50 text-sm">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold mr-3">
                                            {{ contact.name.charAt(0) }}
                                        </div>
                                        <div class="font-medium text-gray-900">{{ contact.name }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-50 text-blue-700">
                                        {{ contact.position }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 flex items-center gap-2">
                                    <span>{{ contact.phone }}</span>
                                    <button @click="openWhatsApp(contact.phone)" class="text-green-500 hover:text-green-600">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button 
                                        v-if="$page.props.auth.user"
                                        @click="deleteContact(contact.id)"
                                        class="text-red-500 hover:text-red-700 font-medium"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredContacts.length === 0">
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500 italic">No contacts found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Grid View -->
                <div v-if="viewMode === 'grid'" class="p-6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 bg-gray-50/50">
                    <div v-for="contact in filteredContacts" :key="contact.id" class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all flex flex-col items-center text-center relative group">
                        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity" v-if="$page.props.auth.user">
                            <button @click="deleteContact(contact.id)" class="text-red-400 hover:text-red-600 bg-red-50 p-1 rounded-full">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                        <div class="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-2xl mb-3 shadow-inner">
                            {{ contact.name.charAt(0) }}
                        </div>
                        <h3 class="font-bold text-gray-900 text-lg truncate w-full">{{ contact.name }}</h3>
                        <p class="text-blue-500 text-xs font-semibold uppercase tracking-wider mb-4">{{ contact.position }}</p>
                        
                        <div class="mt-auto w-full">
                            <button @click="openWhatsApp(contact.phone)" class="w-full flex items-center justify-center gap-2 bg-green-50 text-green-600 py-2 rounded-lg hover:bg-green-100 transition-colors font-semibold text-sm">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                                WhatsApp
                            </button>
                        </div>
                    </div>
                </div>
                    </div> <!-- Closing p-0 -->
                </div> <!-- Closing Main Card -->
            </div>
        </div>

        <!-- Add Contact Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold mb-4">Add New Contact</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Full Name</label>
                        <input 
                            v-model="form.name" 
                            type="text" 
                            class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" 
                            required
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Position</label>
                        <input 
                            v-model="form.position" 
                            type="text" 
                            class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" 
                            required
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Phone Number</label>
                        <div class="flex gap-2">
                             <select v-model="countryCode" class="rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm bg-gray-50">
                                <option v-for="c in countryCodes" :key="c.code" :value="c.code">
                                    +{{ c.code }} ({{ c.country }})
                                </option>
                            </select>
                            <input 
                                v-model="localPhone" 
                                type="tel" 
                                placeholder="8123456789"
                                class="flex-1 rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" 
                                required
                            >
                        </div>
                        <p class="text-[10px] text-gray-500 mt-1">Number will be formatted as: +{{ countryCode }}{{ localPhone ? localPhone.replace(/^0+/, '') : '' }}</p>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button 
                            type="button" 
                            @click="showAddModal = false" 
                            class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="form.processing" 
                            class="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-gradient-flow {
    animation: gradient 15s ease infinite;
}

@keyframes gradient {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}
</style>
