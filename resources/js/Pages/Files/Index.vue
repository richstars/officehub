<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';
import { renderAsync } from 'docx-preview';
import * as XLSX from 'xlsx';

const props = defineProps({
    files: Array,
    recentFiles: Array,
    totalStorageSize: Number,
    currentCategory: String,
});

const searchQuery = ref('');
const selectedCategory = ref('All');
// If 'All', we show folders. If a category is selected (from folder click), we filter by it.
const isFolderView = ref(true); 

const viewMode = ref('list'); 
const showUploadModal = ref(false);
const showDownloadModal = ref(false);
const showPreviewModal = ref(false);
const selectedFileToPreview = ref(null);
const isDragging = ref(false);
const zoomLevel = ref(1);
const docxContainer = ref(null);
const excelContainer = ref(null);
const isRenderingOffice = ref(false);
const officeError = ref(null);

const fileCategories = ['Laporan Tahunan', 'Laporan Bulanan', 'SOP', 'Laporan Hasil Pengawasan', 'Surat Perintah Tugas', 'Flight Approval'];

const categoryIcons = {
    'Laporan Tahunan': 'calendar',
    'Laporan Bulanan': 'document-text',
    'SOP': 'clipboard-check',
    'Laporan Hasil Pengawasan': 'shield-check',
    'Surat Perintah Tugas': 'briefcase',
    'Flight Approval': 'paper-airplane'
};

const categoryColors = {
    'Laporan Tahunan': 'text-blue-500 bg-blue-50',
    'Laporan Bulanan': 'text-emerald-500 bg-emerald-50',
    'SOP': 'text-violet-500 bg-violet-50',
    'Laporan Hasil Pengawasan': 'text-red-500 bg-red-50',
    'Surat Perintah Tugas': 'text-amber-500 bg-amber-50',
    'Flight Approval': 'text-sky-500 bg-sky-50'
};

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
const actionType = ref('download');
const isPasswordVerified = ref(false);

const displayFolders = ['Laporan Tahunan', 'Laporan Bulanan', 'SOP', 'Laporan Hasil Pengawasan', 'Surat Perintah Tugas', 'Flight Approval'];

const categories = computed(() => {
    return ['All', ...displayFolders];
});

const selectFolder = (category) => {
    if (category === 'Laporan Hasil Pengawasan') {
        router.visit(route('supervision-reports.index'));
        return;
    }
    selectedCategory.value = category;
    isFolderView.value = false;
};

const backToFolders = () => {
    selectedCategory.value = 'All';
    isFolderView.value = true;
};

