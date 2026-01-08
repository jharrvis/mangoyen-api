<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipTier;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    /**
     * List all active membership tiers
     */
    public function index()
    {
        $tiers = MembershipTier::active()
            ->ordered()
            ->get()
            ->map(function ($tier) {
                return [
                    'id' => $tier->id,
                    'slug' => $tier->slug,
                    'name' => $tier->name,
                    'description' => $tier->description,
                    'price' => $tier->price,
                    'formatted_price' => $tier->formatted_price,
                    'duration_months' => $tier->duration_months,
                    'max_cats' => $tier->isUnlimitedCats() ? 'Unlimited' : $tier->max_cats,
                    'max_photos_per_cat' => $tier->max_photos_per_cat,
                    'max_videos_per_cat' => $tier->max_videos_per_cat,
                    'featured_slots_per_month' => $tier->featured_slots_per_month,
                    'catalog_boost_percent' => $tier->catalog_boost_percent,
                    'badge_type' => $tier->badge_type,
                    'badge_emoji' => $tier->badge_emoji,
                    'max_admin_accounts' => $tier->max_admin_accounts,
                    'has_promo_banner' => $tier->has_promo_banner,
                    'priority_support' => $tier->priority_support,
                ];
            });

        return response()->json([
            'tiers' => $tiers
        ]);
    }

    /**
     * Get single tier details
     */
    public function show($slug)
    {
        $tier = MembershipTier::where('slug', $slug)->firstOrFail();

        return response()->json([
            'tier' => [
                'id' => $tier->id,
                'slug' => $tier->slug,
                'name' => $tier->name,
                'description' => $tier->description,
                'price' => $tier->price,
                'formatted_price' => $tier->formatted_price,
                'duration_months' => $tier->duration_months,
                'max_cats' => $tier->isUnlimitedCats() ? 'Unlimited' : $tier->max_cats,
                'max_photos_per_cat' => $tier->max_photos_per_cat,
                'max_videos_per_cat' => $tier->max_videos_per_cat,
                'features' => [
                    'featured_slots' => $tier->featured_slots_per_month . '/bulan',
                    'catalog_boost' => '+' . $tier->catalog_boost_percent . '%',
                    'badge' => ucfirst($tier->badge_type),
                    'multi_admin' => $tier->max_admin_accounts,
                    'promo_banner' => $tier->has_promo_banner,
                    'priority_support' => $tier->priority_support,
                ],
            ]
        ]);
    }
}
