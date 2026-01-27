<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, nextTick } from 'vue';
import { renderAsync } from 'docx-preview';
import * as XLSX from 'xlsx';
import axios from 'axios';

const props = defineProps({
    reports: Array,
    airportStats: Array,
    airlineStats: Array,
    airports: Array,
    airlines: Array,
});

const searchQuery = ref('');
const selectedAirport = ref('All');
const selectedAirline = ref('All');
const selectedYear = ref('All'); // Year Filter State
const showUploadModal = ref(false);
const isDragging = ref(false);

// Action States
const showDownloadModal = ref(false);
const downloadPassword = ref('');
const selectedFileToDownload = ref(null);
const actionType = ref('download'); // 'download' or 'preview'
const showPreviewModal = ref(false);
const selectedFileToPreview = ref(null);
const zoomLevel = ref(1);
const docxContainer = ref(null);
const excelContainer = ref(null);
const isRenderingOffice = ref(false);
const officeError = ref(null);

const form = useForm({
    name: '',
    start_date: '',
    end_date: '',
    file: null,
    is_secure: false,
    password: '',
    locations: [
        { airport_id: '', airlines: [''] }
    ],
});

// Computed Stats based on Filtered Reports
const computedAirportStats = computed(() => {
    const stats = {};
    filteredReports.value.forEach(report => {
        report.locations.forEach(loc => {
            // Count this airport ONLY if the location contains the selected airline (or All airlines)
            const airlineMatch = selectedAirline.value === 'All' || loc.airlines.some(a => a.id === selectedAirline.value);
            
            if (airlineMatch) {
                if (!stats[loc.airport]) stats[loc.airport] = 0;
                stats[loc.airport]++;
            }
        });
    });

    let results = Object.entries(stats)
        .map(([name, count]) => ({ name, count }));

    // If a specific airport is selected, show ONLY that airport
    if (selectedAirport.value !== 'All') {
        const targetAirport = props.airports.find(a => a.id === selectedAirport.value);
        if (targetAirport) {
            results = results.filter(r => r.name === targetAirport.name);
        } else {
            results = [];
        }
    }

    return results.sort((a, b) => b.count - a.count);
});

const computedAirlineStats = computed(() => {
    const stats = {};
    filteredReports.value.forEach(report => {
        report.locations.forEach(loc => {
             // Count airlines in this location ONLY if the location matches the selected airport (or All airports)
            const airportMatch = selectedAirport.value === 'All' || loc.airport_id === selectedAirport.value;
            
            if (airportMatch) {
                loc.airlines.forEach(airline => {
                    // We will filter by Selected Airline at the end to show only one, 
                    // but for now we aggregate relevant airlines found in legitimate locations.
                    if (!stats[airline.name]) stats[airline.name] = 0;
                    stats[airline.name]++;
                });
            }
        });
    });
    
    let results = Object.entries(stats)
        .map(([name, count]) => {
            const airlineObj = props.airlines.find(a => a.name === name);
            return { 
                name, 
                count, 
                color: airlineObj?.color || '#3b82f6' 
            };
        });

    // If a specific airline is selected, show ONLY that airline
    if (selectedAirline.value !== 'All') {
         const targetAirline = props.airlines.find(a => a.id === selectedAirline.value);
         if (targetAirline) {
             results = results.filter(r => r.name === targetAirline.name);
         } else {
             results = [];
         }
    }

    return results.sort((a, b) => b.count - a.count);
});

// Chart Configuration
const airportChartOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
    dataLabels: { enabled: false },
    xaxis: { 
        categories: computedAirportStats.value.map(s => s.name),
        labels: { 
            style: { colors: '#64748b' },
            formatter: (val) => Math.floor(val) 
        },
        forceNiceScale: true,
        tickAmount: 5 
    },
    yaxis: { labels: { style: { colors: '#64748b', fontSize: '11px' } } },
    colors: ['#3b82f6'],
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
    tooltip: { 
        theme: 'light',
        y: { formatter: (val) => Math.floor(val) }
    }
}));