const filteredFiles = computed(() => {
    return props.files.filter(file => {
        const matchesSearch = file.display_name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                             (file.description && file.description.toLowerCase().includes(searchQuery.value.toLowerCase()));
        
        // If in folder view (isFolderView is true), we only show files IF search query is present (optional, or just hide files)
        // But usually folder view hides list. 
        // Logic: 
        // 1. If searching, ignore folders and show all matching files across categories?
        //    Let's keep it simple: If searching, show matching files from ALL categories.
        //    If NOT searching:
        //      If isFolderView -> display nothing (or recent), user must click folder.
        //      If !isFolderView -> display files for selectedCategory.
        
        if (searchQuery.value) return matchesSearch;

        // In folder view, we show ALL files (filtered by search if any, but search is handled above)
        // If not searching and isFolderView is true, return true (all files)
        if (isFolderView.value) return true; 

        return file.category === selectedCategory.value;
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

const deleteFile = (file) => {
    if (confirm('Are you sure you want to delete this file?')) {
        const url = file.source === 'supervision_report'
            ? route('supervision-reports.destroy', file.id)
            : route('files.destroy', file.id);
            
        useForm({}).delete(url, {
            preserveScroll: true,
        });
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
    selectedFileToDownload.value = file;
    if (file.is_secure) {
        showDownloadModal.value = true;
        downloadPassword.value = '';
        actionType.value = 'download';
    } else {
        const url = file.source === 'supervision_report' 
            ? route('supervision-reports.download', file.id)
            : route('files.download', file.id);
        postDownload(url, {});
    }
};

const submitDownload = async () => {
    if (!selectedFileToDownload.value) return;

    const file = selectedFileToDownload.value;
    const isReport = file.source === 'supervision_report';

    if (actionType.value === 'download') {
        const url = isReport 
            ? route('supervision-reports.download', file.id)
            : route('files.download', file.id);
            
        postDownload(url, {
            password: downloadPassword.value
        });
        showDownloadModal.value = false;
        downloadPassword.value = '';
        selectedFileToDownload.value = null;
    } else if (actionType.value === 'preview') {
        const url = isReport 
            ? route('supervision-reports.verify-password', file.id)
            : route('files.verify-password', file.id);

        try {
            await window.axios.post(url, {
                password: downloadPassword.value
            });
            // Password correct
            showDownloadModal.value = false;
            downloadPassword.value = '';
            selectedFileToDownload.value = null;
            isPasswordVerified.value = true;
            
            // Proceed to preview
            openPreview(file);
            isPasswordVerified.value = false;

        } catch (error) {
            alert('Incorrect password.');
            downloadPassword.value = '';
        }
    }
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

const previewType = computed(() => {
    if (!selectedFileToPreview.value) return null;
    const ext = selectedFileToPreview.value.file_path.split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'image';
    if (['pdf'].includes(ext)) return 'pdf';
    if (['doc', 'docx'].includes(ext)) return 'word';
    if (['xls', 'xlsx', 'csv'].includes(ext)) return 'excel';
    if (['ppt', 'pptx'].includes(ext)) return 'ppt';
    return 'other';
});

const openPreview = async (file) => {
    if (file.is_secure && !isPasswordVerified.value) { // Assuming we track verification per session or just prompt every time.
         // Actually, let's just use the same pattern: Prompt every time for now or reuse the modal.
         // Since Files/Index doesn't have 'actionType' yet, we need to add it or adapt.
         // But simplicity: prompt every time if secured.
         selectedFileToDownload.value = file;
         downloadPassword.value = '';
         actionType.value = 'preview';
         showDownloadModal.value = true;
         return;
    }

    selectedFileToPreview.value = file;
    // ... existing logic
    zoomLevel.value = 1;
    showPreviewModal.value = true;
    officeError.value = null;

    if (previewType.value === 'word' || previewType.value === 'excel') {
        isRenderingOffice.value = true;
        try {
            const response = await fetch(`/storage/${file.file_path}`);
            if (!response.ok) throw new Error('Failed to load file');
            const blob = await response.blob();

            await nextTick();

            if (previewType.value === 'word') {
                 if (docxContainer.value) {
                    docxContainer.value.innerHTML = '';
                    await renderAsync(blob, docxContainer.value, docxContainer.value, {
                        className: 'docx-preview',
                        inWrapper: true
                    });
                }
            } else if (previewType.value === 'excel') {
                 if (excelContainer.value) {
                    const arrayBuffer = await blob.arrayBuffer();
                    const workbook = XLSX.read(arrayBuffer);
                    const firstSheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[firstSheetName];
                    const html = XLSX.utils.sheet_to_html(worksheet, { id: 'excel-table', editable: false });
                    excelContainer.value.innerHTML = html;
                }
            }
        } catch (error) {
            console.error('Error rendering office file:', error);
            officeError.value = "Failed to render preview.";
        } finally {
            isRenderingOffice.value = false;
        }
    }
};

const closePreview = () => {
    showPreviewModal.value = false;
    selectedFileToPreview.value = null;
    zoomLevel.value = 1;
};

const zoomIn = () => {
    if (zoomLevel.value < 3) zoomLevel.value += 0.25;
};

const zoomOut = () => {
    if (zoomLevel.value > 0.5) zoomLevel.value -= 0.25;
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
                            <h1 class="text-4xl font-extrabold text-white tracking-tight drop-shadow-md">Page File</h1>
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

                    <!-- Category Title / Back Button -->
                    <div v-if="!isFolderView && !searchQuery" class="mt-8 flex items-center gap-4 animate-fade-in-up">
                        <button @click="backToFolders" class="p-2 bg-white/10 hover:bg-white/20 rounded-full text-white transition-colors backdrop-blur-md">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        </button>
                        <div>
                            <h2 class="text-3xl font-bold text-white tracking-tight">{{ selectedCategory }}</h2>
                            <p class="text-blue-100 text-sm font-medium opacity-80">Manage your {{ selectedCategory.toLowerCase() }} files.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="mx-auto max-w-7xl px-4 mt-8 pb-20">
                
                <!-- Folders Grid (Only visible when no search & isFolderView) -->
                <div v-if="isFolderView && !searchQuery" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 mb-12">
                    <div 
                        v-for="cat in displayFolders" 
                        :key="cat"
                        @click="selectFolder(cat)"
                        class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all cursor-pointer group flex flex-col items-center justify-center gap-4 h-40"
                    >
                        <div :class="['w-14 h-14 rounded-2xl flex items-center justify-center transition-transform group-hover:scale-110', categoryColors[cat] || 'bg-blue-50 text-blue-500']">
                            <!-- Icons based on category -->
                             <svg v-if="categoryIcons[cat] === 'calendar'" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                             <svg v-else-if="categoryIcons[cat] === 'document-text'" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                             <svg v-else-if="categoryIcons[cat] === 'clipboard-check'" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                             <svg v-else-if="categoryIcons[cat] === 'shield-check'" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                             <svg v-else-if="categoryIcons[cat] === 'briefcase'" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                             <svg v-else-if="categoryIcons[cat] === 'folder-open'" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" /></svg>
                             <svg v-else-if="categoryIcons[cat] === 'paper-airplane'" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                             <!-- Fallback/Default Icons -->
                             <svg v-else class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                        </div>
                        <span class="font-bold text-gray-700 text-xs text-center uppercase tracking-wide px-2 truncate w-full">{{ cat }}</span>
                    </div>
                </div>

                 <!-- Recent Uploads Section (Only on main folder view) -->
                <div v-if="isFolderView && !searchQuery && recentFiles && recentFiles.length > 0" class="mb-12">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-1 h-6 bg-orange-500 rounded-full"></div>
                        <h2 class="text-xl font-bold text-gray-800">Recent Activity</h2>
                    </div>
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

                <!-- Files List / Grid -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 animate-fade-in-up">
                    <div class="px-6 py-5 border-b border-gray-100 flex flex-row items-center justify-between">
                         <div class="flex items-center gap-3">
                            <div class="w-1 h-5 bg-orange-500 rounded-sm"></div>
                            <h2 class="text-lg font-bold text-gray-900">{{ isFolderView && !searchQuery ? 'All Files' : (searchQuery ? 'Search Results' : selectedCategory) }}</h2>
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
                                    <button @click="openPreview(file)" class="text-green-600 hover:text-green-900 p-1.5 hover:bg-green-50 rounded-full transition-colors" title="Preview">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    </button>
                                     <button @click="triggerDownload(file)" class="text-blue-600 hover:text-blue-900 p-1.5 hover:bg-blue-50 rounded-full transition-colors" title="Download">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                    </button>
                                    <button 
                                        v-if="$page.props.auth.user"
                                        @click="deleteFile(file)"
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
                            <button @click="deleteFile(file)" class="opacity-0 group-hover:opacity-100 transition-opacity bg-white rounded-full p-1 text-red-500 hover:bg-red-50 border border-gray-200 shadow-sm">
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
                            <div class="flex gap-1 mt-2">
                                <button @click="openPreview(file)" class="flex-1 py-1.5 bg-green-50 hover:bg-green-100 text-green-600 text-xs font-bold rounded flex items-center justify-center">
                                    Preview
                                </button>
                                <button @click="triggerDownload(file)" class="flex-1 py-1.5 bg-gray-50 hover:bg-blue-50 text-blue-600 hover:text-blue-800 text-xs font-bold rounded flex items-center justify-center gap-1">
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
                            <div v-if="fileForm.processing" class="flex flex-col items-center justify-center h-full min-h-[100px]">
                                <svg class="animate-spin h-10 w-10 text-blue-600 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-sm font-bold text-gray-700">Uploading File...</span>
                                <span v-if="fileForm.progress" class="text-xs text-gray-500 font-mono mt-1">{{ fileForm.progress.percentage }}%</span>
                            </div>
                            <div v-else>
                                <input type="file" @change="handleFileInput" class="hidden" id="repoFileInput">
                                <label for="repoFileInput" class="cursor-pointer">
                                    <span v-if="!fileForm.file" class="text-gray-500 text-sm">Click to browse or drag file here</span>
                                    <span v-else class="text-blue-600 font-bold block">{{ fileForm.file.name }}</span>
                                </label>
                            </div>
                        </div>
                         <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">Display Name</label>
                            <input v-model="fileForm.display_name" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">Tag (Category)</label>
                            <select v-model="fileForm.category" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                                <option v-for="cat in fileCategories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
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
                            <button type="submit" class="flex-1 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 text-sm font-bold">
                            {{ actionType === 'preview' ? 'Unlock & Preview' : 'Unlock & Download' }}
                        </button>
                        </div>
                    </form>
                 </div>
                 </div>
            </div>

            <!-- Preview Modal -->
            <div v-if="showPreviewModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm animate-fade-in">
                <div class="bg-gray-900 rounded-xl shadow-2xl w-full max-w-6xl h-[85vh] flex flex-col overflow-hidden relative">
                    <!-- Header/Close -->
                    <div class="flex items-center justify-between px-4 py-3 bg-white/5 border-b border-white/10">
                        <h3 class="text-white font-medium truncate max-w-md">{{ selectedFileToPreview?.display_name }}</h3>
                        <div class="flex items-center gap-3">
                             <button @click="triggerDownload(selectedFileToPreview)" class="text-gray-400 hover:text-white transition-colors" title="Download">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                             </button>
                            <button @click="closePreview" class="text-gray-400 hover:text-white transition-colors bg-white/10 hover:bg-white/20 rounded-full p-1.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Main Preview Area -->
                    <div class="flex-1 bg-black/50 flex items-center justify-center relative overflow-hidden">
                        
                        <!-- Image Preview -->
                        <div v-if="previewType === 'image'" class="w-full h-full overflow-auto flex items-center justify-center p-4">
                            <img 
                                :src="'/storage/' + selectedFileToPreview.file_path" 
                                :style="{ transform: `scale(${zoomLevel})` }" 
                                class="max-w-full max-h-full object-contain transition-transform duration-200 ease-out origin-center"
                            >
                            
                            <!-- Zoom Controls -->
                            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex items-center gap-4 bg-gray-800/80 backdrop-blur-md px-4 py-2 rounded-full text-white shadow-xl border border-white/10 z-20">
                                <button @click="zoomOut" class="hover:text-blue-400 p-1 disabled:opacity-50" :disabled="zoomLevel <= 0.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                                </button>
                                <span class="font-mono text-sm min-w-[3ch] text-center">{{ Math.round(zoomLevel * 100) }}%</span>
                                <button @click="zoomIn" class="hover:text-blue-400 p-1 disabled:opacity-50" :disabled="zoomLevel >= 3">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                </button>
                                <button @click="zoomLevel = 1" class="ml-2 text-xs text-gray-400 hover:text-white border-l border-gray-600 pl-3">Reset</button>
                            </div>
                        </div>

                        <!-- PDF Preview -->
                        <iframe 
                            v-else-if="previewType === 'pdf'" 
                            :src="'/storage/' + selectedFileToPreview.file_path" 
                            class="w-full h-full bg-white"
                        ></iframe>

                        <!-- Word Preview (Local) -->
                        <div v-else-if="previewType === 'word'" class="w-full h-full bg-gray-100 overflow-auto p-8 flex justify-center">
                            <div v-if="isRenderingOffice" class="flex flex-col items-center justify-center p-10">
                                <svg class="animate-spin h-10 w-10 text-blue-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-gray-500 font-medium">Loading document...</p>
                            </div>
                            <div v-else-if="officeError" class="text-red-500 p-4 text-center bg-red-50 rounded-lg border border-red-200">
                                {{ officeError }}
                            </div>
                            <div v-show="!isRenderingOffice && !officeError" ref="docxContainer" class="bg-white shadow-lg p-8 min-h-[800px] w-full max-w-4xl"></div>
                        </div>

                        <!-- Excel Preview (Local) -->
                        <div v-else-if="previewType === 'excel'" class="w-full h-full bg-white overflow-auto p-4">
                             <div v-if="isRenderingOffice" class="flex flex-col items-center justify-center p-10">
                                <svg class="animate-spin h-10 w-10 text-green-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-gray-500 font-medium">Loading spreadsheet...</p>
                            </div>
                            <div v-else-if="officeError" class="text-red-500 p-4 text-center bg-red-50 rounded-lg border border-red-200">
                                {{ officeError }}
                            </div>
                            <div v-show="!isRenderingOffice && !officeError" ref="excelContainer" class="excel-preview-container prose max-w-none"></div>
                        </div>
                        
                         <!-- PowerPoint (PPT) Placeholder -->
                        <div v-else-if="previewType === 'ppt'" class="w-full h-full flex flex-col items-center justify-center p-10 text-center bg-gray-50">
                             <div class="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-10 h-10 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" /></svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">Preview Not Available Locally</h3>
                            <p class="text-gray-500 mb-6 max-w-sm">
                                PowerPoint previews require a public server. Please download the file to view it.
                            </p>
                            <button @click="triggerDownload(selectedFileToPreview)" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                Download Presentation
                            </button>
                        </div>

                         <!-- Fallback -->
                        <div v-else class="text-center p-10">
                            <div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2">Preview Unavailable</h3>
                            <p class="text-gray-400 mb-6">This file type cannot be previewed directly.</p>
                            <button @click="triggerDownload(selectedFileToPreview); closePreview()" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">Download File</button>
                        </div>
                    </div>
                 </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style>
/* Global Styles for rendered tables */
.excel-preview-container table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.excel-preview-container table td, .excel-preview-container table th {
    border: 1px solid #e5e7eb;
    padding: 0.5rem;
    text-align: left;
}
.excel-preview-container table th {
    background-color: #f9fafb;
    font-weight: 600;
}
/* Docx Preview Styles if needed */
.docx-wrapper {
    background: transparent !important;
    padding: 0 !important;
}
.docx-preview {
    min-height: 800px;
}
</style>

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
