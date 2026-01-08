<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\Cat;
use App\Models\EscrowTransaction;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Mail\AdoptionRequestMail;
use App\Mail\AdoptionApprovedMail;
use App\Mail\AdoptionRejectedMail;
use App\Mail\PaymentReceivedMail;
use App\Mail\AdoptionCompletedMail;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdoptionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Adoption::with(['cat.shelter', 'cat.photos', 'escrowTransaction', 'adopter'])
            ->withCount([
                'messages as unread_messages_count' => function ($q) use ($user) {
                    $q->where('sender_id', '!=', $user->id)
                        ->whereNull('read_at');
                }
            ]);

        if ($user->isShelter()) {
            // Shelter melihat adopsi kucing mereka
            $query->whereHas('cat.shelter', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } else {
            // Adopter melihat adopsi mereka sendiri
            $query->where('adopter_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $adoptions = $query->latest()->paginate($request->get('per_page', 10));

        return response()->json($adoptions);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'cat_id' => 'required|exists:cats,id',
            'notes' => 'nullable|string',
            'adopter_address' => 'required|string',
            'adopter_phone' => 'required|string|max:20',
        ]);

        $cat = Cat::findOrFail($validated['cat_id']);

        if (!$cat->isAvailable()) {
            return response()->json([
                'message' => 'Maaf, kucing ini sudah tidak tersedia untuk diadopsi.',
            ], 422);
        }

        // Create adoption
        $adoption = Adoption::create([
            'adopter_id' => $user->id,
            'cat_id' => $cat->id,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            'adopter_address' => $validated['adopter_address'],
            'adopter_phone' => $validated['adopter_phone'],
        ]);

        // Create escrow transaction
        $platformFee = $cat->adoption_fee * 0.05; // 5% platform fee
        EscrowTransaction::create([
            'adoption_id' => $adoption->id,
            'amount' => $cat->adoption_fee,
            'platform_fee' => $platformFee,
            'payment_status' => 'pending',
        ]);

        // Update cat status
        $cat->update(['status' => 'booked']);

        // Log activity
        ActivityLog::log(
            'adoption_submitted',
            "Pengajuan adopsi baru untuk {$cat->name}",
            $adoption,
            $user,
            ['cat_name' => $cat->name, 'shelter' => $cat->shelter->name ?? 'Unknown']
        );

        // Notify shelter owner
        $cat->load('shelter.user');
        if ($cat->shelter?->user_id) {
            Notification::notify(
                $cat->shelter->user_id,
                'adoption_request',
                '📥 Pengajuan Adopsi Baru',
                "{$user->name} mengajukan adopsi untuk {$cat->name}",
                '/dashboard',
                $adoption
            );
        }

        // Email notification to shelter (queued)
        if ($cat->shelter->user->email) {
            Mail::to($cat->shelter->user->email)->queue(new AdoptionRequestMail($adoption));
        }

        // Email notification to Admin (queued)
        if (config('mail.admin_email')) {
            Mail::to(config('mail.admin_email'))->queue(new AdoptionRequestMail($adoption));
        }

        // WhatsApp notification to shelter (delayed by 15s)
        if ($cat->shelter->user->phone) {
            SendWhatsAppMessage::dispatch(
                $cat->shelter->user->phone,
                "Halo {$cat->shelter->user->name}, ada pengajuan adopsi baru untuk {$cat->name} dari {$user->name}! Silakan cek di MangOyen. 🐱"
            )->delay(now()->addSeconds(15));
        }

        return response()->json([
            'message' => 'Pengajuan adopsi berhasil! Silakan lakukan pembayaran. 🎉',
            'adoption' => $adoption->load(['cat', 'escrowTransaction']),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $adoption = Adoption::with(['cat.shelter', 'cat.photos', 'escrowTransaction', 'adopter'])
            ->findOrFail($id);

        // Check access
        if (
            $adoption->adopter_id !== $user->id &&
            $adoption->cat->shelter->user_id !== $user->id &&
            !$user->isAdmin()
        ) {
            return response()->json([
                'message' => 'Kamu tidak punya akses untuk melihat adopsi ini.',
            ], 403);
        }

        return response()->json([
            'adoption' => $adoption,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $adoption = Adoption::with(['cat.shelter'])->findOrFail($id);

        // Only shelter owner can approve
        if ($adoption->cat->shelter->user_id !== $user->id) {
            return response()->json([
                'message' => 'Hanya shelter pemilik yang bisa menyetujui adopsi ini.',
            ], 403);
        }

        if ($adoption->status !== 'pending') {
            return response()->json(['message' => 'Status adopsi tidak valid untuk disetujui'], 400);
        }

        $adoption->update(['status' => 'approved']);

        // Log activity
        ActivityLog::log(
            'adoption_approved',
            "Adopsi {$adoption->cat->name} disetujui",
            $adoption,
            $user,
            ['cat_name' => $adoption->cat->name]
        );

        // Notify adopter
        Notification::notify(
            $adoption->adopter_id,
            'adoption_approved',
            '✅ Adopsi Disetujui!',
            "Pengajuan adopsimu untuk {$adoption->cat->name} telah disetujui. Kamu bisa chat dengan shelter atau langsung bayar.",
            '/dashboard',
            $adoption
        );

        // Email notification to adopter (queued)
        if ($adoption->adopter->email) {
            Mail::to($adoption->adopter->email)->queue(new AdoptionApprovedMail($adoption));
        }

        // WhatsApp notification to adopter (delayed by 15s)
        if ($adoption->adopter->phone) {
            SendWhatsAppMessage::dispatch(
                $adoption->adopter->phone,
                "Selamat {$adoption->adopter->name}! 🎉 Pengajuan adopsimu untuk {$adoption->cat->name} telah DISETUJUI. Silakan lakukan pembayaran di dashboard MangOyen."
            )->delay(now()->addSeconds(15));
        }

        return response()->json([
            'message' => 'Adopsi disetujui! Adopter bisa chat atau langsung bayar.',
            'adoption' => $adoption->fresh(),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $user = $request->user();
        $adoption = Adoption::with(['cat.shelter', 'cat', 'escrowTransaction'])->findOrFail($id);

        // Only shelter owner can reject
        if ($adoption->cat->shelter->user_id !== $user->id) {
            return response()->json([
                'message' => 'Hanya shelter pemilik yang bisa menolak adopsi ini.',
            ], 403);
        }

        if ($adoption->status === 'completed' || $adoption->status === 'shipping') {
            return response()->json(['message' => 'Tidak bisa menolak adopsi yang sudah berjalan'], 400);
        }

        // Refund if paid
        if ($adoption->escrowTransaction && $adoption->escrowTransaction->isPaid()) {
            $adoption->escrowTransaction->update(['payment_status' => 'refunded']);
        }

        $adoption->update([
            'status' => 'rejected',
            'rejection_reason' => $request->input('rejection_reason'),
        ]);
        $adoption->cat->update(['status' => 'available']);

        // Log activity
        ActivityLog::log(
            'adoption_rejected',
            "Adopsi {$adoption->cat->name} ditolak",
            $adoption,
            $user,
            ['cat_name' => $adoption->cat->name, 'reason' => $request->input('rejection_reason')]
        );

        // Notify adopter
        $reasonText = $request->input('rejection_reason')
            ? " Alasan: {$request->input('rejection_reason')}"
            : '';
        Notification::notify(
            $adoption->adopter_id,
            'adoption_rejected',
            '❌ Adopsi Ditolak',
            "Maaf, pengajuan adopsimu untuk {$adoption->cat->name} tidak disetujui.{$reasonText}",
            '/dashboard',
            $adoption
        );

        // Email notification to adopter (queued)
        if ($adoption->adopter->email) {
            Mail::to($adoption->adopter->email)->queue(new AdoptionRejectedMail($adoption));
        }

        // WhatsApp notification to adopter (delayed by 15s)
        if ($adoption->adopter->phone) {
            $reasonText = $adoption->rejection_reason ? " Alasan: {$adoption->rejection_reason}" : '';
            SendWhatsAppMessage::dispatch(
                $adoption->adopter->phone,
                "Halo {$adoption->adopter->name}, mohon maaf pengajuan adopsimu untuk {$adoption->cat->name} belum bisa disetujui.{$reasonText}"
            )->delay(now()->addSeconds(15));
        }

        return response()->json([
            'message' => 'Adopsi ditolak. Kucing kembali available.',
            'adoption' => $adoption->fresh(),
        ]);
    }

    public function confirmPayment(Request $request, $id)
    {
        $user = $request->user();
        $adoption = Adoption::with('escrowTransaction')->findOrFail($id);

        if ($adoption->adopter_id !== $user->id) {
            return response()->json([
                'message' => 'Kamu tidak punya akses untuk mengkonfirmasi pembayaran ini.',
            ], 403);
        }

        $validated = $request->validate([
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string',
        ]);

        $adoption->escrowTransaction->markAsPaid(
            $validated['payment_method'],
            $validated['payment_reference'] ?? null
        );

        $adoption->update(['status' => 'payment']);

        // Email notification to shelter (queued)
        if ($adoption->cat->shelter->user->email) {
            Mail::to($adoption->cat->shelter->user->email)->queue(new PaymentReceivedMail($adoption));
        }

        // WhatsApp notification to shelter (delayed by 15s)
        if ($adoption->cat->shelter->user->phone) {
            SendWhatsAppMessage::dispatch(
                $adoption->cat->shelter->user->phone,
                "💰 Pembayaran telah diterima untuk adopsi {$adoption->cat->name}. Dana aman di Rekber MangOyen. Silakan proses pengiriman anabul!"
            )->delay(now()->addSeconds(15));
        }

        return response()->json([
            'message' => 'Pembayaran berhasil dikonfirmasi! Dana ditahan di Rekber. 💰',
            'adoption' => $adoption->fresh()->load('escrowTransaction'),
        ]);
    }

    public function confirmReceived(Request $request, $id)
    {
        $user = $request->user();
        $adoption = Adoption::with(['escrowTransaction', 'cat.shelter'])->findOrFail($id);

        if ($adoption->adopter_id !== $user->id) {
            return response()->json([
                'message' => 'Kamu tidak punya akses untuk mengkonfirmasi penerimaan ini.',
            ], 403);
        }

        // Release escrow
        $adoption->escrowTransaction->release();

        // Update adoption status
        $adoption->update(['status' => 'completed']);

        // Update cat status
        $adoption->cat->update(['status' => 'adopted']);

        // Update shelter stats
        $adoption->cat->shelter->increment('total_adopted');

        // Log activity
        ActivityLog::create([
            'causer_id' => $user->id,
            'subject_type' => 'App\Models\Adoption',
            'subject_id' => $adoption->id,
            'event' => 'adoption_completed',
            'description' => "Adopsi {$adoption->cat->name} selesai! Adopter telah menerima anabul dan dana dilepas ke shelter.",
            'properties' => [
                'adoption_id' => $adoption->id,
                'cat_id' => $adoption->cat_id,
                'escrow_amount' => $adoption->escrowTransaction->amount,
                'shelter_id' => $adoption->cat->shelter_id
            ]
        ]);

        // Email notification to both (queued)
        if ($adoption->adopter->email) {
            Mail::to($adoption->adopter->email)->queue(new AdoptionCompletedMail($adoption));
        }
        if ($adoption->cat->shelter->user->email) {
            Mail::to($adoption->cat->shelter->user->email)->queue(new AdoptionCompletedMail($adoption));
        }

        // WhatsApp notifications (delayed)
        if ($adoption->adopter->phone) {
            SendWhatsAppMessage::dispatch(
                $adoption->adopter->phone,
                "Yeay! Adopsi {$adoption->cat->name} selesai! 🎉 Terima kasih telah menjadi adopter. Selamat berpetualang bersama teman baru!"
            )->delay(now()->addSeconds(15));
        }

        if ($adoption->cat->shelter->user->phone) {
            SendWhatsAppMessage::dispatch(
                $adoption->cat->shelter->user->phone,
                "Adopsi {$adoption->cat->name} telah selesai! 🎊 Dana akan segera diteruskan ke akunmu. Terima kasih sudah merawat anabul ini dengan baik."
            )->delay(now()->addSeconds(30)); // Extra delay for second message
        }

        return response()->json([
            'message' => 'Selamat! Kucing sudah diterima. Dana sudah dilepas ke shelter. 🎊',
            'adoption' => $adoption->fresh(),
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $adoption = Adoption::with(['escrowTransaction', 'cat'])->findOrFail($id);

        // Only adopter, shelter, or admin can cancel
        if (
            $adoption->adopter_id !== $user->id &&
            $adoption->cat->shelter->user_id !== $user->id &&
            !$user->isAdmin()
        ) {
            return response()->json([
                'message' => 'Kamu tidak punya akses untuk membatalkan adopsi ini.',
            ], 403);
        }

        // Refund if already paid
        if ($adoption->escrowTransaction->isPaid()) {
            $adoption->escrowTransaction->update(['payment_status' => 'refunded']);
        }

        $adoption->update(['status' => 'cancelled']);
        $adoption->cat->update(['status' => 'available']);

        return response()->json([
            'message' => 'Adopsi dibatalkan. Dana akan dikembalikan jika sudah dibayar.',
            'adoption' => $adoption->fresh(),
        ]);
    }

    /**
     * Update final price after negotiation (Shelter only)
     */
    public function updateFinalPrice(Request $request, $id)
    {
        $user = $request->user();
        $adoption = Adoption::with(['cat.shelter'])->findOrFail($id);

        // Only shelter owner can update final price
        if ($adoption->cat->shelter->user_id !== $user->id) {
            return response()->json([
                'message' => 'Hanya shelter pemilik yang bisa mengupdate harga final.',
            ], 403);
        }

        // Only allow if cat is negotiable
        if (!$adoption->cat->is_negotiable) {
            return response()->json([
                'message' => 'Kucing ini tidak bisa dinegosiasi harganya.',
            ], 400);
        }

        $validated = $request->validate([
            'final_price' => 'required|numeric|min:0',
        ]);

        $adoption->update([
            'final_price' => $validated['final_price'],
            'price_negotiated_at' => now(),
        ]);

        // Update escrow transaction amount if exists
        if ($adoption->escrowTransaction && $adoption->escrowTransaction->payment_status === 'pending') {
            $platformFee = $validated['final_price'] * 0.05; // 5% platform fee
            $adoption->escrowTransaction->update([
                'amount' => $validated['final_price'],
                'platform_fee' => $platformFee,
            ]);
        }

        // Notify adopter about price update
        \App\Models\Notification::notify(
            $adoption->adopter_id,
            'price_updated',
            '💰 Harga Final Diupdate',
            "Harga final untuk adopsi {$adoption->cat->name} sudah diupdate menjadi Rp " . number_format($validated['final_price'], 0, ',', '.'),
            '/dashboard',
            $adoption
        );

        return response()->json([
            'message' => 'Harga final berhasil diupdate! Adopter akan menerima notifikasi.',
            'adoption' => $adoption->fresh()->load(['cat', 'escrowTransaction']),
        ]);
    }

    /**
     * Shelter confirms shipping with tracking number
     */
    public function confirmShipping(Request $request, $id)
    {
        $user = $request->user();
        $adoption = Adoption::with(['cat.shelter', 'adopter'])->findOrFail($id);

        // Only shelter can confirm shipping
        if (!$user->isShelter() || $adoption->cat->shelter->user_id !== $user->id) {
            return response()->json([
                'message' => 'Hanya shelter yang bisa mengkonfirmasi pengiriman.',
            ], 403);
        }

        // Must be in payment status
        if ($adoption->status !== 'payment') {
            return response()->json([
                'message' => 'Status adopsi tidak valid untuk konfirmasi pengiriman.',
            ], 400);
        }

        $validated = $request->validate([
            'tracking_number' => 'required|string|max:100',
            'shipping_proof' => 'nullable|image|max:5120',
        ]);

        $shippingProof = null;
        if ($request->hasFile('shipping_proof')) {
            $shippingProof = $request->file('shipping_proof')->store('shipping-proofs', 'public');
        }

        // Update adoption
        $adoption->update([
            'status' => 'shipping',
            'tracking_number' => $validated['tracking_number'],
            'shipping_proof' => $shippingProof,
            'shipped_at' => now(),
        ]);

        // Log activity
        ActivityLog::create([
            'causer_id' => $user->id,
            'subject_type' => 'App\Models\Adoption',
            'subject_id' => $adoption->id,
            'event' => 'shipping_confirmed',
            'description' => "Shelter mengkonfirmasi pengiriman {$adoption->cat->name} dengan resi: {$validated['tracking_number']}",
            'properties' => [
                'adoption_id' => $adoption->id,
                'tracking_number' => $validated['tracking_number'],
                'has_proof' => !is_null($shippingProof)
            ]
        ]);

        // Notify adopter
        Notification::notify(
            $adoption->adopter_id,
            'shipping_confirmed',
            '📦 Kucing Sedang Dikirim!',
            "Kucing {$adoption->cat->name} sudah dikirim dengan nomor resi: {$validated['tracking_number']}. Mohon konfirmasi setelah menerima.",
            '/dashboard',
            $adoption
        );

        // WA to adopter
        if ($adoption->adopter->phone) {
            SendWhatsAppMessage::dispatch(
                $adoption->adopter->phone,
                "📦 {$adoption->cat->name} sudah dikirim! Nomor resi: {$validated['tracking_number']}. Silakan konfirmasi setelah menerima anabul. - MangOyen"
            )->delay(now()->addSeconds(5));
        }

        return response()->json([
            'message' => 'Pengiriman berhasil dikonfirmasi! Adopter akan menerima notifikasi.',
            'adoption' => $adoption->fresh(),
        ]);
    }
}