const airportSeries = computed(() => [{
    name: 'Frekuensi',
    data: computedAirportStats.value.map(s => s.count)
}]);

// Airline Chart
const airlineChartOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
    plotOptions: { 
        bar: { 
            horizontal: true, 
            borderRadius: 4, 
            barHeight: '70%', 
            distributed: true 
        } 
    },
    dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, offsetX: 0 },
    xaxis: { 
        categories: computedAirlineStats.value.map(s => s.name),
        labels: { 
            style: { colors: '#64748b' },
            formatter: (val) => Math.floor(val)
        }
    },
    yaxis: { 
        labels: { 
            show: true,
            style: { colors: '#64748b', fontSize: '11px', fontWeight: 700 },
            maxWidth: 200 
        } 
    },
    colors: computedAirlineStats.value.map(s => s.color || '#3b82f6'),
    legend: { show: false },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
    tooltip: { 
        theme: 'light',
        y: { formatter: (val) => Math.floor(val) }
    }
}));

const airlineSeries = computed(() => [{
    name: 'Frekuensi',
    data: computedAirlineStats.value.map(s => ({
        x: s.name, 
        y: s.count
    }))
}]);

// Upload Logic
const handleFileDrop = (e) => {
    isDragging.value = false;
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        form.file = files[0];
        if (!form.name) form.name = files[0].name.split('.').slice(0, -1).join('.');
    }
};

const handleFileInput = (e) => {
    const files = e.target.files;
    if (files.length > 0) {
        form.file = files[0];
        if (!form.name) form.name = files[0].name.split('.').slice(0, -1).join('.');
    }
};

const addLocation = () => {
    form.locations.push({ airport_id: '', airlines: [''] });
};

const removeLocation = (index) => {
    form.locations.splice(index, 1);
};

const addAirline = (locIndex) => {
    form.locations[locIndex].airlines.push('');
};

const removeAirline = (locIndex, airlineIndex) => {
    form.locations[locIndex].airlines.splice(airlineIndex, 1);
};

const submit = () => {
    form.post(route('supervision-reports.store'), {
        onSuccess: () => {
            showUploadModal.value = false;
            form.reset();
        },
    });
};

const filteredReports = computed(() => {
    let result = props.reports;

    // Search
    if (searchQuery.value) {
        const lower = searchQuery.value.toLowerCase();
        result = result.filter(r => r.name.toLowerCase().includes(lower));
    }

    // Airport Filter
    if (selectedAirport.value !== 'All') {
        result = result.filter(r => 
            r.locations.some(loc => loc.airport_id === selectedAirport.value)
        );
    }

    // Airline Filter
    if (selectedAirline.value !== 'All') {
        result = result.filter(r => 
            r.locations.some(loc => 
                loc.airlines.some(airline => airline.id === selectedAirline.value)
            )
        );
    }

    // Year Filter
    if (selectedYear.value !== 'All') {
        result = result.filter(r => {
             // Extract year from start_date (YYYY-MM-DD or similar)
            return r.start_date.startsWith(selectedYear.value);
        });
    }

    return result;
});

// Extract unique years from reports for the dropdown, ensuring 2025 is the starting point
const availableYears = computed(() => {
    const years = new Set();
    
    // Add years from reports
    props.reports.forEach(r => {
        const y = parseInt(r.start_date.substring(0, 4));
        if (!isNaN(y)) years.add(y);
    });

    // Ensure 2025 to Current Year are always available
    const startYear = 2025;
    const currentYear = new Date().getFullYear();
    
    for (let y = startYear; y <= currentYear; y++) {
        years.add(y);
    }

    return Array.from(years).sort().reverse();
});

// --- Action Methods ---

