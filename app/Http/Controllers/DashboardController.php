<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Link;
use App\Models\Contact;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    private function checkReadOnly($user)
    {
        if ($user->role === 'superadmin') return false;
        $perms = $user->permissions ?? [];
        return $perms['read_only'] ?? false;
    }

    public function index()
    {
        return Inertia::render('Dashboard', [
            'links' => Link::orderBy('created_at', 'desc')->get(),
            'files' => File::orderBy('created_at', 'desc')->get(),
            'recentFiles' => File::orderBy('created_at', 'desc')->take(5)->get(),
            'announcement' => Announcement::where('is_active', true)->latest()->first(),
            'totalStorageSize' => File::sum('size'),
        ]);
    }

    public function storeLink(Request $request)
    {
        if ($this->checkReadOnly($request->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url',
            'category' => 'required|string|max:255',
            'icon' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        Link::create($validated);

        return back()->with('success', 'Tool added successfully!');
    }

    public function updateLink(Request $request, Link $link)
    {
        if ($this->checkReadOnly($request->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url',
            'category' => 'required|string|max:255',
            'icon' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $link->update($validated);

        return back()->with('success', 'Tool updated successfully!');
    }

    public function destroyLink(Link $link)
    {
        if ($this->checkReadOnly(auth()->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        $link->delete();
        return back()->with('success', 'Tool deleted successfully!');
    }

    public function storeFile(Request $request)
    {
        if ($this->checkReadOnly($request->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        $request->validate([
            'display_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file', // Removed size limit
        ]);

        $path = $request->file('file')->store('files', 'public');

        File::create([
            'display_name' => $request->display_name,
            'category' => $request->category,
            'description' => $request->description,
            'file_path' => $path,
            'size' => $request->file('file')->getSize(),
        ]);

        return back()->with('success', 'File uploaded successfully!');
    }

    public function destroyFile(File $file)
    {
        if ($this->checkReadOnly(auth()->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        if (\Storage::disk('public')->exists($file->file_path)) {
            \Storage::disk('public')->delete($file->file_path);
        }
        $file->delete();
        return back()->with('success', 'File deleted successfully!');
    }

    public function storeAnnouncement(Request $request)
    {
        if ($this->checkReadOnly($request->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        Announcement::create($validated);
        return back()->with('success', 'Announcement updated!');
    }

    public function destroyAnnouncement(Announcement $announcement)
    {
        if ($this->checkReadOnly(auth()->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        $announcement->delete();
        return back()->with('success', 'Announcement deleted!');
    }
}
