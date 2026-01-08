<?php

namespace Database\Seeders;

use App\Models\MembershipTier;
use Illuminate\Database\Seeder;

class MembershipTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'slug' => 'anak-bawang',
                'name' => 'Anak Bawang',
                'description' => 'Paket pemula untuk shelter kecil. Cocok untuk yang baru mulai.',
                'price' => 100000,
                'duration_months' => 12,
                'max_cats' => 5,
                'max_photos_per_cat' => 3,
                'max_videos_per_cat' => 0,
                'featured_slots_per_month' => 0,
                'catalog_boost_percent' => 0,
                'badge_type' => 'basic',
                'max_admin_accounts' => 1,
                'has_promo_banner' => false,
                'priority_support' => false,
                'sort_order' => 1,
            ],
            [
                'slug' => 'sultan-meong',
                'name' => 'Sultan Meong',
                'description' => 'Untuk shelter menengah dengan banyak anabul. Bonus prioritas listing!',
                'price' => 300000,
                'duration_months' => 12,
                'max_cats' => 20,
                'max_photos_per_cat' => 5,
                'max_videos_per_cat' => 1,
                'featured_slots_per_month' => 2,
                'catalog_boost_percent' => 10,
                'badge_type' => 'gold',
                'max_admin_accounts' => 2,
                'has_promo_banner' => true,
                'priority_support' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'crazy-cat-lord',
                'name' => 'Crazy Cat Lord',
                'description' => 'Paket ultimate untuk shelter profesional. Unlimited kucing, prioritas maksimal!',
                'price' => 750000,
                'duration_months' => 12,
                'max_cats' => 9999, // Unlimited
                'max_photos_per_cat' => 10,
                'max_videos_per_cat' => 3,
                'featured_slots_per_month' => 5,
                'catalog_boost_percent' => 30,
                'badge_type' => 'diamond',
                'max_admin_accounts' => 5,
                'has_promo_banner' => true,
                'priority_support' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($tiers as $tier) {
            MembershipTier::updateOrCreate(
                ['slug' => $tier['slug']],
                $tier
            );
        }
    }
}
