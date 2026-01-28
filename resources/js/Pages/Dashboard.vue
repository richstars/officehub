<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

// --- Props & State ---
const props = defineProps({
    links: Array,
    files: Array,
    recentFiles: Array,
    announcement: Object,
    totalStorageSize: Number,
});

const searchQuery = ref('');
const selectedCategory = ref('All');
const showToolModal = ref(false);
const showFileModal = ref(false);
const showEditAnnouncementModal = ref(false);
const editingTool = ref(null);
const isDragging = ref(false);

const favorites = ref(JSON.parse(localStorage.getItem('office_hub_favorites') || '[]'));

const showDownloadModal = ref(false);
const downloadPassword = ref('');
const selectedFileToDownload = ref(null);
const selectedFileToPreview = ref(null);
const showPreviewModal = ref(false);

const fileCategories = ['General', 'HR', 'Finance', 'Flight Approval', 'Operations', 'IT', 'Marketing'];

// --- Filtering & Computed ---
const categories = computed(() => {
    const cats = new Set(['All', 'Flight Approval', 'General', 'HR', 'Finance', 'Operations', 'IT', 'Marketing']);
    props.links.forEach(l => cats.add(l.category));
    props.files.forEach(f => cats.add(f.category));
    return Array.from(cats);
});

const filteredLinks = computed(() => {
    return props.links
        .filter(link => {
            const matchesSearch = link.title.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                                 (link.description && link.description.toLowerCase().includes(searchQuery.value.toLowerCase()));
            const matchesCategory = selectedCategory.value === 'All' || link.category === selectedCategory.value;
            return matchesSearch && matchesCategory;
        })
        .sort((a, b) => {
            const aFav = favorites.value.includes(a.id);
            const bFav = favorites.value.includes(b.id);
            if (aFav && !bFav) return -1;
            if (!aFav && bFav) return 1;
            return 0;
        });
});

const filteredFiles = computed(() => {
    return props.files.filter(file => {
        const matchesSearch = file.display_name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                             (file.description && file.description.toLowerCase().includes(searchQuery.value.toLowerCase()));
        const matchesCategory = selectedCategory.value === 'All' || file.category === selectedCategory.value;
        return matchesSearch && matchesCategory;
    });
});

// --- Actions ---
const toggleFavorite = (id) => {
    const index = favorites.value.indexOf(id);
    if (index === -1) {
        favorites.value.push(id);
    } else {
        favorites.value.splice(index, 1);
    }
    localStorage.setItem('office_hub_favorites', JSON.stringify(favorites.value));
};

const formatSize = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};

// --- Forms ---
const announcementForm = useForm({
    content: props.announcement?.content || '',
});

const toolForm = useForm({
    title: '',
    url: '',
    category: 'Tools',
    icon: 'link',
    icon_file: null,
    description: '',
});

const fileForm = useForm({
    display_name: '',
    category: 'General',
    file: null,
    description: '',
    is_secure: false,
    password: '',
});

// --- Handlers ---
const openAddTool = () => {
    editingTool.value = null;
    toolForm.reset();
    showToolModal.value = true;
};

const openEditTool = (link) => {
    editingTool.value = link;
    toolForm.title = link.title;
    toolForm.url = link.url;
    toolForm.category = link.category;
    toolForm.icon = link.icon || 'link';
    toolForm.description = link.description || '';
    showToolModal.value = true;
};

const submitTool = () => {
    if (editingTool.value) {
        toolForm.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(route('links.update', editingTool.value.id), {
            onSuccess: () => {
                showToolModal.value = false;
                toolForm.reset();
            },
        });
    } else {
        toolForm.post(route('links.store'), {
            onSuccess: () => {
                showToolModal.value = false;
                toolForm.reset();
            },
        });
    }
};

const deleteTool = (id) => {
    if (confirm('Delete this tool?')) {
        useForm({}).delete(route('links.destroy', id));
    }
};

const submitFile = () => {
    fileForm.post(route('files.store'), {
        onSuccess: () => {
            showFileModal.value = false;
            fileForm.reset();
        },
    });
};

const handleFileDrop = (e) => {
    isDragging.value = false;
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileForm.file = files[0];
        if (!fileForm.display_name) {
            fileForm.display_name = files[0].name.split('.').slice(0, -1).join('.');
        }
    }
};

