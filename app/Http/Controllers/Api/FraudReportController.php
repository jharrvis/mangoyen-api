<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FraudReport;
use Illuminate\Http\Request;

class FraudReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reporter_name' => 'required_without:reporter_id|string|max:255',
            'reporter_phone' => 'required_without:reporter_id|string|max:20',
            'perpetrator_name' => 'required|string|max:255',
            'description' => 'required|string',
            'evidence' => 'nullable|file|max:5120', // 5MB max
        ]);

        $evidencePath = null;
        if ($request->hasFile('evidence')) {
            $evidencePath = $request->file('evidence')->store('fraud-evidence', 'public');
        }

        $report = FraudReport::create([
            'reporter_id' => $request->user()?->id,
            'reporter_name' => $validated['reporter_name'] ?? $request->user()?->name,
            'reporter_phone' => $validated['reporter_phone'] ?? $request->user()?->phone,
            'perpetrator_name' => $validated['perpetrator_name'],
            'description' => $validated['description'],
            'evidence_path' => $evidencePath,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Laporan berhasil dikirim! Tim kami akan segera menindaklanjuti. 🛡️',
            'report' => $report,
        ], 201);
    }
}
