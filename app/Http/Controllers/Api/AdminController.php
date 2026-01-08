<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EscrowTransaction;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $transactions = EscrowTransaction::with(['adoption.adopter', 'adoption.cat.shelter.user'])
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

    /**
     * Get single transaction detail
     */
    public function showTransaction(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaction = EscrowTransaction::with([
            'adoption.adopter',
            'adoption.cat.shelter.user',
            'adoption.cat.photos'
        ])->findOrFail($id);

        return response()->json(['transaction' => $transaction]);
    }

    /**
     * Check Midtrans transaction status
     */
    public function checkMidtransStatus(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaction = EscrowTransaction::findOrFail($id);

        if (!$transaction->midtrans_order_id) {
            return response()->json([
                'message' => 'No Midtrans order ID found'
            ], 400);
        }

        try {
            $midtransService = new MidtransService();
            $status = $midtransService->getTransactionStatus($transaction->midtrans_order_id);

            return response()->json([
                'midtrans_status' => $status,
                'local_status' => $transaction->payment_status
            ]);
        } catch (\Exception $e) {
            Log::error('Midtrans status check failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to check Midtrans status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually verify payment
     */
    public function verifyPayment(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);

        $transaction = EscrowTransaction::with('adoption.cat')->findOrFail($id);

        if ($transaction->isPaid()) {
            return response()->json([
                'message' => 'Transaction already paid'
            ], 400);
        }

        // Mark as paid
        $transaction->markAsPaid('manual_verification', 'ADMIN-' . time());
        $transaction->adoption->update([
            'status' => 'payment',
            'shipping_deadline' => now()->addDays(3)
        ]);

        // Log activity
        ActivityLog::create([
            'causer_id' => $request->user()->id,
            'subject_type' => 'App\Models\EscrowTransaction',
            'subject_id' => $transaction->id,
            'event' => 'manual_payment_verification',
            'description' => "Admin manually verified payment for {$transaction->adoption->cat->name}",
            'properties' => [
                'transaction_id' => $transaction->id,
                'notes' => $validated['notes'] ?? null
            ]
        ]);

        return response()->json([
            'message' => 'Payment verified successfully',
            'transaction' => $transaction->fresh()
        ]);
    }

    /**
     * Export transactions to CSV
     */
    public function exportTransactions(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transactions = EscrowTransaction::with(['adoption.adopter', 'adoption.cat.shelter'])
            ->orderBy('created_at', 'desc')
            ->get();

        $csv = "Order ID,Adopter,Cat,Shelter,Amount,Status,Created,Paid\n";

        foreach ($transactions as $t) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s\n",
                $t->midtrans_order_id ?? '-',
                $t->adoption->adopter->name ?? '-',
                $t->adoption->cat->name ?? '-',
                $t->adoption->cat->shelter->name ?? '-',
                $t->amount,
                $t->payment_status,
                $t->created_at->format('Y-m-d H:i'),
                $t->paid_at ? $t->paid_at->format('Y-m-d H:i') : '-'
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="escrow-transactions-' . date('Y-m-d') . '.csv"'
        ]);
    }
}