const handleFileInput = (e) => {
    const files = e.target.files;
    if (files.length > 0) {
        fileForm.file = files[0];
        if (!fileForm.display_name) {
            fileForm.display_name = files[0].name.split('.').slice(0, -1).join('.');
        }
    }
};

const deleteFile = (id) => {
    if (confirm('Delete this file?')) {
        useForm({}).delete(route('files.destroy', id));
    }
};

const submitAnnouncement = () => {
    announcementForm.post(route('announcements.store'), {
        onSuccess: () => {
            showEditAnnouncementModal.value = false;
        },
    });
};

const deleteAnnouncement = (id) => {
    if (confirm('Remove announcement?')) {
        useForm({}).delete(route('announcements.destroy', id));
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
    
    // CSRF Token
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

const openPreview = (file) => {
    selectedFileToPreview.value = file;
    showPreviewModal.value = true;
};

const closePreview = () => {
    showPreviewModal.value = false;
    selectedFileToPreview.value = null;
};

const handleToolIconInput = (e) => {
    if (e.target.files.length > 0) {
        toolForm.icon_file = e.target.files[0];
    }
};
</script>

<template>
    <Head title="AIR TRANSPORT OTORITAS BANDARA WILAYAH VII" />

    <AuthenticatedLayout>
        <!-- Simple Functional Dashboard Design -->
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
                    <!-- Announcement (Glassy) -->
                    <div v-if="announcement" class="mb-6 bg-white/10 backdrop-blur-md border border-white/20 rounded-xl p-3 flex justify-between items-center text-white text-sm shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="bg-gradient-to-r from-amber-400 to-orange-400 text-white text-[10px] font-bold px-2 py-0.5 rounded shadow-sm uppercase tracking-wide">News</span>
                            <span class="font-medium text-blue-50">{{ announcement.content }}</span>
                        </div>
                        <button v-if="$page.props.auth.user" @click="showEditAnnouncementModal = true" class="text-xs text-white/80 hover:text-white underline decoration-white/50 hover:decoration-white transition-colors">Edit</button>
                    </div>

                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                        <div>
                            <h1 class="text-4xl font-extrabold text-white tracking-tight drop-shadow-md">AIR TRANSPORT OTORITAS BANDARA WILAYAH VII</h1>
                            <p class="text-blue-100 text-sm mt-1 font-medium opacity-90">Welcome to your internal workspace.</p>
                        </div>
                        <div v-if="$page.props.auth.user" class="flex gap-3">
                            <button @click="openAddTool" class="bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm hover:shadow-md flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Add Tool
                            </button>
                             <button @click="showFileModal = true" class="bg-white text-blue-900 hover:bg-blue-50 px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                Upload File
                            </button>
                        </div>
                    </div>

                    <!-- Search Bar (Glassy) -->
                    <div class="relative max-w-2xl">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                             <svg class="h-5 w-5 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Type to search tools & files..." 
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

            <div class="mx-auto max-w-7xl px-4 mt-8">
                <!-- Category Tabs -->
                <!-- Category Tabs Removed -->
                <div class="hidden mb-6"></div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Tools Area -->
                    <div class="lg:col-span-2">
                        <h2 class="text-lg font-bold text-gray-800 mb-4 border-l-4 border-blue-800 pl-3">Applications & Tools</h2>
                        
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div 
                                v-for="link in filteredLinks" 
                                :key="link.id" 
                                class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all group relative"
                            >
                                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1" v-if="$page.props.auth.user">
                                    <button @click.prevent="openEditTool(link)" class="p-1 hover:bg-gray-100 rounded text-gray-400 hover:text-blue-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </button>
                                    <button @click.prevent="deleteTool(link.id)" class="p-1 hover:bg-gray-100 rounded text-gray-400 hover:text-red-600">
                                         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>

                                <a :href="link.url" target="_blank" class="flex flex-col items-center text-center h-full">
                                    <div class="h-24 w-full rounded-lg flex items-center justify-center mb-3 overflow-hidden group-hover:shadow-sm transition-all bg-gray-50">
                                        <img v-if="link.icon_path" :src="'/storage/' + link.icon_path" alt="Icon" class="w-full h-full object-contain p-2 hover:scale-110 transition-transform duration-300">
                                        <div v-else class="h-full w-full flex items-center justify-center text-blue-600 group-hover:text-blue-700 transition-colors">
                                            <svg v-if="link.icon === 'envelope'" class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                            <svg v-else-if="link.icon === 'user-group'" class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                            <svg v-else class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                        </div>
                                    </div>
                                    <h3 class="font-bold text-gray-900 text-sm mb-1">{{ link.title }}</h3>
                                    <p class="text-xs text-gray-500 line-clamp-2">{{ link.description }}</p>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Area -->
                    <div class="space-y-6">
                        <!-- Storage Widget -->
                         <div v-if="$page.props.auth.user" class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                            <h3 class="font-bold text-gray-800 text-sm mb-2">Storage Usage</h3>
                            <div class="flex items-end gap-2">
                                <span class="text-2xl font-bold text-blue-600">{{ formatSize(totalStorageSize) }}</span>
                                <span class="text-xs text-gray-500 mb-1.5">used of Unlimited</span>
                            </div>
                        </div>

                         <!-- File Repo -->
                         <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-bold text-gray-800 border-l-4 border-orange-400 pl-3">File Repository</h3>
                                <Link :href="route('files.index')" class="text-xs font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                                    View All
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </Link>
                            </div>
                            
                            <div class="space-y-3">
                                <div v-if="filteredFiles.length === 0" class="text-center py-4 text-gray-400 text-xs italic">
                                    No files found.
                                </div>
                                <div 
                                    v-for="file in filteredFiles.slice(0, 5)" 
                                    :key="file.id"
                                    class="group flex items-start gap-3 p-2 hover:bg-gray-50 rounded-lg transition-colors border-b border-gray-50 last:border-0"
                                >
                                    <div class="relative bg-gray-100 p-2 rounded text-gray-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                         <span v-if="file.is_secure" class="absolute -top-1 -right-1 bg-white rounded-full p-0.5 shadow-sm">
                                            <svg class="w-3 h-3 text-orange-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-semibold text-gray-800 truncate">{{ file.display_name }}</p>
                                            <svg v-if="file.is_secure" class="w-3 h-3 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                        </div>
                                        <p class="text-[10px] text-gray-400">{{ formatSize(file.size) }} • {{ formatDate(file.created_at) }}</p>
                                    </div>
                                    <div class="flex gap-1">
                                         <button @click="openPreview(file)" class="text-green-600 hover:bg-green-50 p-1.5 rounded transition-colors" title="Preview">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                         </button>
                                         <button @click="triggerDownload(file)" class="text-blue-600 hover:bg-blue-50 p-1.5 rounded transition-colors" title="Download">
                                            <svg v-if="file.is_secure" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                         </button>
                                         <button v-if="$page.props.auth.user" @click="deleteFile(file.id)" class="text-red-400 hover:bg-red-50 p-1.5 rounded transition-colors" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                         </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Tool Modal -->
            <div v-if="showToolModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                    <h3 class="text-lg font-bold mb-4">{{ editingTool ? 'Edit Tool' : 'Add New Tool' }}</h3>
                     <form @submit.prevent="submitTool" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">Title</label>
                            <input v-model="toolForm.title" type="text" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">URL</label>
                            <input v-model="toolForm.url" type="url" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        <!-- Category Field Removed -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">Icon (Custom)</label>
                            
                            <div 
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="(e) => { isDragging = false; if(e.dataTransfer.files.length > 0) toolForm.icon_file = e.dataTransfer.files[0]; }"
                                :class="[
                                    'border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors mb-4',
                                    isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:bg-gray-50'
                                ]"
                            >
                                <div v-if="toolForm.processing" class="flex flex-col items-center justify-center h-24">
                                     <svg class="animate-spin h-8 w-8 text-blue-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-sm font-bold text-gray-700">Uploading...</span>
                                    <span v-if="toolForm.progress" class="text-xs text-gray-500">{{ toolForm.progress.percentage }}%</span>
                                </div>
                                <div v-else>
                                    <input type="file" @change="handleToolIconInput" class="hidden" id="toolIconInput" accept="image/*">
                                    <label for="toolIconInput" class="cursor-pointer block">
                                        <div v-if="!toolForm.icon_file" class="flex flex-col items-center">
                                            <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            <span class="text-gray-500 text-xs">Drop image here or click to browse</span>
                                        </div>
                                        <div v-else class="flex items-center justify-center gap-2">
                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            <span class="text-blue-600 font-bold text-sm block truncate max-w-[200px]">{{ toolForm.icon_file.name }}</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                             
                            <label class="block text-xs font-bold text-gray-500 uppercase">Or preset icon:</label>
                            <select v-model="toolForm.icon" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 mt-1">
                                <option value="link">General Link</option>
                                <option value="envelope">Email</option>
                                <option value="user-group">People/HR</option>
                            </select>
                        </div>
                         <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase">Description</label>
                            <textarea v-model="toolForm.description" rows="2" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="showToolModal = false" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</button>
                            <button type="submit" class="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- File Modal -->
            <div v-if="showFileModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                 <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                    <h3 class="text-lg font-bold mb-4">Upload File</h3>
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
                                <input type="file" @change="handleFileInput" class="hidden" id="fileInput">
                                <label for="fileInput" class="cursor-pointer">
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
                            <label class="block text-xs font-bold text-gray-500 uppercase">Category</label>
                            <select v-model="fileForm.category" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                                <option v-for="cat in fileCategories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                        
                        <!-- Secure Options -->
                        <div class="pt-2 border-t mt-2">
                             <div class="flex items-center gap-2 mb-2">
                                <input type="checkbox" v-model="fileForm.is_secure" id="is_secure_dash" class="rounded text-blue-600 focus:ring-blue-500">
                                <label for="is_secure_dash" class="text-sm font-bold text-gray-700 flex items-center gap-1">
                                    <svg class="w-3 h-3 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                    Secure File
                                </label>
                            </div>
                            <div v-if="fileForm.is_secure">
                                <label class="block text-xs font-bold text-gray-500 uppercase">Set Password</label>
                                <input v-model="fileForm.password" type="text" placeholder="Enter password to download" class="w-full rounded-lg border-orange-300 focus:ring-orange-500 focus:border-orange-500 bg-orange-50" :required="fileForm.is_secure">
                            </div>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="showFileModal = false" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</button>
                            <button type="submit" class="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Upload</button>
                        </div>
                    </form>
                 </div>
            </div>

             <!-- Announcement Modal -->
            <div v-if="showEditAnnouncementModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                 <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                    <h3 class="text-lg font-bold mb-4">Update Announcement</h3>
                    <form @submit.prevent="submitAnnouncement">
                        <textarea v-model="announcementForm.content" class="w-full rounded-lg border-gray-300 mb-4" rows="3" placeholder="Enter announcement text..."></textarea>
                        <div class="flex gap-2">
                              <button type="button" @click="showEditAnnouncementModal = false" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</button>
                              <button type="submit" class="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Post</button>
                        </div>
                        <button v-if="announcement" @click="deleteAnnouncement(announcement.id)" type="button" class="w-full text-center text-red-500 text-xs mt-3 hover:underline">Remove Announcement</button>
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

            <!-- Preview Modal -->
            <div v-if="showPreviewModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl h-[80vh] flex flex-col overflow-hidden relative">
                    <button @click="closePreview" class="absolute top-4 right-4 bg-white/50 hover:bg-white p-2 rounded-full z-10 transition-colors">
                         <svg class="w-6 h-6 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <div class="flex-1 bg-gray-100 flex items-center justify-center p-4">
                        <iframe v-if="selectedFileToPreview && !selectedFileToPreview.is_secure" :src="'/storage/' + selectedFileToPreview.file_path" class="w-full h-full rounded shadow-sm border border-gray-200"></iframe>
                        <div v-else class="text-center p-10">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            <h3 class="text-lg font-bold text-gray-700">Preview Unavailable</h3>
                            <p class="text-gray-500">Secure files or unsupported types cannot be previewed.</p>
                            <button @click="triggerDownload(selectedFileToPreview); closePreview()" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Download Instead</button>
                        </div>
                    </div>
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
