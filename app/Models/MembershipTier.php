<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipTier extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'price',
        'duration_months',
        'max_cats',
        'max_photos_per_cat',
        'max_videos_per_cat',
        'featured_slots_per_month',
        'catalog_boost_percent',
        'badge_type',
        'max_admin_accounts',
        'has_promo_banner',
        'priority_support',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'max_cats' => 'integer',
        'max_photos_per_cat' => 'integer',
        'max_videos_per_cat' => 'integer',
        'featured_slots_per_month' => 'integer',
        'catalog_boost_percent' => 'integer',
        'max_admin_accounts' => 'integer',
        'has_promo_banner' => 'boolean',
        'priority_support' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Tier slugs
    const TIER_ANAK_BAWANG = 'anak-bawang';
    const TIER_SULTAN_MEONG = 'sultan-meong';
    const TIER_CRAZY_CAT_LORD = 'crazy-cat-lord';

    /**
     * Get shelters with this tier
     */
    public function shelters(): HasMany
    {
        return $this->hasMany(Shelter::class, 'membership_tier_id');
    }

    /**
     * Scope for active tiers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    /**
     * Get badge emoji
     */
    public function getBadgeEmojiAttribute(): string
    {
        return match ($this->badge_type) {
            'gold' => '😺',
            'diamond' => '👑',
            default => '🐱',
        };
    }

    /**
     * Check if tier is unlimited cats
     */
    public function isUnlimitedCats(): bool
    {
        return $this->max_cats >= 9999;
    }
}
