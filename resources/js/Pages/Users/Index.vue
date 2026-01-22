<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: Array,
});

const showAddModal = ref(false);
const showEditModal = ref(false);
const editingUser = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'admin',
    permissions: {
        read_only: false,
        access_directory: true,
        access_files: true,
    },
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'admin',
    permissions: {
        read_only: false,
        access_directory: true,
        access_files: true,
    },
});

const submitAdd = () => {
    form.post(route('users.store'), {
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
};

const openEdit = (user) => {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = user.role;
    editForm.password = ''; // Clear password field
    
    // Set permissions, default if null
    const perms = user.permissions || {};
    editForm.permissions.read_only = perms.read_only ?? false;
    editForm.permissions.access_directory = perms.access_directory ?? true;
    editForm.permissions.access_files = perms.access_files ?? true;

    showEditModal.value = true;
};
// ... rest of script ...


const submitEdit = () => {
    editForm.put(route('users.update', editingUser.value.id), {
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
        },
    });
};

const deleteUser = (user) => {
    if (confirm(`Are you sure you want to delete ${user.name}?`)) {
        useForm({}).delete(route('users.destroy', user.id));
    }
};
</script>

<template>
    <Head title="User Management" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-gray-50 pb-20">
             <!-- Header Area -->
            <div class="bg-indigo-600 px-4 py-8 shadow-sm">
                <div class="mx-auto max-w-7xl">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-white">User Management</h1>
                            <p class="text-indigo-200 text-sm">Manage systems accounts and permissions.</p>
                        </div>
                        <button 
                            @click="showAddModal = true"
                            class="bg-indigo-500 hover:bg-indigo-400 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        >
                            + Add New User
                        </button>
                    </div>
                </div>
            </div>

            <div class="mx-auto max-w-7xl px-4 mt-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-xs mr-3">
                                            {{ user.name.charAt(0) }}
                                        </div>
                                        <div class="text-sm font-bold text-gray-900">{{ user.name }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ user.email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                        user.role === 'superadmin' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'
                                    ]">
                                        {{ user.role }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <button @click="openEdit(user)" class="text-indigo-600 hover:text-indigo-900 font-bold mr-4">Edit</button>
                                    <button 
                                        v-if="user.id !== $page.props.auth.user.id"
                                        @click="deleteUser(user)" 
                                        class="text-red-500 hover:text-red-700 font-bold"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add User Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="w-full max-w-md bg-white rounded-xl shadow-xl p-6">
                <h2 class="text-lg font-bold mb-4 text-gray-900">Add New User</h2>
                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Full Name</label>
                        <input v-model="form.name" type="text" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Email Address</label>
                        <input v-model="form.email" type="email" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Password</label>
                        <input v-model="form.password" type="password" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Role</label>
                        <select v-model="form.role" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="admin">Admin (Author)</option>
                            <option value="superadmin">Superadmin (User Manager)</option>
                        </select>
                    </div>

                    <div v-if="form.role === 'admin'" class="space-y-3 pt-2">
                        <label class="block text-xs font-bold text-gray-500 uppercase border-b pb-1">Permissions</label>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.permissions.read_only" id="add_read_only" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <label for="add_read_only" class="text-sm text-gray-700">Read Only Mode (Cannot Create/Edit/Delete)</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.permissions.access_directory" id="add_access_directory" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <label for="add_access_directory" class="text-sm text-gray-700">Access Directory</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.permissions.access_files" id="add_access_files" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <label for="add_access_files" class="text-sm text-gray-700">Access File Repository</label>
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showAddModal = false" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="flex-1 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="w-full max-w-md bg-white rounded-xl shadow-xl p-6">
                <h2 class="text-lg font-bold mb-4 text-gray-900">Edit User</h2>
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Full Name</label>
                        <input v-model="editForm.name" type="text" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Email Address</label>
                        <input v-model="editForm.email" type="email" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">New Password (Optional)</label>
                        <input v-model="editForm.password" type="password" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Leave blank to keep current">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase">Role</label>
                        <select v-model="editForm.role" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="admin">Admin (Author)</option>
                            <option value="superadmin">Superadmin (User Manager)</option>
                        </select>
                    </div>

                    <div v-if="editForm.role === 'admin'" class="space-y-3 pt-2">
                        <label class="block text-xs font-bold text-gray-500 uppercase border-b pb-1">Permissions</label>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="editForm.permissions.read_only" id="edit_read_only" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <label for="edit_read_only" class="text-sm text-gray-700">Read Only Mode (Cannot Create/Edit/Delete)</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="editForm.permissions.access_directory" id="edit_access_directory" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <label for="edit_access_directory" class="text-sm text-gray-700">Access Directory</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="editForm.permissions.access_files" id="edit_access_files" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <label for="edit_access_files" class="text-sm text-gray-700">Access File Repository</label>
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showEditModal = false" class="flex-1 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</button>
                        <button type="submit" :disabled="editForm.processing" class="flex-1 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
