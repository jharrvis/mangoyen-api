<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cat;
use App\Models\CatPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatController extends Controller
{
    /**
     * Sanitize description by censoring phone numbers, emails, and URLs
     */
    private function sanitizeDescription(?string $description): ?string
    {
        if (empty($description)) {
            return $description;
        }

        // Phone patterns (Indonesia)
        $description = preg_replace('/(\+62|62|0)\d{8,12}/', '[NOMOR DISENSOR]', $description);

        // Email pattern
        $description = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL DISENSOR]', $description);

        // WhatsApp/Telegram links
        $description = preg_replace('/wa\.me\/\d+/i', '[LINK DISENSOR]', $description);
        $description = preg_replace('/t\.me\/\w+/i', '[LINK DISENSOR]', $description);

        // General URLs
        $description = preg_replace('/https?:\/\/[^\s]+/i', '[URL DISENSOR]', $description);

        return $description;
    }

    public function index(Request $request)
    {
        $query = Cat::with(['shelter', 'photos'])
            ->available();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('breed', 'like', "%{$search}%");
            });
        }

        // Filter by breed
        if ($request->filled('breed') && $request->breed !== 'Semua') {
            $query->where('breed', $request->breed);
        }

        // Filter by age
        if ($request->filled('age') && $request->age !== 'Semua') {
            $query->where('age_category', strtolower($request->age));
        }

        // Filter by gender
        if ($request->filled('gender')) {
            $query->where('gender', strtolower($request->gender));
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->whereHas('shelter', function ($q) use ($request) {
                $q->where('city', 'like', "%{$request->city}%");
            });
        }

        // Sorting
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'popular':
                $query->withCount('adoptions')->orderByDesc('adoptions_count');
                break;
            case 'urgent':
                $query->orderByDesc('is_urgent')->orderByDesc('created_at');
                break;
            default:
                $query->orderByDesc('created_at');
        }

        $cats = $query->paginate($request->get('per_page', 12));

        return response()->json($cats);
    }

    public function myCats(Request $request)
    {
        $user = $request->user();

        if (!$user->shelter) {
            return response()->json([
                'data' => [],
                'total' => 0,
                'message' => 'Kamu belum punya shelter.'
            ]);
        }

        $query = Cat::with(['photos'])
            ->where('shelter_id', $user->shelter->id);

        // Search
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $cats = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 50));

        return response()->json($cats);
    }

    public function show($slug)
    {
        $cat = Cat::with(['shelter.user', 'photos'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment view count
        $cat->increment('view_count');

        return response()->json([
            'cat' => $cat,
        ]);
    }

    public function showById($id)
    {
        $cat = Cat::with(['shelter.user', 'photos'])
            ->findOrFail($id);

        // Increment view count
        $cat->increment('view_count');

        return response()->json([
            'cat' => $cat,
        ]);
    }

    /**
     * Toggle save/like status for a cat
     */
    public function toggleSave(Request $request, $id)
    {
        $user = $request->user();
        $cat = Cat::findOrFail($id);

        $wishlist = \App\Models\Wishlist::where('user_id', $user->id)
            ->where('cat_id', $cat->id)
            ->first();

        if ($wishlist) {
            // Unsave (Remove from wishlist)
            $wishlist->delete();
            $cat->decrement('saved_count');
            $message = 'Kucing dihapus dari favorit';
            $saved = false;
        } else {
            // Save (Add to wishlist)
            \App\Models\Wishlist::create([
                'user_id' => $user->id,
                'cat_id' => $cat->id,
            ]);
            $cat->increment('saved_count');
            $message = 'Kucing ditambahkan ke favorit';
            $saved = true;
        }

        return response()->json([
            'message' => $message,
            'saved' => $saved,
            'saved_count' => $cat->fresh()->saved_count,
        ]);
    }

    /**
     * Check if user has saved a cat
     */
    public function checkSaved(Request $request, $id)
    {
        $user = $request->user();

        $isSaved = \App\Models\Wishlist::where('user_id', $user->id)
            ->where('cat_id', $id)
            ->exists();

        return response()->json([
            'saved' => $isSaved,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->shelter) {
            return response()->json([
                'message' => 'Kamu harus punya shelter dulu untuk menambah kucing.',
            ], 403);
        }

        $validated = $request->validate([
            // Basic info
            'name' => 'required|string|max:255',
            'breed' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'age_months' => 'nullable|integer|min:0',
            'age_category' => 'nullable|in:kitten,adult',
            'gender' => 'required|in:jantan,betina',
            'color' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0|max:50',
            'description' => 'nullable|string|min:50|max:1000',

            // Personality
            'energy_level' => 'nullable|in:low,medium,high,hyperactive',
            'temperament' => 'nullable|in:shy,friendly,clingy,independent',
            'good_with_kids' => 'nullable|boolean',
            'good_with_cats' => 'nullable|boolean',
            'good_with_dogs' => 'nullable|boolean',
            'indoor_only' => 'nullable|boolean',
            'tags' => 'nullable|string', // Comma-separated or JSON

            // Health
            'health_status' => 'nullable|string',
            'vaccination_status' => 'nullable|in:none,partial,complete',
            'is_sterilized' => 'nullable|boolean',
            'is_dewormed' => 'nullable|boolean',
            'is_flea_free' => 'nullable|boolean',
            'special_condition' => 'nullable|string|max:255',
            'medical_notes' => 'nullable|string',

            // Adoption
            'adoption_fee' => 'nullable|numeric|min:0',
            'is_urgent' => 'nullable|boolean',
            'adoption_requirements' => 'nullable|string',

            // Media
            'youtube_url' => 'nullable|url',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:5120', // 5MB per photo
            'vaccine_proof' => 'nullable|file|max:5120',
            'certificate' => 'nullable|file|max:5120',
            'awards' => 'nullable|string', // JSON array

            // Price Settings
            'price_visible' => 'nullable|boolean',
            'is_negotiable' => 'nullable|boolean',
        ]);

        // Process tags
        $tags = null;
        if ($request->filled('tags')) {
            if (is_string($validated['tags'])) {
                $tags = array_map('trim', explode(',', $validated['tags']));
            }
        }

        // Process awards
        $awards = null;
        if ($request->filled('awards')) {
            $awards = json_decode($validated['awards'], true) ?: null;
        }

        // Handle file uploads
        $vaccinePath = null;
        if ($request->hasFile('vaccine_proof')) {
            $vaccinePath = $request->file('vaccine_proof')->store('vaccines', 'public');
        }

        $certPath = null;
        if ($request->hasFile('certificate')) {
            $certPath = $request->file('certificate')->store('certificates', 'public');
        }

        $cat = $user->shelter->cats()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(6),
            'breed' => $validated['breed'] ?? 'Domestik',
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'age_months' => $validated['age_months'] ?? null,
            'age_category' => $validated['age_category'] ?? 'adult',
            'gender' => $validated['gender'],
            'color' => $validated['color'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'description' => $this->sanitizeDescription($validated['description'] ?? null),
            'energy_level' => $validated['energy_level'] ?? 'medium',
            'temperament' => $validated['temperament'] ?? 'friendly',
            'good_with_kids' => $validated['good_with_kids'] ?? false,
            'good_with_cats' => $validated['good_with_cats'] ?? false,
            'good_with_dogs' => $validated['good_with_dogs'] ?? false,
            'indoor_only' => $validated['indoor_only'] ?? true,
            'tags' => $tags,
            'health_status' => $validated['health_status'] ?? null,
            'vaccination_status' => $validated['vaccination_status'] ?? 'none',
            'is_sterilized' => $validated['is_sterilized'] ?? false,
            'is_dewormed' => $validated['is_dewormed'] ?? false,
            'is_flea_free' => $validated['is_flea_free'] ?? true,
            'special_condition' => $validated['special_condition'] ?? null,
            'medical_notes' => $validated['medical_notes'] ?? null,
            'vaccine_proof' => $vaccinePath,
            'certificate' => $certPath,
            'awards' => $awards,
            'youtube_url' => $validated['youtube_url'] ?? null,
            'adoption_fee' => $validated['adoption_fee'] ?? 0,
            'is_urgent' => $validated['is_urgent'] ?? false,
            'adoption_requirements' => $validated['adoption_requirements'] ?? null,
            'price_visible' => $validated['price_visible'] ?? true,
            'is_negotiable' => $validated['is_negotiable'] ?? false,
        ]);

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $index => $photo) {
                $path = $photo->store('cats', 'public');
                $this->applyWatermark($path);
                $cat->photos()->create([
                    'photo_path' => $path,
                    'is_primary' => $index === 0,
                ]);
            }
        }

        // Log activity
        \App\Models\ActivityLog::log(
            'cat_created',
            "Kucing baru \"{$cat->name}\" ditambahkan ke {$user->shelter->name}",
            $cat,
            $user,
            ['shelter' => $user->shelter->name, 'breed' => $cat->breed]
        );

        return response()->json([
            'message' => 'Kucing berhasil ditambahkan! 🐱',
            'cat' => $cat->load('photos'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $cat = Cat::findOrFail($id);

        if ($cat->shelter->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Kamu tidak punya akses untuk mengedit kucing ini.',
            ], 403);
        }

        $validated = $request->validate([
            // Basic info
            'name' => 'sometimes|string|max:255',
            'breed' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'age_months' => 'nullable|integer|min:0',
            'age_category' => 'nullable|in:kitten,adult',
            'gender' => 'sometimes|in:jantan,betina',
            'color' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0|max:50',
            'description' => 'nullable|string|min:50|max:1000',

            // Personality
            'energy_level' => 'nullable|in:low,medium,high,hyperactive',
            'temperament' => 'nullable|in:shy,friendly,clingy,independent',
            'good_with_kids' => 'nullable|boolean',
            'good_with_cats' => 'nullable|boolean',
            'good_with_dogs' => 'nullable|boolean',
            'indoor_only' => 'nullable|boolean',
            'tags' => 'nullable|string',

            // Health
            'health_status' => 'nullable|string',
            'vaccination_status' => 'nullable|in:none,partial,complete',
            'is_sterilized' => 'nullable|boolean',
            'is_dewormed' => 'nullable|boolean',
            'is_flea_free' => 'nullable|boolean',
            'special_condition' => 'nullable|string|max:255',
            'medical_notes' => 'nullable|string',

            // Adoption
            'adoption_fee' => 'nullable|numeric|min:0',
            'is_urgent' => 'nullable|boolean',
            'adoption_requirements' => 'nullable|string',
            'status' => 'sometimes|in:available,booked,adopted',

            // Media
            'youtube_url' => 'nullable|url',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:5120',
            'vaccine_proof' => 'nullable|file|max:5120',
            'certificate' => 'nullable|file|max:5120',
            'awards' => 'nullable|string',

            // Price Settings
            'price_visible' => 'nullable|boolean',
            'is_negotiable' => 'nullable|boolean',
        ]);

        // Process tags
        if ($request->filled('tags')) {
            if (is_string($validated['tags'])) {
                $validated['tags'] = array_map('trim', explode(',', $validated['tags']));
            }
        }

        // Process awards
        if ($request->filled('awards')) {
            $validated['awards'] = json_decode($validated['awards'], true) ?: null;
        }

        // Sanitize description
        if (isset($validated['description'])) {
            $validated['description'] = $this->sanitizeDescription($validated['description']);
        }

        // Handle file uploads
        if ($request->hasFile('vaccine_proof')) {
            $validated['vaccine_proof'] = $request->file('vaccine_proof')->store('vaccines', 'public');
        }

        if ($request->hasFile('certificate')) {
            $validated['certificate'] = $request->file('certificate')->store('certificates', 'public');
        }

        // Delete photos that were removed
        if ($request->has('deleted_photo_ids')) {
            $deletedIds = $request->input('deleted_photo_ids', []);
            foreach ($deletedIds as $photoId) {
                $photo = CatPhoto::find($photoId);
                if ($photo && $photo->cat_id === $cat->id) {
                    // Delete file from storage
                    \Storage::disk('public')->delete($photo->photo_path);
                    $photo->delete();
                }
            }
        }

        // Handle new photos
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $index => $photo) {
                $path = $photo->store('cats', 'public');
                $this->applyWatermark($path);
                $cat->photos()->create([
                    'photo_path' => $path,
                    'is_primary' => false,
                ]);
            }
            unset($validated['photos']);
        }

        // Update primary photo
        if ($request->has('primary_photo_index')) {
            $primaryIndex = (int) $request->input('primary_photo_index');
            $photos = $cat->photos()->orderBy('id')->get();

            foreach ($photos as $index => $photo) {
                $photo->update(['is_primary' => $index === $primaryIndex]);
            }
        }

        $cat->update($validated);

        return response()->json([
            'message' => 'Data kucing berhasil diupdate! ✅',
            'cat' => $cat->fresh()->load('photos'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $cat = Cat::findOrFail($id);

        if ($cat->shelter->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Kamu tidak punya akses untuk menghapus kucing ini.',
            ], 403);
        }

        $cat->delete();

        return response()->json([
            'message' => 'Kucing berhasil dihapus.',
        ]);
    }

    public function breeds()
    {
        $breeds = Cat::select('breed')
            ->distinct()
            ->whereNotNull('breed')
            ->orderBy('breed')
            ->pluck('breed');

        return response()->json([
            'breeds' => $breeds,
        ]);
    }
    private function applyWatermark($path)
    {
        $fullPath = storage_path('app/public/' . $path);

        if (!file_exists($fullPath))
            return;

        $info = getimagesize($fullPath);
        $mime = $info['mime'];

        switch ($mime) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($fullPath);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($fullPath);
                break;
            default:
                return;
        }

        if (!$image)
            return;

        $text = "MangOyen.com";
        $font = 5; // Built-in font size (1-5)

        $width = imagesx($image);
        $height = imagesy($image);

        // Calculate text dimensions
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);

        // Add padding
        $padding = 10;

        // Position: Bottom Right
        $x = $width - $textWidth - $padding - 10;
        $y = $height - $textHeight - $padding - 10;

        // Background color (Black semi-transparent)
        $bg = imagecolorallocatealpha($image, 0, 0, 0, 80);

        // Draw background rectangle
        imagefilledrectangle(
            $image,
            $x - $padding,
            $y - $padding,
            $x + $textWidth + $padding,
            $y + $textHeight + $padding,
            $bg
        );

        // Text Color (White)
        $white = imagecolorallocate($image, 255, 255, 255);

        // Add text
        imagestring($image, $font, $x, $y, $text, $white);

        // Save
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($image, $fullPath, 90);
                break;
            case 'image/png':
                // Preserve transparency for PNG
                imagealphablending($image, true);
                imagesavealpha($image, true);
                imagepng($image, $fullPath);
                break;
        }

        imagedestroy($image);
    }

    /**
     * Get user's saved/wishlist cats
     */
    public function savedCats(Request $request)
    {
        $user = $request->user();
        $catIds = \App\Models\Wishlist::where('user_id', $user->id)->pluck('cat_id');

        $cats = Cat::with(['shelter', 'photos'])
            ->whereIn('id', $catIds)
            ->get();

        return response()->json(['data' => $cats]);
    }
}
