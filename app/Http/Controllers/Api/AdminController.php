<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EscrowTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Get all escrow transactions for admin dashboard (Rekber Pusat)
     */
    public function escrowTransactions(Request $request)
    {
        // Ensure admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transactions = EscrowTransaction::with(['adoption.adopter', 'adoption.cat.shelter'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate stats
        $stats = [
            'total' => $transactions->count(),
            'totalAmount' => $transactions->sum('amount'),
            'pending' => $transactions->where('payment_status', 'pending')->count(),
            'paid' => $transactions->where('payment_status', 'paid')->count(),
            'released' => $transactions->where('payment_status', 'released')->count(),
            'failed' => $transactions->where('payment_status', 'failed')->count(),
            'refunded' => $transactions->where('payment_status', 'refunded')->count(),
            'expired' => $transactions->where('payment_status', 'expired')->count(),
            'cancelled' => $transactions->where('payment_status', 'cancelled')->count(),
        ];

        return response()->json([
            'transactions' => $transactions,
            'stats' => $stats
        ]);
    }
}
