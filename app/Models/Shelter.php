<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Shelter extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'address',
        'city',
        'district',
        'province',
        'logo',
        'cover_image',
        'is_verified',
        'rating',
        'total_adopted',
        'membership_tier_id',
        'membership_expires_at',
    ];

    protected $appends = ['logo_url', 'cover_image_url'];

    public function getLogoUrlAttribute()
    {
        if ($this->logo) {
            return asset('storage/' . $this->logo);
        }

        // Fallback to user avatar
        if ($this->user && $this->user->avatar) {
            if (Str::startsWith($this->user->avatar, 'http')) {
                return $this->user->avatar;
            }
            return asset('storage/' . $this->user->avatar);
        }

        return null;
    }

    public function getCoverImageUrlAttribute()
    {
        if ($this->cover_image) {
            return asset('storage/' . $this->cover_image);
        }
        return null;
    }

    protected $casts = [
        'is_verified' => 'boolean',
        'rating' => 'decimal:1',
        'membership_expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($shelter) {
            if (!$shelter->slug) {
                $shelter->slug = Str::slug($shelter->name) . '-' . Str::random(5);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cats()
    {
        return $this->hasMany(Cat::class);
    }

    public function availableCats()
    {
        return $this->cats()->where('status', 'available');
    }

    public function membershipTier()
    {
        return $this->belongsTo(MembershipTier::class, 'membership_tier_id');
    }

    /**
     * Check if membership is active
     */
    public function hasMembership(): bool
    {
        return $this->membership_tier_id &&
            $this->membership_expires_at &&
            $this->membership_expires_at->isFuture();
    }

    /**
     * Get tier name or default
     */
    public function getTierNameAttribute(): string
    {
        return $this->membershipTier?->name ?? 'Free';
    }

    /**
     * Get tier badge type
     */
    public function getTierBadgeAttribute(): string
    {
        return $this->membershipTier?->badge_type ?? 'basic';
    }

    /**
     * Check if shelter can add more cats
     */
    public function canAddCat(): bool
    {
        if (!$this->membershipTier)
            return true; // Free tier has no limit initially
        return $this->cats()->count() < $this->membershipTier->max_cats;
    }
}
