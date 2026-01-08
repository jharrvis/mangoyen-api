<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Cat;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Get all wishlist items for current user
     */
    public function index(Request $request)
    {
        $wishlists = Wishlist::with([
            'cat' => function ($query) {
                $query->with([
                    'photos' => function ($q) {
                        $q->limit(1);
                    },
                    'shelter:id,name,slug'
                ]);
            }
        ])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'wishlists' => $wishlists->map(function ($item) {
                $cat = $item->cat;
                $photo = $cat->photos->first();
                return [
                    'id' => $item->id,
                    'cat_id' => $cat->id,
                    'cat' => [
                        'id' => $cat->id,
                        'slug' => $cat->slug,
                        'name' => $cat->name,
                        'breed' => $cat->breed ?? 'Domestik',
                        'gender' => $cat->gender,
                        'age_months' => $cat->age_months,
                        'adoption_fee' => $cat->adoption_fee,
                        'status' => $cat->status,
                        'city' => $cat->city,
                        'photo_url' => $photo ? '/storage/' . $photo->photo_path : null,
                        'shelter' => $cat->shelter ? [
                            'id' => $cat->shelter->id,
                            'name' => $cat->shelter->name,
                            'slug' => $cat->shelter->slug,
                        ] : null,
                    ],
                    'created_at' => $item->created_at,
                ];
            }),
            'total' => $wishlists->count(),
        ]);
    }

    /**
     * Add a cat to wishlist
     */
    public function store(Request $request, $catId)
    {
        // Check if cat exists
        $cat = Cat::find($catId);
        if (!$cat) {
            return response()->json(['message' => 'Anabul tidak ditemukan'], 404);
        }

        // Check if already in wishlist
        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('cat_id', $catId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Anabul sudah ada di wishlist',
                'wishlist' => $existing,
            ], 200);
        }

        // Create wishlist entry
        $wishlist = Wishlist::create([
            'user_id' => $request->user()->id,
            'cat_id' => $catId,
        ]);

        return response()->json([
            'message' => 'Berhasil ditambahkan ke wishlist! 🧡',
            'wishlist' => $wishlist,
        ], 201);
    }

    /**
     * Remove a cat from wishlist
     */
    public function destroy(Request $request, $catId)
    {
        $deleted = Wishlist::where('user_id', $request->user()->id)
            ->where('cat_id', $catId)
            ->delete();

        if ($deleted) {
            return response()->json([
                'message' => 'Berhasil dihapus dari wishlist',
            ]);
        }

        return response()->json([
            'message' => 'Anabul tidak ada di wishlist',
        ], 404);
    }

    /**
     * Check if a cat is in user's wishlist
     */
    public function check(Request $request, $catId)
    {
        $exists = Wishlist::where('user_id', $request->user()->id)
            ->where('cat_id', $catId)
            ->exists();

        return response()->json([
            'in_wishlist' => $exists,
        ]);
    }

    /**
     * Get wishlist IDs for current user (for bulk checking)
     */
    public function ids(Request $request)
    {
        $ids = Wishlist::where('user_id', $request->user()->id)
            ->pluck('cat_id')
            ->toArray();

        return response()->json([
            'cat_ids' => $ids,
        ]);
    }
}
