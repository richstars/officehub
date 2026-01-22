<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Hash;

class FileController extends Controller
{
    // ... (checkAccess and checkReadOnly methods remain same) ...
    private function checkAccess($user)
    {
        if ($user->role === 'superadmin') return true;
        $perms = $user->permissions ?? [];
        return $perms['access_files'] ?? true;
    }

    private function checkReadOnly($user)
    {
        if ($user->role === 'superadmin') return false;
        $perms = $user->permissions ?? [];
        return $perms['read_only'] ?? false;
    }

    public function index(Request $request)
    {
        if (!$this->checkAccess($request->user())) {
             return redirect()->route('dashboard')->with('restriction', 'You do not have permission to access the File Repository.');
        }

        return Inertia::render('Files/Index', [
            'files' => File::orderBy('created_at', 'desc')->get(),
            'recentFiles' => File::orderBy('created_at', 'desc')->take(5)->get(),
            'totalStorageSize' => File::sum('size'),
        ]);
    }

    public function store(Request $request)
    {
        if ($this->checkReadOnly($request->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        $request->validate([
            'display_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file', 
            'is_secure' => 'nullable|boolean',
            'password' => 'nullable|required_if:is_secure,true|string|min:4',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        // Sanitize filename but keep extension
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Add timestamp to ensure uniqueness but keep original name recognizable
        $storedFileName = time() . '_' . $safeName . '.' . $extension;
        
        $path = $file->storeAs('files', $storedFileName, 'public');

        File::create([
            'display_name' => $request->display_name,
            'category' => $request->category,
            'description' => $request->description,
            'file_path' => $path,
            'size' => $file->getSize(),
            'is_secure' => $request->boolean('is_secure'),
            'password' => $request->boolean('is_secure') ? Hash::make($request->password) : null,
        ]);

        return back()->with('success', 'File uploaded successfully!');
    }

    public function download(Request $request, File $file)
    {
        if (!$this->checkAccess($request->user())) {
             return redirect()->route('dashboard');
        }

        if ($file->is_secure) {
            if (!$request->password || !Hash::check($request->password, $file->password)) {
                 return back()->with('error', 'Incorrect password for secure file.');
            }
        }

        if (Storage::disk('public')->exists($file->file_path)) {
            // Get the actual filename from storage path
            $storageName = basename($file->file_path);
            
            // Remove the timestamp prefix we added during upload to get back the "original-like" name
            // Pattern: numbers_ followed by rest.
            $downloadName = preg_replace('/^\d+_/', '', $storageName);
            
            // Fallback if regex fails or something weird happens (shouldn't happen)
            if (empty($downloadName)) {
                $downloadName = $storageName;
            }

            return Storage::disk('public')->download($file->file_path, $downloadName);
        }
        
        return back()->with('error', 'File not found.');
    }

    public function destroy(File $file)
    {
        if ($this->checkReadOnly(auth()->user())) {
             return back()->with('restriction', 'Action restricted: You are in Read-Only mode.');
        }

        if (Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }
        $file->delete();
        return back()->with('success', 'File deleted successfully!');
    }
}
