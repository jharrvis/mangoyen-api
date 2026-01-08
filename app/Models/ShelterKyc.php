<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShelterKyc extends Model
{
    protected $table = 'shelter_kycs';

    protected $fillable = [
        'user_id',
        'ktp_image',
        'selfie_with_ktp',
        'address_proof',
        'full_name',
        'nik',
        'phone',
        'address',
        'city',
        'province',
        'shelter_name',
        'shelter_description',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_REVIEWING = 'reviewing';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get the user who submitted this KYC
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who reviewed this KYC
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if KYC is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if KYC is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if KYC is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Scope for pending KYCs
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved KYCs
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Get KTP image URL
     */
    public function getKtpImageUrlAttribute(): ?string
    {
        return $this->ktp_image ? asset('storage/' . $this->ktp_image) : null;
    }

    /**
     * Get selfie image URL
     */
    public function getSelfieUrlAttribute(): ?string
    {
        return $this->selfie_with_ktp ? asset('storage/' . $this->selfie_with_ktp) : null;
    }
}
