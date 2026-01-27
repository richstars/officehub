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
            'email' => 'nullable|email|max:255',
            'department' => 'nullable|string|max:255',
            'employee_id' => 'nullable|string|max:50',
            'bio' => 'nullable|string',
            'certifications' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos', 'public');
            $validated['photo_path'] = $path;
        }

        Contact::create($validated);

        return back()->with('success', 'Contact added successfully!');
    }

    public function update(Request $request, Contact $contact)
    {
        if ($this->checkReadOnly($request->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'department' => 'nullable|string|max:255',
            'employee_id' => 'nullable|string|max:50',
            'bio' => 'nullable|string',
            'certifications' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('photo')) {
             // Delete old photo if it exists
            if ($contact->photo_path && \Storage::disk('public')->exists($contact->photo_path)) {
                \Storage::disk('public')->delete($contact->photo_path);
            }
            $path = $request->file('photo')->store('photos', 'public');
            $validated['photo_path'] = $path;
        }

        $contact->update($validated);

        return back()->with('success', 'Contact updated successfully!');
    }

    public function destroy(Contact $contact)
    {
        if ($this->checkReadOnly(auth()->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        if ($contact->photo_path && \Storage::disk('public')->exists($contact->photo_path)) {
            \Storage::disk('public')->delete($contact->photo_path);
        }

        $contact->delete();
        return back()->with('success', 'Contact deleted successfully!');
    }
}
