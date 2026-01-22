<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    files: Array,
    recentFiles: Array,
    totalStorageSize: Number,
});

const searchQuery = ref('');
const selectedCategory = ref('All');
const viewMode = ref('list'); 
const showUploadModal = ref(false);
const showDownloadModal = ref(false);
const isDragging = ref(false);

const fileForm = useForm({
    display_name: '',
    category: 'General',
    file: null,
    description: '',
    is_secure: false,
    password: '',
});

const downloadPassword = ref('');
const selectedFileToDownload = ref(null);

const categories = computed(() => {
    const cats = new Set(['All']);
    props.files.forEach(f => cats.add(f.category));
    return Array.from(cats);
});

const filteredFiles = computed(() => {
    return props.files.filter(file => {
        const matchesSearch = file.display_name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                             (file.description && file.description.toLowerCase().includes(searchQuery.value.toLowerCase()));
        const matchesCategory = selectedCategory.value === 'All' || file.category === selectedCategory.value;
        return matchesSearch && matchesCategory;
    });
});

const formatSize = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

const getFileIcon = (filename) => {
    const ext = filename.split('.').pop().toLowerCase();
    if (['pdf'].includes(ext)) return 'pdf';
    if (['doc', 'docx'].includes(ext)) return 'word';
    if (['xls', 'xlsx', 'csv'].includes(ext)) return 'excel';
    if (['ppt', 'pptx'].includes(ext)) return 'ppt';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'image';
    if (['zip', 'rar'].includes(ext)) return 'zip';
    return 'default';
};

const submitFile = () => {
    fileForm.post(route('files.store'), {
        onSuccess: () => {
            showUploadModal.value = false;
            fileForm.reset();
        },
    });
};

const deleteFile = (id) => {
    if (confirm('Are you sure you want to delete this file?')) {
        useForm({}).delete(route('files.destroy', id));
    }
};

const handleFileDrop = (e) => {
    isDragging.value = false;
    const files = e.dataTransfer.files;
    if (files.length > 0) processFile(files[0]);
};

const handleFileInput = (e) => {
    const files = e.target.files;
    if (files.length > 0) processFile(files[0]);
};

const processFile = (file) => {
    fileForm.file = file;
    if (!fileForm.display_name) {
        fileForm.display_name = file.name.split('.').slice(0, -1).join('.');
    }
};

const triggerDownload = (file) => {
    if (file.is_secure) {
        selectedFileToDownload.value = file;
        downloadPassword.value = '';
        showDownloadModal.value = true;
    } else {
        postDownload(route('files.download', file.id), {});
    }
};

const submitDownload = () => {
    if (!selectedFileToDownload.value) return;
    postDownload(route('files.download', selectedFileToDownload.value.id), {
        password: downloadPassword.value
    });
    showDownloadModal.value = false;
    downloadPassword.value = '';
    selectedFileToDownload.value = null;
};

const postDownload = (url, data) => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    
    // CSRF Token (Assuming meta tag exists in app layout, if not, use page prop if available but meta is standard in Laravel)
    // Fallback if meta not found: try to get from Inertia page props if passed, but usually meta is best for pure HTML form.
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    if (tokenMeta) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = tokenMeta.getAttribute('content');
        form.appendChild(csrfInput);
    }

    for (const key in data) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = data[key];
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
};
</script>

