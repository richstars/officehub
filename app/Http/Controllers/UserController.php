<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::orderBy('name', 'asc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:admin,superadmin',
            'permissions' => 'nullable|array',
            'permissions.read_only' => 'boolean',
            'permissions.access_directory' => 'boolean',
            'permissions.access_files' => 'boolean',
        ]);

        $defaultPermissions = [
            'read_only' => false,
            'access_directory' => true,
            'access_files' => true,
        ];

        $permissions = array_merge($defaultPermissions, $validated['permissions'] ?? []);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'permissions' => $permissions,
        ]);

        return back()->with('success', 'User created successfully!');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|in:admin,superadmin',
            'permissions' => 'nullable|array',
            'permissions.read_only' => 'boolean',
            'permissions.access_directory' => 'boolean',
            'permissions.access_files' => 'boolean',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $defaultPermissions = [
            'read_only' => false,
            'access_directory' => true,
            'access_files' => true,
        ];
        
        $user->permissions = array_merge($defaultPermissions, $validated['permissions'] ?? []);
        $user->save();

        return back()->with('success', 'User updated successfully!');
    }

    public function destroy(User $user)
    {
        // Prevent superadmin from deleting themselves
        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot delete your own account!');
        }

        $user->delete();
        return back()->with('success', 'User deleted successfully!');
    }
}
