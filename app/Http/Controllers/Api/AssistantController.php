<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cat;
use App\Services\MangoyenHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssistantController extends Controller
{
    /**
     * Chat with MangOyen assistant
     */
    public function chat(Request $request)
    {
        $message = $request->input('message', '');
        $context = $request->input('context', []);

        if (empty($message)) {
            return response()->json(['message' => 'Pesan tidak boleh kosong'], 400);
        }

        // Check if this is a search request
        $searchResult = $this->detectSearchIntent($message);

        if ($searchResult['isSearch']) {
            // Search cats from database
            $cats = $this->searchCats($searchResult['criteria']);

            if ($cats->count() > 0) {
                $responseMessage = $this->formatSearchResponse($cats, $searchResult['criteria']);
                return response()->json([
                    'message' => $responseMessage,
                    'cats' => $cats->take(5)->map(function ($cat) {
                        $photo = $cat->photos->first();
                        $photoUrl = null;
                        if ($photo && $photo->photo_path) {
                            $photoUrl = '/storage/' . $photo->photo_path;
                        }
                        return [
                            'id' => $cat->id,
                            'slug' => $cat->slug,
                            'name' => $cat->name,
                            'breed' => $cat->breed ?? 'Domestik',
                            'location' => $cat->city ?? $cat->location ?? 'Indonesia',
                            'photo_url' => $photoUrl,
                        ];
                    })->values(),
                    'total' => $cats->count(),
                ]);
            } else {
                return response()->json([
                    'message' => "🐱 Hmm, aku belum nemuin anabul yang sesuai kriteria kamu nih. Mau coba cari dengan kriteria lain?",
                    'cats' => [],
                ]);
            }
        }

        // Regular Q&A - use AI
        $response = MangoyenHelper::ask($message);

        return response()->json([
            'message' => $response,
            'cats' => null,
        ]);
    }

    /**
     * Detect if message is a search request
     */
    private function detectSearchIntent(string $message): array
    {
        $message = strtolower($message);

        $searchKeywords = [
            'cari',
            'carikan',
            'mau',
            'ingin',
            'pengen',
            'nyari',
            'ada',
            'punya',
            'tersedia',
            'search',
            'find'
        ];

        $isSearch = false;
        foreach ($searchKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                $isSearch = true;
                break;
            }
        }

        // Extract criteria
        $criteria = [
            'breed' => null,
            'location' => null,
            'gender' => null,
            'age' => null,
        ];

        // Detect breed
        $breeds = [
            'persia' => 'Persia',
            'anggora' => 'Anggora',
            'himalaya' => 'Himalaya',
            'british shorthair' => 'British Shorthair',
            'ragdoll' => 'Ragdoll',
            'maine coon' => 'Maine Coon',
            'siamese' => 'Siamese',
            'siam' => 'Siamese',
            'domestik' => 'Domestik',
            'kampung' => 'Domestik',
            'lokal' => 'Domestik',
            'munchkin' => 'Munchkin',
            'scottish fold' => 'Scottish Fold',
            'bengal' => 'Bengal',
            'sphynx' => 'Sphynx',
        ];

        foreach ($breeds as $key => $value) {
            if (str_contains($message, $key)) {
                $criteria['breed'] = $value;
                break;
            }
        }

        // Detect location (Indonesian cities)
        $cities = [
            'jakarta',
            'surabaya',
            'bandung',
            'medan',
            'semarang',
            'makassar',
            'palembang',
            'tangerang',
            'depok',
            'bekasi',
            'yogyakarta',
            'jogja',
            'malang',
            'solo',
            'bogor',
            'bali',
            'denpasar',
            'lampung',
            'batam',
            'pekanbaru',
        ];

        foreach ($cities as $city) {
            if (str_contains($message, $city)) {
                $criteria['location'] = ucfirst($city);
                break;
            }
        }

        // Detect gender
        if (str_contains($message, 'jantan') || str_contains($message, 'male') || str_contains($message, 'cowok')) {
            $criteria['gender'] = 'Jantan';
        } elseif (str_contains($message, 'betina') || str_contains($message, 'female') || str_contains($message, 'cewek')) {
            $criteria['gender'] = 'Betina';
        }

        // Detect age keywords
        if (str_contains($message, 'kitten') || str_contains($message, 'anak') || str_contains($message, 'kecil')) {
            $criteria['age'] = 'kitten';
        } elseif (str_contains($message, 'dewasa') || str_contains($message, 'adult')) {
            $criteria['age'] = 'adult';
        }

        return [
            'isSearch' => $isSearch,
            'criteria' => $criteria,
        ];
    }

    /**
     * Search cats from database
     */
    private function searchCats(array $criteria)
    {
        $query = Cat::query()
            ->where('status', 'available')
            ->with([
                'photos' => function ($q) {
                    $q->limit(1);
                }
            ]);

        if ($criteria['breed']) {
            $query->where('breed', 'like', '%' . $criteria['breed'] . '%');
        }

        if ($criteria['location']) {
            $query->where(function ($q) use ($criteria) {
                $q->where('city', 'like', '%' . $criteria['location'] . '%')
                    ->orWhere('location', 'like', '%' . $criteria['location'] . '%');
            });
        }

        if ($criteria['gender']) {
            $query->where('gender', $criteria['gender']);
        }

        if ($criteria['age'] === 'kitten') {
            $query->where('age_months', '<=', 6);
        } elseif ($criteria['age'] === 'adult') {
            $query->where('age_months', '>', 12);
        }

        return $query->orderBy('created_at', 'desc')->limit(10)->get();
    }

    /**
     * Format search response
     */
    private function formatSearchResponse($cats, $criteria): string
    {
        $count = $cats->count();
        $breed = $criteria['breed'] ?? 'anabul';
        $location = $criteria['location'] ? " di {$criteria['location']}" : '';

        $messages = [
            "🐱 Yay! Aku nemuin {$count} {$breed}{$location} yang lagi nyari rumah baru! Ini beberapa yang cocok:",
            "😸 Ada {$count} {$breed}{$location} nih yang available! Cek yuk:",
            "🐾 Ketemu! {$count} {$breed}{$location} siap diadopsi. Ini rekomendasiku:",
        ];

        return $messages[array_rand($messages)];
    }
}