<template>
    <Head title="File Repository" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-gray-50 pb-20">
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
                            <h1 class="text-4xl font-extrabold text-white tracking-tight drop-shadow-md">File Repository</h1>
                            <p class="text-blue-100 text-sm mt-1 font-medium opacity-90">Manage and organize your documents.</p>
                        </div>
                        <div v-if="$page.props.auth.user" class="flex gap-3">
                            <button 
                                @click="showUploadModal = true"
                                class="bg-white text-blue-900 hover:bg-blue-50 px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md hover:shadow-lg flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Upload File
                            </button>
                        </div>
                    </div>

                    <!-- Search Bar & Filter -->
                    <div class="flex flex-col md:flex-row gap-3">
                         <div class="relative flex-1 max-w-2xl">
                             <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input 
                                v-model="searchQuery"
                                type="text" 
                                placeholder="Search files..." 
                                class="block w-full rounded-2xl border border-white/20 bg-white/10 backdrop-blur-md py-4 pl-12 pr-12 text-white placeholder-blue-200 shadow-xl focus:border-white/40 focus:bg-white/20 focus:ring-0 focus:outline-none transition-all"
                            >
                             <button class="absolute inset-y-0 right-2 flex items-center px-4 text-white hover:text-blue-100 transition-colors">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>
                        </div>
                         <!-- Filter Dropdown (Glassy) -->
                        <div class="relative w-full md:w-auto">
                             <select v-model="selectedCategory" class="w-full md:w-48 appearance-none rounded-2xl border border-white/20 bg-white/10 backdrop-blur-md py-4 pl-4 pr-10 text-white shadow-xl focus:border-white/40 focus:bg-white/20 focus:ring-0 focus:outline-none transition-all cursor-pointer">
                                <option v-for="cat in categories" :key="cat" :value="cat" class="text-gray-900">
                                    {{ cat === 'All' ? 'All Tags' : cat }}
                                </option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-blue-200">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="mx-auto max-w-7xl px-4 mt-8">
                
                 <!-- Recent Uploads Section -->
                <div v-if="recentFiles && recentFiles.length > 0" class="mb-8">
                    <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4 px-1">Recent Activity</h2>
                    <div class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
                         <div 
                            v-for="file in recentFiles" 
                            :key="'recent-' + file.id" 
                            class="min-w-[140px] w-36 bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all flex flex-col items-center text-center cursor-pointer group flex-shrink-0 relative overflow-hidden"
                            @click="searchQuery = file.display_name"
                        >
                            <div v-if="file.is_secure" class="absolute top-2 right-2 text-orange-500">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                            </div>
                            <div 
                                :class="[
                                    'h-12 w-12 rounded-xl flex items-center justify-center font-bold text-xs uppercase mb-3 shadow-sm group-hover:scale-105 transition-transform',
                                    getFileIcon(file.file_path) === 'pdf' ? 'bg-red-50 text-red-600' :
                                    getFileIcon(file.file_path) === 'word' ? 'bg-blue-50 text-blue-600' :
                                    getFileIcon(file.file_path) === 'excel' ? 'bg-green-50 text-green-600' :
                                    'bg-blue-50 text-blue-600'
                                ]"
                            >
                                {{ getFileIcon(file.file_path).toUpperCase() }}
                            </div>
                            <h3 class="text-xs font-bold text-gray-800 truncate w-full" :title="file.display_name">{{ file.display_name }}</h3>
                            <p class="text-[10px] text-gray-400 mt-1">{{ formatDate(file.created_at).split(',')[0] }}</p>
                        </div>
                    </div>
                </div>

                <!-- Main Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="px-6 py-5 border-b border-gray-100 flex flex-row items-center justify-between">
                         <div class="flex items-center gap-3">
                            <div class="w-1 h-5 bg-orange-500 rounded-sm"></div>
                            <h2 class="text-lg font-bold text-gray-900">All Files</h2>
                         </div>
                         <!-- View Toggle -->
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

                <!-- LIST VIEW -->
                <div v-if="viewMode === 'list'" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tag</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modified</th>
                                <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="file in filteredFiles" :key="file.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 rounded bg-gray-100 flex items-center justify-center mr-3 text-gray-500 font-bold text-xs uppercase relative">
                                            {{ getFileIcon(file.file_path).substring(0,3) }}
                                            <span v-if="file.is_secure" class="absolute -top-1 -right-1 bg-white rounded-full p-0.5 shadow-sm">
                                                <svg class="w-3 h-3 text-orange-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                                            </span>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900 flex flex-col">
                                            <span>{{ file.display_name }}</span>
                                            <span v-if="file.is_secure" class="text-[10px] text-orange-500 font-bold flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                SECURE
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-50 text-blue-700">
                                        {{ file.category }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatSize(file.size) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDate(file.updated_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-3 items-center">
                                    <button @click="triggerDownload(file)" class="text-blue-600 hover:text-blue-900 p-1.5 hover:bg-blue-50 rounded-full transition-colors" title="Download">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                    </button>
                                    <button 
                                        v-if="$page.props.auth.user"
                                        @click="deleteFile(file.id)"
                                        class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-full transition-colors"
                                        title="Delete"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredFiles.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500 italic">No files found matching your criteria.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- GRID VIEW -->
                <div v-if="viewMode === 'grid'" class="p-6 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 bg-gray-50/50">
                    <div 
                        v-for="file in filteredFiles" 
                        :key="file.id" 
                        class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all group relative aspect-square flex flex-col"
                    >
                        <div class="absolute top-2 right-2 z-10 flex gap-1" v-if="$page.props.auth.user">
                            <button @click="deleteFile(file.id)" class="opacity-0 group-hover:opacity-100 transition-opacity bg-white rounded-full p-1 text-red-500 hover:bg-red-50 border border-gray-200 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                        
                        <div v-if="file.is_secure" class="absolute top-2 left-2 z-10 bg-white/80 backdrop-blur rounded-full p-1 shadow-sm text-orange-500">
                             <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </div>

                        <div class="flex-1 flex items-center justify-center mb-3">
                            <div 
                                :class="[
                                    'h-16 w-16 rounded-2xl flex items-center justify-center font-black text-lg shadow-sm',
                                    getFileIcon(file.file_path) === 'pdf' ? 'bg-red-100 text-red-600' :
                                    getFileIcon(file.file_path) === 'word' ? 'bg-blue-100 text-blue-600' :
                                    getFileIcon(file.file_path) === 'excel' ? 'bg-green-100 text-green-600' :
                                    'bg-gray-100 text-gray-600'
                                ]"
                            >
                                {{ getFileIcon(file.file_path).toUpperCase() }}
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <h3 class="text-sm font-bold text-gray-900 truncate w-full" :title="file.display_name">{{ file.display_name }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ formatSize(file.size) }}</p>
                            <button @click="triggerDownload(file)" class="mt-2 w-full py-1.5 bg-gray-50 hover:bg-blue-50 text-blue-600 hover:text-blue-800 text-xs font-bold rounded flex items-center justify-center gap-1">
                                <span v-if="file.is_secure">Unlock</span>
                                <span v-else>Download</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            </button>
                        </div>
                    </div>
                     <div v-if="filteredFiles.length === 0" class="col-span-full py-10 text-center text-gray-500 italic">No files found.</div>
                </div>
                    </div> <!-- Close p-0 -->
                </div> <!-- Close Main Card -->
            </div>

            <!-- Upload Modal -->
            <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                 <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                    <h3 class="text-lg font-bold mb-4">Upload New File</h3>
                    <form @submit.prevent="submitFile" class="space-y-4">
                        <div 
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleFileDrop"
                            :class="[
                                'border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors',
                                isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:bg-gray-50'
                            ]"
                        >
                            <input type="file" @change="handleFileInput" class="hidden" id="repoFileInput">
                            <label for="repoFileInput" class="cursor-pointer">
                                <span v-if="!fileForm.file" class="text-gray-500 text-sm">Click to browse or drag file here</span>
                                <span v-else class="text-blue-600 font-bold block">{{ fileForm.file.name }}</span>
                            </label>
                        </div>
                         <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">Display Name</label>
                            <input v-model="fileForm.display_name" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">Tag (Category)</label>
                            <input v-model="fileForm.category" type="text" placeholder="e.g. Finance, HR, Project X" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">Description</label>
                            <textarea v-model="fileForm.description" rows="2" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                        
                        <!-- Secure Options -->
                        <div class="pt-2 border-t mt-2">
                             <div class="flex items-center gap-2 mb-2">
                                <input type="checkbox" v-model="fileForm.is_secure" id="is_secure" class="rounded text-blue-600 focus:ring-blue-500">
                                <label for="is_secure" class="text-sm font-bold text-gray-700 flex items-center gap-1">
                                    <svg class="w-3 h-3 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                    Secure File
                                </label>
                            </div>
                            <div v-if="fileForm.is_secure">
                                <label class="block text-xs font-bold text-gray-500 uppercase">Set Password</label>
                                <input v-model="fileForm.password" type="text" placeholder="Enter password to download" class="w-full rounded-lg border-orange-300 focus:ring-orange-500 focus:border-orange-500 bg-orange-50" :required="fileForm.is_secure">
                                <p class="text-[10px] text-gray-500 mt-1">Users must enter this password to download the file.</p>
                            </div>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="showUploadModal = false" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</button>
                            <button type="submit" :disabled="fileForm.processing || !fileForm.file" class="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Upload</button>
                        </div>
                    </form>
                 </div>
            </div>
            
            <!-- Password Modal -->
            <div v-if="showDownloadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                 <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 mb-4">
                        <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Restricted Access</h3>
                    <p class="text-sm text-gray-500 mb-4">Enter the password to unlock <strong>{{ selectedFileToDownload?.display_name }}</strong>.</p>
                    
                    <form @submit.prevent="submitDownload">
                        <input 
                            v-model="downloadPassword" 
                            type="password" 
                            placeholder="File Password" 
                            class="w-full rounded-lg border-gray-300 mb-4 text-center tracking-widest"
                            autoFocus
                            required
                        >
                        <div class="flex gap-2">
                            <button type="button" @click="showDownloadModal = false; selectedFileToDownload = null" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">Cancel</button>
                            <button type="submit" class="flex-1 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 text-sm font-bold">Unlock & Download</button>
                        </div>
                    </form>
                 </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

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
