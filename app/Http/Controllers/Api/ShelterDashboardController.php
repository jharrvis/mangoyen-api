<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cat;
use App\Models\Adoption;

class ShelterDashboardController extends Controller
{
    /**
     * Get general dashboard stats
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        if (!$user->shelter) {
            return response()->json([
                'message' => 'User does not have a shelter profile'
            ], 403);
        }

        $shelterId = $user->shelter->id;

        // Total Active Cats
        $totalCats = Cat::where('shelter_id', $shelterId)
            ->where('status', 'available')
            ->count();

        // Pending Adoptions
        $pendingAdoptions = Adoption::whereHas('cat', function ($q) use ($shelterId) {
            $q->where('shelter_id', $shelterId);
        })
            ->where('status', 'pending')
            ->count();

        // Total Views (All time)
        $totalViews = Cat::where('shelter_id', $shelterId)->sum('view_count');

        // Adoption Success Rate (Adopted / Total Created) - Optional metric
        // ...

        return response()->json([
            'total_cats' => $totalCats,
            'pending_adoptions' => $pendingAdoptions,
            'total_views' => $totalViews,
        ]);
    }

    /**
     * Get most viewed cats
     */
    public function mostViewed(Request $request)
    {
        $user = $request->user();

        if (!$user->shelter) {
            return response()->json([
                'message' => 'User does not have a shelter profile'
            ], 403);
        }

        $limit = $request->get('limit', 5);

        $cats = Cat::where('shelter_id', $user->shelter->id)
            ->where('status', '!=', 'adopted') // Optional: Exclude adopted if we only want active ones
            ->orderByDesc('view_count')
            ->take($limit)
            ->with(['primaryPhoto', 'photos']) // Load photos for thumbnail
            ->get();

        // Transform for display
        $data = $cats->map(function ($cat) {
            // Get primary photo URL
            $photoUrl = null;
            if ($cat->primaryPhoto) {
                $photoUrl = $cat->primaryPhoto->photo_url; // Use accessor
            } elseif ($cat->photos->first()) {
                $photoUrl = $cat->photos->first()->photo_url;
            }

            // Calculate age in months and format it
            $ageMonths = $cat->calculated_age;
            $ageDisplay = '-';
            if ($ageMonths) {
                $months = (int) round($ageMonths);
                $years = floor($months / 12);
                $rem = $months % 12;
                if ($years > 0 && $rem > 0) {
                    $ageDisplay = "{$years}thn {$rem}bln";
                } elseif ($years > 0) {
                    $ageDisplay = "{$years} tahun";
                } else {
                    $ageDisplay = "{$months} bulan";
                }
            }

            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'breed' => $cat->breed,
                'age_display' => $ageDisplay,
                'calculated_age' => $ageMonths,
                'view_count' => $cat->view_count,
                'photo_url' => $photoUrl,
                'status' => $cat->status,
            ];
        });

        return response()->json($data);
    }
}
