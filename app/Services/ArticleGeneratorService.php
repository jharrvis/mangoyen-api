<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArticleGeneratorService
{
    private $openRouterUrl = 'https://openrouter.ai/api/v1/chat/completions';
    // Use a more reliable free model
    private $model = 'meta-llama/llama-3.2-3b-instruct:free';

    /**
     * System prompt for article generation
     */
    private $systemPrompt = <<<PROMPT
Kamu adalah penulis artikel profesional untuk MangOyen, platform adopsi kucing Indonesia.

=== GAYA PENULISAN ===
- Bahasa Indonesia yang santai tapi informatif
- Friendly dan engaging, cocok untuk cat lovers
- Gunakan emoji secukupnya untuk mempermanis 🐱
- Paragraf pendek dan mudah dibaca
- Berikan tips praktis yang actionable

=== STRUKTUR ARTIKEL ===
1. Pembukaan yang menarik (1-2 paragraf)
2. Isi dengan subheading yang jelas
3. Tips atau poin-poin penting
4. Penutup yang inspiring

=== TOPIK YANG DIPERBOLEHKAN ===
✅ Perawatan kucing (makanan, grooming, kesehatan)
✅ Tips adopsi kucing
✅ Edukasi tentang vaksin, sterilisasi
✅ Cara memahami behavior kucing
✅ Tips untuk first-time cat owner
✅ Review produk kucing (general)

=== FORMAT OUTPUT ===
Berikan response dalam format JSON:
{
    "title": "Judul artikel yang menarik",
    "excerpt": "Ringkasan 2-3 kalimat untuk preview",
    "content": "<p>Konten artikel dalam HTML</p>",
    "suggested_category": "HEALTH/TIPS/GUIDE/NEWS",
    "suggested_tags": ["tag1", "tag2", "tag3"]
}

PENTING: Response HARUS berupa valid JSON tanpa markdown code block.
PROMPT;

    /**
     * Generate article from topic
     */
    public function generate(string $topic): array
    {
        $openRouterKey = config('services.openrouter.api_key');

        if (empty($openRouterKey)) {
            throw new \Exception('OpenRouter API key tidak dikonfigurasi');
        }

        $userPrompt = "Buatkan artikel tentang: {$topic}\n\nPastikan artikel minimal 500 kata dan informatif.";

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $openRouterKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => 'MangOyen Article Generator',
                ])
                ->post($this->openRouterUrl, [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ]);

            if (!$response->successful()) {
                Log::error('ArticleGenerator API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Gagal menghubungi AI service');
            }

            $content = $response->json()['choices'][0]['message']['content'] ?? '';

            Log::info('ArticleGenerator raw response', ['content' => $content]);

            // Extract JSON from response - try multiple patterns
            $data = $this->extractJsonFromResponse($content);

            if (!$data) {
                Log::error('ArticleGenerator JSON parse error', [
                    'error' => 'Could not extract valid JSON',
                    'content' => $content
                ]);
                throw new \Exception('Format response AI tidak valid');
            }

            // Validate required fields
            if (empty($data['title']) || empty($data['content'])) {
                throw new \Exception('Response AI tidak lengkap');
            }

            return [
                'title' => $data['title'],
                'excerpt' => $data['excerpt'] ?? '',
                'content' => $data['content'],
                'category' => $data['suggested_category'] ?? 'TIPS',
                'suggested_tags' => $data['suggested_tags'] ?? [],
                'is_ai_generated' => true,
            ];

        } catch (\Exception $e) {
            Log::error('ArticleGenerator failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Extract JSON from AI response that may contain extra text or markdown
     */
    private function extractJsonFromResponse(string $content): ?array
    {
        // Log the raw content for debugging
        Log::debug('ArticleGenerator parsing content', ['length' => strlen($content)]);

        // Clean whitespace at start/end
        $content = trim($content);

        // Try 1: Direct JSON parse (most common case)
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && $this->isValidArticleData($data)) {
            Log::debug('ArticleGenerator: Direct parse successful');
            return $data;
        }

        // Try 2: Fix common JSON issues - newlines inside strings
        // Replace actual newlines with \n escape sequences inside the JSON
        $fixedContent = $this->fixJsonNewlines($content);
        $data = json_decode($fixedContent, true);
        if (json_last_error() === JSON_ERROR_NONE && $this->isValidArticleData($data)) {
            Log::debug('ArticleGenerator: Fixed newlines parse successful');
            return $data;
        }

        // Try 3: Extract from markdown code block
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $content, $matches)) {
            $extracted = $this->fixJsonNewlines(trim($matches[1]));
            $data = json_decode($extracted, true);
            if (json_last_error() === JSON_ERROR_NONE && $this->isValidArticleData($data)) {
                Log::debug('ArticleGenerator: Markdown block parse successful');
                return $data;
            }
        }

        // Try 4: Find JSON object pattern { ... }
        if (preg_match('/\{[\s\S]*\}/s', $content, $matches)) {
            $extracted = $this->fixJsonNewlines($matches[0]);
            $data = json_decode($extracted, true);
            if (json_last_error() === JSON_ERROR_NONE && $this->isValidArticleData($data)) {
                Log::debug('ArticleGenerator: Pattern extract successful');
                return $data;
            }
        }

        Log::error('ArticleGenerator: All parsing methods failed', [
            'json_error' => json_last_error_msg()
        ]);

        return null;
    }

    /**
     * Fix newlines inside JSON string values
     */
    private function fixJsonNewlines(string $json): string
    {
        // Replace literal newlines with escaped \n within string values
        // This is a simplified approach - replace all newlines with space
        $json = str_replace(["\r\n", "\r", "\n"], ' ', $json);

        // Clean up multiple spaces
        $json = preg_replace('/\s+/', ' ', $json);

        return $json;
    }

    /**
     * Check if data has required article fields
     */
    private function isValidArticleData(?array $data): bool
    {
        return $data && !empty($data['title']) && !empty($data['content']);
    }
}
