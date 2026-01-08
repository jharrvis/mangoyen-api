<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShelterKyc;
use App\Models\Shelter;
use App\Models\MembershipTier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KycController extends Controller
{
    /**
     * Submit KYC application
     */
    public function submit(Request $request)
    {
        $user = $request->user();

        // Check if user already has a KYC submission
        $existingKyc = ShelterKyc::where('user_id', $user->id)->first();
        if ($existingKyc) {
            if ($existingKyc->isApproved()) {
                return response()->json([
                    'message' => 'Akun Anda sudah terverifikasi sebagai Shelter'
                ], 400);
            }
            if ($existingKyc->isPending() || $existingKyc->status === 'reviewing') {
                return response()->json([
                    'message' => 'Pengajuan KYC Anda sedang dalam proses review',
                    'kyc' => $existingKyc
                ], 400);
            }
            // If rejected, allow resubmission
        }

        // Check if user already has a shelter
        if ($user->shelter) {
            return response()->json([
                'message' => 'Anda sudah memiliki shelter terdaftar'
            ], 400);
        }

        $validated = $request->validate([
            'ktp_image' => 'required|image|max:5120', // 5MB
            'selfie_with_ktp' => 'required|image|max:5120',
            'address_proof' => 'nullable|image|max:5120',
            'full_name' => 'required|string|max:100',
            'nik' => 'required|string|size:16',
            'phone' => 'required|string|max:15',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'shelter_name' => 'required|string|max:100',
            'shelter_description' => 'nullable|string|max:1000',
        ]);

        // Handle file uploads
        $ktpPath = $request->file('ktp_image')->store('kyc/ktp', 'public');
        $selfiePath = $request->file('selfie_with_ktp')->store('kyc/selfie', 'public');
        $addressProofPath = null;
        if ($request->hasFile('address_proof')) {
            $addressProofPath = $request->file('address_proof')->store('kyc/address', 'public');
        }

        // Update or create KYC
        $kyc = ShelterKyc::updateOrCreate(
            ['user_id' => $user->id],
            [
                'ktp_image' => $ktpPath,
                'selfie_with_ktp' => $selfiePath,
                'address_proof' => $addressProofPath,
                'full_name' => $validated['full_name'],
                'nik' => $validated['nik'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'city' => $validated['city'],
                'province' => $validated['province'],
                'shelter_name' => $validated['shelter_name'],
                'shelter_description' => $validated['shelter_description'] ?? null,
                'status' => ShelterKyc::STATUS_PENDING,
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        return response()->json([
            'message' => 'Pengajuan KYC berhasil dikirim. Tim kami akan review dalam 1-3 hari kerja.',
            'kyc' => $kyc
        ], 201);
    }

    /**
     * Get current user's KYC status
     */
    public function status(Request $request)
    {
        $user = $request->user();
        $kyc = ShelterKyc::where('user_id', $user->id)->first();

        if (!$kyc) {
            return response()->json([
                'has_kyc' => false,
                'message' => 'Anda belum mengajukan KYC'
            ]);
        }

        return response()->json([
            'has_kyc' => true,
            'kyc' => [
                'id' => $kyc->id,
                'status' => $kyc->status,
                'shelter_name' => $kyc->shelter_name,
                'rejection_reason' => $kyc->rejection_reason,
                'reviewed_at' => $kyc->reviewed_at,
                'created_at' => $kyc->created_at,
            ]
        ]);
    }

    /**
     * [ADMIN] List all KYC submissions
     */
    public function adminList(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = ShelterKyc::with('user:id,name,email,avatar');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Order by pending first, then by created_at
        $kycs = $query->orderByRaw("FIELD(status, 'pending', 'reviewing', 'approved', 'rejected')")
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($kycs);
    }

    /**
     * [ADMIN] Get KYC detail
     */
    public function adminShow(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $kyc = ShelterKyc::with(['user:id,name,email,avatar,role', 'reviewer:id,name'])
            ->findOrFail($id);

        return response()->json([
            'kyc' => $kyc,
            'ktp_image_url' => $kyc->ktp_image_url,
            'selfie_url' => $kyc->selfie_url,
            'address_proof_url' => $kyc->address_proof ? asset('storage/' . $kyc->address_proof) : null,
        ]);
    }

    /**
     * [ADMIN] Approve KYC and create shelter
     */
    public function approve(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $kyc = ShelterKyc::with('user')->findOrFail($id);

        if ($kyc->isApproved()) {
            return response()->json(['message' => 'KYC ini sudah diapprove sebelumnya'], 400);
        }

        // Update KYC status
        $kyc->update([
            'status' => ShelterKyc::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        // Update user role to shelter
        $kyc->user->update(['role' => 'shelter']);

        // Get default tier (Anak Bawang)
        $defaultTier = MembershipTier::where('slug', 'anak-bawang')->first();

        // Create shelter profile with default tier
        $shelter = Shelter::create([
            'user_id' => $kyc->user_id,
            'name' => $kyc->shelter_name,
            'slug' => Str::slug($kyc->shelter_name) . '-' . Str::random(6),
            'description' => $kyc->shelter_description,
            'address' => $kyc->address,
            'city' => $kyc->city,
            'province' => $kyc->province,
            'is_verified' => true,
            'membership_tier_id' => $defaultTier?->id,
            'membership_expires_at' => $defaultTier ? now()->addMonths($defaultTier->duration_months) : null,
        ]);

        // Log activity
        \App\Models\ActivityLog::log(
            'shelter_verified',
            "Shelter \"{$shelter->name}\" berhasil diverifikasi",
            $shelter,
            $admin,
            ['owner' => $kyc->user->name, 'city' => $kyc->city]
        );

        return response()->json([
            'message' => 'KYC berhasil diapprove. Shelter telah dibuat.',
            'kyc' => $kyc->fresh(),
            'shelter' => $shelter
        ]);
    }

    /**
     * [ADMIN] Reject KYC
     */
    public function reject(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

        $kyc = ShelterKyc::findOrFail($id);

        if ($kyc->isApproved()) {
            return response()->json(['message' => 'Tidak dapat reject KYC yang sudah diapprove'], 400);
        }

        $kyc->update([
            'status' => ShelterKyc::STATUS_REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'message' => 'KYC berhasil ditolak.',
            'kyc' => $kyc->fresh()
        ]);
    }
}
