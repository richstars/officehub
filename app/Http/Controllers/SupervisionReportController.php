<?php

namespace App\Http\Controllers;

use App\Models\SupervisionReport;
use App\Models\Airport;
use App\Models\Airline;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SupervisionReportController extends Controller
{
    public function verifyPassword(Request $request, SupervisionReport $report)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (!$report->is_secure) {
            return response()->json(['valid' => true]);
        }

        if (password_verify($request->password, $report->password)) {
            return response()->json(['valid' => true]);
        }

        return response()->json(['valid' => false], 403);
    }
    public function index()
    {
        $reports = SupervisionReport::with(['details.airport', 'details.airline', 'user'])
            ->latest()
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'name' => $report->name,
                    'file_path' => Storage::url($report->file_path),
                    'file_size' => $this->formatSize($report->file_size),
                    'start_date' => $report->start_date->format('Y-m-d'),
                    'end_date' => $report->end_date->format('Y-m-d'),
                    'is_secure' => $report->is_secure,
                    'locations' => $report->details->groupBy('airport_id')->map(function ($details) {
                        return [
                            'airport' => $details->first()->airport->name,
                            'airport_id' => $details->first()->airport_id,
                            'airlines' => $details->map(function ($detail) {
                                return [
                                    'id' => $detail->airline->id,
                                    'name' => $detail->airline->name,
                                ];
                            })->values()->all(),
                        ];
                    })->values()->all(),
                    'modified' => $report->updated_at->format('d M Y, H:i'),
                ];
            });

        // Chart Data Calculation
        // Airport Frequency
        // Chart Data Calculation - Fetch ALL to show zeros
        
        // 1. Airport Stats
        $airportStats = Airport::all()->map(function ($airport) {
             return [
                'name' => $airport->name,
                // Count how many details reference this airport
                'count' => DB::table('supervision_report_details')->where('airport_id', $airport->id)->count()
             ];
        });

        // 2. Airline Stats
        $airlineStats = Airline::all()->map(function ($airline) {
             return [
                'name' => $airline->name,
                'color' => $airline->color,
                // Count how many details reference this airline
                'count' => DB::table('supervision_report_details')->where('airline_id', $airline->id)->count()
             ];
        });

        return Inertia::render('SupervisionReports/Index', [
            'reports' => $reports,
            'airportStats' => $airportStats,
            'airlineStats' => $airlineStats,
            'airports' => Airport::all(),
            'airlines' => Airline::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'required|file|max:10240', // 10MB
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_secure' => 'boolean',
            'locations' => 'required|array|min:1',
            'locations.*.airport_id' => 'required|exists:airports,id',
            'locations.*.airlines' => 'required|array|min:1',
            'locations.*.airlines.*' => 'exists:airlines,id',
        ]);

        DB::transaction(function () use ($request, $validated) {
            $path = $request->file('file')->store('supervision_reports', 'public');

            $report = SupervisionReport::create([
                'name' => $validated['name'],
                'file_path' => $path,
                'file_size' => $request->file('file')->getSize(),
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_secure' => $request->boolean('is_secure'),
                'password' => $request->boolean('is_secure') && $request->filled('password') ? bcrypt($request->password) : null,
                'user_id' => auth()->id(),
            ]);

            foreach ($validated['locations'] as $location) {
                foreach ($location['airlines'] as $airlineId) {
                    $report->details()->create([
                        'airport_id' => $location['airport_id'],
                        'airline_id' => $airlineId,
                    ]);
                }
            }
        });

        return redirect()->back();
    }

    public function download(Request $request, SupervisionReport $report)
    {
        if ($report->is_secure) {
            if (!$request->filled('password') || !password_verify($request->password, $report->password)) {
                return back()->withErrors(['password' => 'Incorrect password provided.']);
            }
        }

        return Storage::download($report->file_path, $report->name . '.' . pathinfo($report->file_path, PATHINFO_EXTENSION));
    }

    public function destroy(SupervisionReport $report)
    {
        if (Storage::exists($report->file_path)) {
            Storage::delete($report->file_path);
        }
        $report->delete();

        return redirect()->back();
    }
    
    private function formatSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return $bytes . ' byte';
        } else {
            return '0 bytes';
        }
    }
}
