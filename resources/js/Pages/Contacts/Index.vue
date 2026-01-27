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
const showEditModal = ref(false); // If needed later
const showDetailModal = ref(false);
const selectedContact = ref(null);
const isDragging = ref(false);
const isEditing = ref(false);

const form = useForm({
    _method: 'POST', // Default, will override for PUT
    name: '',
    // department removed
    position: '',
    employee_id: '',
    email: '',
    phone: '',
    bio: '',
    certifications: '',
    photo: null,
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

    if (isEditing.value && selectedContact.value) {
        form.post(route('contacts.update', selectedContact.value.id), {
            _method: 'put',
            onSuccess: () => {
                showAddModal.value = false;
                form.reset();
                localPhone.value = '';
                countryCode.value = '62';
                isEditing.value = false;
                selectedContact.value = null;
            },
        });
    } else {
        form.post(route('contacts.store'), {
            onSuccess: () => {
                showAddModal.value = false;
                form.reset();
                localPhone.value = '';
                countryCode.value = '62';
            },
        });
    }
};

const openAddModal = () => {
    isEditing.value = false;
    selectedContact.value = null;
    form.reset();
    form.clearErrors();
    form._method = 'POST';
    localPhone.value = '';
    countryCode.value = '62';
    showAddModal.value = true;
};

const openEditModal = (contact) => {
    isEditing.value = true;
    selectedContact.value = contact;
    form.clearErrors();
    form._method = 'PUT';
    
    // Populate Form
    form.name = contact.name;
    // department removed
    form.position = contact.position;
    form.employee_id = contact.employee_id;
    form.email = contact.email;
    form.bio = contact.bio;
    form.certifications = contact.certifications;
    form.photo = null; // Don't pre-fill file input
    
    // Parse Phone
    let phone = contact.phone || '';
    if (phone.startsWith('+')) phone = phone.substring(1);
    
    const matched = countryCodes.find(c => phone.startsWith(c.code));
    if (matched) {
        countryCode.value = matched.code;
        localPhone.value = phone.substring(matched.code.length);
    } else {
        countryCode.value = '62';
        localPhone.value = phone;
    }

    showDetailModal.value = false;
    showAddModal.value = true;
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

const openDetail = (contact) => {
    selectedContact.value = contact;
    showDetailModal.value = true;
};

const handlePhotoInput = (e) => {
    if (e.target.files.length > 0) {
        form.photo = e.target.files[0];
    }
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
                                @click="openAddModal"
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
                                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold mr-3 overflow-hidden">
                                            <img v-if="contact.photo_path" :src="'/storage/' + contact.photo_path" class="w-full h-full object-cover">
                                            <span v-else>{{ contact.name.charAt(0) }}</span>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ contact.name }}</div>
                                            <div v-if="contact.employee_id" class="text-xs text-gray-500 font-mono mt-0.5">ID: {{ contact.employee_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-50 text-blue-700">
                                        {{ contact.position }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500 flex items-center gap-2">
                                    <span>{{ contact.phone }}</span>
                                    <button @click="openWhatsApp(contact.phone)" class="text-green-500 hover:text-green-600" title="WhatsApp">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-3">
                                        <button @click="openDetail(contact)" class="text-blue-500 hover:text-blue-700 transition-colors" title="View Profile">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </button>
                                        <button 
                                            v-if="$page.props.auth.user"
                                            @click="deleteContact(contact.id)"
                                            class="text-red-400 hover:text-red-600 transition-colors"
                                            title="Delete"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
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
                        <div class="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-2xl mb-3 shadow-inner overflow-hidden cursor-pointer" @click="openDetail(contact)">
                            <img v-if="contact.photo_path" :src="'/storage/' + contact.photo_path" class="w-full h-full object-cover select-none">
                            <span v-else>{{ contact.name.charAt(0) }}</span>
                        </div>
                        <h3 class="font-bold text-gray-900 text-lg truncate w-full">{{ contact.name }}</h3>
                        <p v-if="contact.employee_id" class="text-xs text-gray-400 font-mono mb-1">{{ contact.employee_id }}</p>
                        <p class="text-blue-500 text-xs font-semibold uppercase tracking-wider mb-4">{{ contact.position }}</p>
                        
                        <div class="mt-auto w-full grid grid-cols-2 gap-2">
                             <button @click="openDetail(contact)" class="flex items-center justify-center gap-1 bg-blue-50 text-blue-600 py-2 rounded-lg hover:bg-blue-100 transition-colors font-semibold text-xs">
                                Profile
                            </button>
                            <button @click="openWhatsApp(contact.phone)" class="flex items-center justify-center gap-1 bg-green-50 text-green-600 py-2 rounded-lg hover:bg-green-100 transition-colors font-semibold text-xs">
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

        <!-- Add/Edit Contact Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold mb-4">{{ isEditing ? 'Edit Contact' : 'Add New Contact' }}</h3>
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
                        <label class="block text-xs font-bold text-gray-500 uppercase">Profile Photo</label>
                        <div 
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="(e) => { isDragging = false; if(e.dataTransfer.files.length > 0) form.photo = e.dataTransfer.files[0]; }"
                            :class="[
                                'border-2 border-dashed rounded-xl p-4 text-center cursor-pointer transition-colors',
                                isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:bg-gray-50'
                            ]"
                        >
                            <input type="file" @change="handlePhotoInput" class="hidden" id="photoInput" accept="image/*">
                            <label for="photoInput" class="cursor-pointer block">
                                <div v-if="!form.photo" class="flex flex-col items-center">
                                         <svg class="w-6 h-6 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <span class="text-gray-400 text-[10px]">Drop image or browse</span>
                                </div>
                                <div v-else class="flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    <span class="text-blue-600 font-bold text-xs block truncate max-w-[120px]">{{ form.photo.name }}</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Employee ID</label>
                        <input v-model="form.employee_id" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <!-- Department Field Removed -->
                     <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Position</label>
                        <input v-model="form.position" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Email</label>
                        <input v-model="form.email" type="email" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
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
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Diklat & Certifications</label>
                        <textarea v-model="form.certifications" rows="3" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter certifications, one per line"></textarea>
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
                            {{ isEditing ? 'Update Contact' : 'Save Contact' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Detail Modal -->
         <!-- Detail Modal (Redesigned) -->
         <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto relative animate-bounce-in">
                
                <!-- Close Button -->
                <button @click="showDetailModal = false" class="absolute top-6 right-6 text-gray-400 hover:text-gray-600 transition-colors z-10">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>

                <div class="p-8">
                    <!-- Header -->
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">Profil Lengkap</h2>
                        
                        <div class="flex flex-col items-center">
                            <div class="h-28 w-28 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-4xl font-bold mb-4 shadow-sm overflow-hidden border-4 border-white ring-1 ring-gray-100">
                                <img v-if="selectedContact?.photo_path" :src="'/storage/' + selectedContact.photo_path" class="w-full h-full object-cover">
                                <span v-else>{{ selectedContact?.name.charAt(0) }}</span>
                            </div>
                            
                            <h3 class="text-2xl font-bold text-gray-900 mb-1">{{ selectedContact?.name }}</h3>
                            <p class="text-sm font-medium text-gray-500 mb-4 uppercase tracking-wide">{{ selectedContact?.position }}</p>
                            
                            <!-- Mock Edit Button (Functional placeholder) -->
                            <button @click="openEditModal(selectedContact)" class="flex items-center gap-2 px-4 py-2 rounded-full border border-blue-100 bg-blue-50 text-blue-600 text-sm font-semibold hover:bg-blue-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                Edit Profile
                            </button>
                        </div>
                    </div>

                    <!-- Content Grid -->
                     <div class="grid md:grid-cols-2 gap-12 border-t border-gray-100 pt-8">
                        
                        <!-- Left Column: Contact Info -->
                        <div>
                             <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-6">Contact Information</h4>
                             
                             <div class="space-y-6">
                                <div v-if="selectedContact?.employee_id" class="flex items-start gap-4">
                                     <div class="w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center text-purple-600 flex-shrink-0">
                                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" /></svg>
                                     </div>
                                     <div>
                                         <p class="text-xs font-bold text-gray-400 mb-0.5">Employee ID</p>
                                         <p class="text-sm font-semibold text-gray-900 font-mono">{{ selectedContact?.employee_id }}</p>
                                     </div>
                                </div>

                                <div class="flex items-start gap-4">
                                     <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 flex-shrink-0">
                                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                     </div>
                                     <div>
                                         <p class="text-xs font-bold text-gray-400 mb-0.5">Email Address</p>
                                         <a :href="'mailto:' + selectedContact?.email" class="text-sm font-semibold text-gray-900 hover:text-blue-600 transition-colors">{{ selectedContact?.email || 'N/A' }}</a>
                                     </div>
                                </div>

                                <div class="flex items-start gap-4">
                                     <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center text-green-600 flex-shrink-0">
                                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                     </div>
                                     <div>
                                         <p class="text-xs font-bold text-gray-400 mb-0.5">Phone / Whatsapp</p>
                                         <div class="flex items-center gap-2">
                                             <span class="text-sm font-semibold text-gray-900">{{ selectedContact?.phone }}</span>
                                             <button @click="openWhatsApp(selectedContact?.phone)" class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold hover:bg-green-200 transition-colors">Chat</button>
                                         </div>
                                     </div>
                                </div>
                             </div>
                        </div>

                        <!-- Right Column: Certifications -->
                        <div>
                             <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-6">Diklat & Certifications</h4>
                             
                             <div class="space-y-4">
                                <template v-if="selectedContact?.certifications">
                                     <div v-for="(cert, index) in selectedContact.certifications.split('\n')" :key="index" class="flex items-start gap-4">
                                         <div class="w-10 h-10 rounded-full bg-orange-50 flex items-center justify-center text-orange-600 flex-shrink-0">
                                             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /><path d="M9 12l2 2 4-4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                         </div>
                                         <div>
                                             <p class="text-sm font-semibold text-gray-900 leading-tight">{{ cert }}</p>
                                         </div>
                                     </div>
                                </template>
                                <div v-else class="text-sm text-gray-400 italic">No certifications listed.</div>
                             </div>
                        </div>

                     </div>

                     <!-- Footer Actions -->
                     <div class="mt-12 flex justify-end pt-6 border-t border-gray-100">
                         <button @click="showDetailModal = false" class="px-6 py-2 rounded-lg border border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-colors">
                             Close
                         </button>
                     </div>

                </div>
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
