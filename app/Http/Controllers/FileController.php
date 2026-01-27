<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Hash;
use App\Models\SupervisionReport;

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

        $category = $request->query('category');

        // Fetch Files
        $filesQuery = File::orderBy('created_at', 'desc');
        if ($category) {
            $filesQuery->where('category', $category);
        }
        $files = $filesQuery->get()->map(function ($file) {
            $file->source = 'file';
            return $file;
        });

        // Fetch Supervision Reports (only if category matches or no category selected)
        $reports = collect([]);
        if (!$category || $category === 'Laporan Hasil Pengawasan') {
            $reports = SupervisionReport::orderBy('created_at', 'desc')->get()->map(function ($report) {
                return (object) [
                    'id' => $report->id,
                    'display_name' => $report->name,
                    'category' => 'Laporan Hasil Pengawasan',
                    'description' => "Pengawasan: " . ($report->start_date ? $report->start_date->format('d M Y') : '-') . " - " . ($report->end_date ? $report->end_date->format('d M Y') : '-'),
                    'file_path' => $report->file_path,
                    'size' => $report->file_size,
                    'is_secure' => $report->is_secure,
                    'password' => $report->password,
                    'created_at' => $report->created_at,
                    'updated_at' => $report->updated_at,
                    'source' => 'supervision_report',
                ];
            });
        }

        // Merge and Sort
        $allFiles = $files->concat($reports)->sortByDesc('created_at')->values();

        // Calculate Access stats (approximate)
        $totalSize = File::sum('size') + SupervisionReport::sum('file_size');

        return Inertia::render('Files/Index', [
            'files' => $allFiles,
            'recentFiles' => $allFiles->take(5), // Use the merged list for recent
            'totalStorageSize' => $totalSize,
            'currentCategory' => $category,
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
    public function verifyPassword(Request $request, File $file)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (!$file->is_secure) {
            return response()->json(['valid' => true]);
        }

        if (Hash::check($request->password, $file->password)) {
            return response()->json(['valid' => true]);
        }

        return response()->json(['valid' => false], 403);
    }
}
