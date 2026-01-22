<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContactController extends Controller
{
    private function checkAccess($user)
    {
        // Superadmin bypasses checks
        if ($user->role === 'superadmin') return true;
        
        $perms = $user->permissions ?? [];
        // Default to true if not set
        return $perms['access_directory'] ?? true;
    }

    private function checkReadOnly($user)
    {
        // Superadmin bypasses checks
        if ($user->role === 'superadmin') return false;

        $perms = $user->permissions ?? [];
        return $perms['read_only'] ?? false;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$this->checkAccess($user)) {
             return redirect()->route('dashboard')->with('restriction', 'You do not have permission to access the Directory.');
        }

        return Inertia::render('Contacts/Index', [
            'contacts' => Contact::orderBy('name', 'asc')->get()
        ]);
    }

    public function store(Request $request)
    {
        if ($this->checkReadOnly($request->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        Contact::create($validated);

        return back()->with('success', 'Contact added successfully!');
    }

    public function destroy(Contact $contact)
    {
        if ($this->checkReadOnly(auth()->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        $contact->delete();
        return back()->with('success', 'Contact deleted successfully!');
    }
}