const getFileIcon = (filename) => {
    if (!filename) return 'default';
    const ext = filename.split('.').pop().toLowerCase();
    if (['pdf'].includes(ext)) return 'pdf';
    if (['doc', 'docx'].includes(ext)) return 'word';
    if (['xls', 'xlsx', 'csv'].includes(ext)) return 'excel';
    if (['ppt', 'pptx'].includes(ext)) return 'ppt';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'image';
    if (['zip', 'rar'].includes(ext)) return 'zip';
    return 'default';
};

const deleteFile = (report) => {
    if (confirm('Are you sure you want to delete this report?')) {
        useForm({}).delete(route('supervision-reports.destroy', report.id));
    }
};

const triggerDownload = (report) => {
    if (report.is_secure) {
        selectedFileToDownload.value = report;
        downloadPassword.value = '';
        actionType.value = 'download';
        showDownloadModal.value = true;
    } else {
        postDownload(route('supervision-reports.download', report.id), {});
    }
};

const submitPasswordAction = async () => {
    if (!selectedFileToDownload.value) return;

    if (actionType.value === 'download') {
        postDownload(route('supervision-reports.download', selectedFileToDownload.value.id), {
            password: downloadPassword.value
        });
        showDownloadModal.value = false;
        downloadPassword.value = '';
        selectedFileToDownload.value = null;
    } else if (actionType.value === 'preview') {
        try {
            await axios.post(route('supervision-reports.verify-password', selectedFileToDownload.value.id), {
                password: downloadPassword.value
            });
            // Password correct
            const report = selectedFileToDownload.value;
            showDownloadModal.value = false;
            downloadPassword.value = '';
            selectedFileToDownload.value = null;
            
            // Proceed to preview
            loadPreview(report);

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

// Preview Logic
const previewType = computed(() => {
    if (!selectedFileToPreview.value) return null;
    const path = selectedFileToPreview.value.file_path; 
    const ext = path.split('.').pop().toLowerCase();
    
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'image';
    if (['pdf'].includes(ext)) return 'pdf';
    if (['doc', 'docx'].includes(ext)) return 'word';
    if (['xls', 'xlsx', 'csv'].includes(ext)) return 'excel';
    if (['ppt', 'pptx'].includes(ext)) return 'ppt';
    return 'other';
});

const openPreview = (report) => {
    if (report.is_secure) {
        selectedFileToDownload.value = report; // Reusing this ref for the modal context
        downloadPassword.value = '';
        actionType.value = 'preview';
        showDownloadModal.value = true;
    } else {
        loadPreview(report);
    }
};

const loadPreview = async (report) => {
    selectedFileToPreview.value = report;
    zoomLevel.value = 1;
    showPreviewModal.value = true;
    officeError.value = null;

    if (previewType.value === 'word' || previewType.value === 'excel') {
        isRenderingOffice.value = true;
        try {
            const response = await fetch(report.file_path);
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

const zoomIn = () => { if (zoomLevel.value < 3) zoomLevel.value += 0.25; };
const zoomOut = () => { if (zoomLevel.value > 0.5) zoomLevel.value -= 0.25; };
</script>

<template>
    <Head title="Laporan Hasil Pengawasan" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-gray-50 pb-20">
            <!-- Header (Glossy Glass Theme) -->
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-blue-800 to-sky-500 bg-[length:300%_300%] animate-gradient-flow px-6 py-10 shadow-lg border-b border-white/10 pb-24">
                 <!-- Dot Pattern -->
                <div class="absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.15)_1px,transparent_1px)] [background-size:20px_20px]"></div>

                <!-- Glossy Effects -->
                <div class="absolute inset-0 bg-white/5 backdrop-blur-[1px]"></div>
                <div class="absolute -top-20 -right-20 h-80 w-80 rounded-full bg-sky-400/20 blur-3xl mix-blend-overlay"></div>
                <div class="absolute -bottom-20 -left-20 h-80 w-80 rounded-full bg-blue-900/40 blur-3xl"></div>

                 <div class="max-w-7xl mx-auto relative z-10">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                        <div>
                            <h1 class="text-4xl font-extrabold text-white tracking-tight drop-shadow-md">Page File</h1>
                            <p class="text-blue-100 text-sm mt-1 font-medium opacity-90">Manage and organize your documents.</p>
                        </div>
                        <div class="flex gap-3">
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
                        
                        <!-- Year Filter -->
                        <div class="relative w-full md:w-32">
                             <select v-model="selectedYear" class="w-full appearance-none rounded-2xl border border-white/20 bg-white/10 backdrop-blur-md py-4 pl-4 pr-10 text-white shadow-xl focus:border-white/40 focus:bg-white/20 focus:ring-0 focus:outline-none transition-all cursor-pointer">
                                <option class="text-gray-900" value="All">All Years</option>
                                <option v-for="year in availableYears" :key="year" :value="year" class="text-gray-900">{{ year }}</option>
                            </select>
                             <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-blue-200">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </div>

                         <!-- Filter Dropdown (Glassy) -->
                        <!-- Filter Dropdowns Removed -->
                    </div>

                    <!-- Category Title / Back Button -->
                    <div class="mt-8 flex items-center gap-4 animate-fade-in-up">
                        <Link :href="route('files.index')" class="p-2 bg-white/10 hover:bg-white/20 rounded-full text-white transition-colors backdrop-blur-md">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        </Link>
                        <div>
                            <h2 class="text-3xl font-bold text-white tracking-tight">Laporan Hasil Pengawasan</h2>
                            <p class="text-blue-100 text-sm font-medium opacity-80">Manage your laporan hasil pengawasan files.</p>
                        </div>
                    </div>
                 </div>
            </div>

            <div class="max-w-7xl mx-auto px-6 -mt-16 relative z-20 space-y-6">
                <!-- Charts Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                            <h3 class="font-bold text-gray-700 flex items-center gap-2">
                                <span class="w-1 h-5 bg-red-400 rounded-full"></span>
                                Frekuensi Pengawasan Bandara
                            </h3>
                            <div class="relative w-full sm:w-40">
                                <select v-model="selectedAirport" class="w-full appearance-none rounded-lg border border-gray-200 bg-gray-50 py-1.5 pl-3 pr-8 text-xs font-medium text-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none cursor-pointer">
                                    <option value="All">All Airports</option>
                                    <option v-for="airport in airports" :key="airport.id" :value="airport.id">{{ airport.name }}</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </div>
                        </div>
                        <div class="h-64">
                             <apexchart :options="airportChartOptions" :series="airportSeries" height="100%" />
                        </div>
                    </div>
                     <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                            <h3 class="font-bold text-gray-700 flex items-center gap-2">
                                <span class="w-1 h-5 bg-blue-400 rounded-full"></span>
                                Frekuensi Maskapai
                            </h3>
                             <div class="relative w-full sm:w-40">
                                <select v-model="selectedAirline" class="w-full appearance-none rounded-lg border border-gray-200 bg-gray-50 py-1.5 pl-3 pr-8 text-xs font-medium text-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none cursor-pointer">
                                    <option value="All">All Airlines</option>
                                    <option v-for="airline in airlines" :key="airline.id" :value="airline.id">{{ airline.name }}</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </div>
                        </div>
                         <div class="h-64">
                             <apexchart :options="airlineChartOptions" :series="airlineSeries" height="100%" />
                        </div>
                    </div>
                </div>

                <!-- Table Section -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                     <div class="p-4 border-b border-gray-100 flex items-center gap-3">
                        <span class="w-1 h-5 bg-orange-400 rounded-full"></span>
                        <h3 class="font-bold text-gray-800 uppercase text-sm tracking-wide">Laporan Hasil Pengawasan</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600">
                            <thead class="bg-gray-50 text-xs uppercase font-semibold text-gray-500">
                                <tr>
                                    <th class="px-6 py-4 w-[150px]">Name</th>
                                    <th class="px-6 py-4 w-[140px]">Tanggal Pengawasan</th>
                                    <th class="px-6 py-4 w-auto">Lokasi & Maskapai</th>
                                    <th class="px-6 py-4 w-[100px]">Size</th>
                                    <th class="px-6 py-4 w-[140px]">Modified</th>
                                    <th class="px-6 py-4 text-right w-[100px]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="report in filteredReports" :key="report.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-shrink-0 h-8 w-8 rounded bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-xs uppercase relative">
                                                {{ getFileIcon(report.file_path).substring(0,3) }}
                                                <span v-if="report.is_secure" class="absolute -top-1 -right-1 bg-white rounded-full p-0.5 shadow-sm">
                                                    <svg class="w-3 h-3 text-orange-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                                                </span>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="truncate max-w-[100px] block" :title="report.name">{{ report.name }}</span>
                                                <span v-if="report.is_secure" class="text-[10px] text-orange-500 font-bold flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                    SECURE
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-mono">
                                        {{ report.start_date }} - {{ report.end_date }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-4">
                                            <div v-for="(loc, idx) in report.locations" :key="idx" class="text-xs">
                                                <div class="font-bold text-gray-700 mb-1.5">{{ loc.airport }}</div>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <span v-for="(airline, aIdx) in loc.airlines" :key="aIdx" class="px-2 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-600 border border-blue-100">
                                                        {{ airline.name }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-xs">{{ report.file_size }}</td>
                                    <td class="px-6 py-4 text-xs">{{ report.modified }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end gap-2 items-center">
                                            <button @click="openPreview(report)" class="text-green-600 hover:text-green-900 p-1.5 hover:bg-green-50 rounded-full transition-colors" title="Preview">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            </button>
                                            <button @click="triggerDownload(report)" class="text-blue-600 hover:text-blue-900 p-1.5 hover:bg-blue-50 rounded-full transition-colors" title="Download">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                            </button>
                                            <button 
                                                v-if="$page.props.auth.user"
                                                @click="deleteFile(report)"
                                                class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-full transition-colors"
                                                title="Delete"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredReports.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-400 italic">No reports found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Upload Modal -->
        <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh]">
                <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-bold text-lg text-gray-800">Upload File</h3>
                    <button @click="showUploadModal = false; form.reset()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar">
                    <form @submit.prevent="submit" class="space-y-5">
                        
                        <!-- Drag Drop -->
                        <div 
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleFileDrop"
                            :class="[
                                'border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors relative group',
                                isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:bg-gray-50'
                            ]"
                        >
                            <input type="file" @change="handleFileInput" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" id="fileInput">
                            <div class="flex flex-col items-center pointer-events-none">
                                <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                                </div>
                                <span v-if="!form.file" class="text-sm font-medium text-gray-600">Click to browse or drag file here</span>
                                <span v-else class="text-sm font-bold text-blue-600 truncate max-w-[200px]">{{ form.file.name }}</span>
                                <span class="text-xs text-gray-400 mt-1">Supported files: PDF, DOC, ZIP, APK, Images</span>
                            </div>
                        </div>

                        <!-- Basic Info -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama File</label>
                            <input v-model="form.name" type="text" placeholder="File name" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tanggal Mulai</label>
                                <input v-model="form.start_date" type="date" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tanggal Selesai</label>
                                <input v-model="form.end_date" type="date" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                        </div>

                        <!-- Dynamic Locations -->
                        <div class="space-y-4">
                            <div class="flex justify-between items-end border-b border-gray-100 pb-1">
                                <label class="block text-xs font-bold text-gray-500 uppercase">Lokasi Pengawasan</label>
                                <button type="button" @click="addLocation" class="text-xs font-bold text-blue-600 hover:underline">+ Tambah Lokasi</button>
                            </div>

                            <div v-for="(loc, index) in form.locations" :key="index" class="bg-gray-50 p-4 rounded-xl border border-gray-200 relative group">
                                <button v-if="form.locations.length > 1" type="button" @click="removeLocation(index)" class="absolute top-2 right-2 text-red-400 hover:text-red-600 p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                                
                                <div class="mb-3">
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Lokasi {{ index + 1 }} - Bandara</label>
                                    <select v-model="loc.airport_id" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                                        <option value="" disabled>Select Airport</option>
                                        <option v-for="airport in airports" :key="airport.id" :value="airport.id">{{ airport.name }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Maskapai</label>
                                    <div class="space-y-2">
                                        <div v-for="(airlineId, aIdx) in loc.airlines" :key="aIdx" class="flex gap-2">
                                            <select v-model="loc.airlines[aIdx]" class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white">
                                                <option value="" disabled>Select Airline</option>
                                                <option v-for="airline in airlines" :key="airline.id" :value="airline.id">{{ airline.name }}</option>
                                            </select>
                                            <button v-if="loc.airlines.length > 1" type="button" @click="removeAirline(index, aIdx)" class="text-red-400 hover:text-red-600 px-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <button type="button" @click="addAirline(index)" class="mt-2 text-xs font-bold text-blue-600 hover:underline inline-flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                        Tambah Maskapai
                                    </button>
                                </div>
                            </div>
                        </div>

                         <!-- Secure -->
                         <div class="pt-2 border-t mt-2">
                            <div class="flex items-center gap-2 mb-2">
                                <input type="checkbox" v-model="form.is_secure" id="secureCheck" class="rounded text-blue-600 focus:ring-blue-500 border-gray-300">
                                <label for="secureCheck" class="text-sm font-bold text-gray-700 flex items-center gap-1">
                                    <svg class="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                    Secure File
                                </label>
                            </div>
                            <div v-if="form.is_secure">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Set Password</label>
                                <input v-model="form.password" type="text" placeholder="Enter password" class="w-full rounded-lg border-orange-300 focus:ring-orange-500 focus:border-orange-500 bg-orange-50 text-sm" required>
                                <p class="text-[10px] text-gray-500 mt-1">Users must enter this password to download or view the file.</p>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="p-5 border-t border-gray-100 flex gap-3">
                    <button @click="showUploadModal = false; form.reset()" class="flex-1 py-2.5 bg-white border border-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors">Cancel</button>
                    <button @click="submit" :disabled="form.processing" class="flex-1 py-2.5 bg-blue-500/90 hover:bg-blue-600 text-white font-bold rounded-xl shadow-lg shadow-blue-500/30 transition-all disabled:opacity-50">Upload</button>
                </div>
            </div>
        </div>


        <!-- Download Password Modal -->
        <div v-if="showDownloadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
             <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 mb-4">
                    <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold mb-2">Restricted Access</h3>
                <p class="text-sm text-gray-500 mb-4">Enter the password to unlock <strong>{{ selectedFileToDownload?.name }}</strong>.</p>
                
                <form @submit.prevent="submitPasswordAction">
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

        <!-- Preview Modal -->
        <div v-if="showPreviewModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm animate-fade-in">
            <div class="bg-gray-900 rounded-xl shadow-2xl w-full max-w-6xl h-[85vh] flex flex-col overflow-hidden relative">
                <!-- Header/Close -->
                <div class="flex items-center justify-between px-4 py-3 bg-white/5 border-b border-white/10">
                    <h3 class="text-white font-medium truncate max-w-md">{{ selectedFileToPreview?.name }}</h3>
                    <div class="flex items-center gap-3">
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
                            :src="selectedFileToPreview.file_path" 
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
                        :src="selectedFileToPreview.file_path" 
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
    background: white !important;
    box-shadow: none !important;
    margin: 0 !important;
    padding: 0 !important;
}
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f5f9; 
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1; 
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8; 
}
</style>
