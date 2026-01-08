<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shelter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShelterController extends Controller
{
    public function index(Request $request)
    {
        $query = Shelter::with('user')
            ->withCount('cats');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        // Only verified
        if ($request->boolean('verified_only')) {
            $query->where('is_verified', true);
        }

        // Sorting
        $sort = $request->get('sort', 'popular');
        switch ($sort) {
            case 'rating':
                $query->orderByDesc('rating');
                break;
            case 'adopted':
                $query->orderByDesc('total_adopted');
                break;
            default:
                $query->orderByDesc('total_adopted');
        }

        $shelters = $query->paginate($request->get('per_page', 12));

        return response()->json($shelters);
    }

    public function show($slug)
    {
        $shelter = Shelter::with([
            'user',
            'cats' => function ($q) {
                $q->available()->with('photos')->latest();
            }
        ])
            ->withCount('cats')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'shelter' => $shelter,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->shelter) {
            return response()->json([
                'message' => 'Kamu sudah punya shelter.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'required|string|max:100',
            'district' => 'sometimes|string|max:100',
            'province' => 'required|string|max:100',
            'logo' => 'nullable|image|max:2048',
            'cover_image' => 'nullable|image|max:2048',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('shelters', 'public');
        }

        $coverPath = null;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('shelters/covers', 'public');
        }

        $shelter = Shelter::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(5),
            'description' => $validated['description'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'],
            'district' => $validated['district'] ?? null,
            'province' => $validated['province'],
            'logo' => $logoPath,
            'cover_image' => isset($coverPath) ? $coverPath : null,
        ]);

        // Update user role to shelter
        $user->update(['role' => 'shelter']);

        return response()->json([
            'message' => 'Shelter berhasil didaftarkan! 🏠',
            'shelter' => $shelter,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $shelter = Shelter::findOrFail($id);

        if ($shelter->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Kamu tidak punya akses untuk mengedit shelter ini.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'sometimes|string|max:100',
            'district' => 'sometimes|string|max:100',
            'province' => 'sometimes|string|max:100',
            'logo' => 'nullable|image|max:2048',
            'cover_image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('shelters', 'public');
        }

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('shelters/covers', 'public');
        }

        $shelter->update($validated);

        return response()->json([
            'message' => 'Shelter berhasil diupdate! ✅',
            'shelter' => $shelter->fresh(),
        ]);
    }
}
