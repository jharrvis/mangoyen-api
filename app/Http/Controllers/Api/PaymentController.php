<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\EscrowTransaction;
use App\Services\MidtransService;
use App\Models\Notification;
use App\Mail\PaymentReceivedMail;
use App\Mail\InvoiceMail;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    /**
     * Get Snap Token for an adoption
     */
    public function createSnapToken(Request $request, $adoptionId)
    {
        $user = $request->user();
        $adoption = Adoption::with(['escrowTransaction', 'cat', 'adopter'])->findOrFail($adoptionId);

        if ($adoption->adopter_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaction = $adoption->escrowTransaction;

        if (!$transaction) {
            return response()->json(['message' => 'Escrow transaction not found'], 404);
        }

        // Return existing token if valid (optional optimization)
        if ($transaction->snap_token && $transaction->expires_at && $transaction->expires_at->isFuture()) {
            return response()->json(['snap_token' => $transaction->snap_token]);
        }

        $snapToken = $this->midtransService->createSnapToken($transaction);

        if (!$snapToken) {
            return response()->json(['message' => 'Failed to generate payment token'], 500);
        }

        return response()->json(['snap_token' => $snapToken]);
    }

    /**
     * Handle Midtrans Webhook Notification
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();

        // Log incoming webhook for debugging
        Log::info('Midtrans Webhook Received', ['payload' => $payload]);

        // Handle Midtrans test webhook (when testing from dashboard)
        if (empty($payload) || !isset($payload['order_id'])) {
            Log::info('Midtrans Webhook Test - Empty or Test Payload');
            return response()->json(['status' => 'ok', 'message' => 'Webhook endpoint is working']);
        }

        $serverKey = config('services.midtrans.server_key');

        // Verify Signature
        $expectedSignature = hash("sha512", $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $serverKey);

        if ($expectedSignature !== ($payload['signature_key'] ?? '')) {
            Log::warning('Midtrans Webhook Invalid Signature', [
                'order_id' => $payload['order_id'] ?? 'unknown',
                'expected' => substr($expectedSignature, 0, 20) . '...',
                'received' => substr($payload['signature_key'] ?? '', 0, 20) . '...',
            ]);
            return response()->json(['message' => 'Invalid Signature'], 403);
        }

        $orderId = $payload['order_id'];
        $transactionStatus = $payload['transaction_status'];
        $type = $payload['payment_type'];
        $fraudId = $payload['fraud_status'] ?? null;

        $transaction = EscrowTransaction::where('midtrans_order_id', $orderId)->first();

        if (!$transaction) {
            Log::warning('Midtrans Webhook Transaction Not Found', ['order_id' => $orderId]);
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $adoption = $transaction->adoption;

        if ($transactionStatus == 'capture') {
            if ($type == 'credit_card') {
                if ($fraudId == 'challenge') {
                    $transaction->update(['payment_status' => 'pending']);
                } else {
                    $this->markAsPaid($transaction, $type, $payload['transaction_id']);
                }
            }
        } else if ($transactionStatus == 'settlement') {
            $this->markAsPaid($transaction, $type, $payload['transaction_id']);
        } else if ($transactionStatus == 'pending') {
            $transaction->update(['payment_status' => 'pending']);
        } else if ($transactionStatus == 'deny') {
            $transaction->update(['payment_status' => 'failed']);
        } else if ($transactionStatus == 'expire') {
            $transaction->update(['payment_status' => 'expired']);
        } else if ($transactionStatus == 'cancel') {
            $transaction->update(['payment_status' => 'cancelled']);
        }

        return response()->json(['status' => 'success']);
    }

    protected function markAsPaid($transaction, $method, $reference)
    {
        if ($transaction->isPaid())
            return;

        $transaction->markAsPaid($method, $reference);
        $adoption = $transaction->adoption;
        $adoption->update([
            'status' => 'payment',
            'shipping_deadline' => now()->addDays(3) // 3 hari untuk kirim
        ]);

        // Send Notifications (Email & WA)
        $this->sendPaymentNotifications($adoption);
    }

    protected function sendPaymentNotifications($adoption)
    {
        $adoption->load(['cat.shelter.user', 'adopter', 'escrowTransaction']);

        // === INVOICE EMAIL TO ADOPTER (USER) ===
        if ($adoption->adopter->email) {
            Mail::to($adoption->adopter->email)->queue(new InvoiceMail($adoption, 'adopter'));
        }

        // === INVOICE EMAIL TO SHELTER ===
        if ($adoption->cat->shelter->user->email) {
            Mail::to($adoption->cat->shelter->user->email)->queue(new InvoiceMail($adoption, 'shelter'));
        }

        // WA to Shelter
        if ($adoption->cat->shelter->user->phone) {
            SendWhatsAppMessage::dispatch(
                $adoption->cat->shelter->user->phone,
                "💰 Pembayaran telah diterima untuk adopsi {$adoption->cat->name}. Dana aman di Rekber MangOyen. Silakan proses pengiriman anabul!"
            )->delay(now()->addSeconds(5));
        }

        // Push notification in app
        Notification::notify(
            $adoption->cat->shelter->user_id,
            'payment_received',
            '💰 Pembayaran Diterima',
            "Pembayaran untuk {$adoption->cat->name} telah diverifikasi. Silakan hubungi adopter untuk pengiriman.",
            '/dashboard',
            $adoption
        );
    }
}
