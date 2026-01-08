<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeminiModerator
{
    private static $apiKey;
    // Using Gemini 2.0 Flash - stable model
    private static $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    /**
     * Custom prompt for contact exchange detection - ULTRA STRICT MODE
     */
    private static $systemPrompt = <<<PROMPT
PERAN: Kamu adalah SENSOR KETAT untuk platform adopsi. TUGAS: Deteksi dan BLOKIR semua upaya komunikasi di luar platform.

ATURAN UTAMA: Jika pesan mengandung SALAH SATU dari berikut, WAJIB flagged=true dengan confidence=0.95:

- Kata "DM", "PM", "inbox", "japri" = flagged (type: indirect)
- Kata "WA", "WhatsApp", "wa-an" = flagged (type: social)  
- Kata "IG", "Instagram", "igku" = flagged (type: social)
- Kata "Telegram", "TG", "tele" = flagged (type: social)
- Kata "hubungi", "kontak", "telpon", "call" = flagged (type: indirect)
- Kata "email", "gmail", "yahoo" = flagged (type: email)
- Kata "lanjut di", "pindah ke", "chat di" + platform lain = flagged (type: indirect)
- Angka 10+ digit = flagged (type: phone atau bank)
- Format email (xxx@xxx.xxx atau xxx at xxx) = flagged (type: email)

CONTOH WAJIB DI-FLAG:
- "DM aku di IG ya" = {"flagged":true,"reason":"Ajakan DM di Instagram","type":"indirect","confidence":0.95}
- "lanjut di wa aja" = {"flagged":true,"reason":"Ajakan pindah ke WhatsApp","type":"indirect","confidence":0.95}  
- "hubungi aku" = {"flagged":true,"reason":"Ajakan kontak di luar platform","type":"indirect","confidence":0.95}
- "email aku di gmail" = {"flagged":true,"reason":"Berbagi email","type":"email","confidence":0.95}

CONTOH TIDAK DI-FLAG:
- "kucing ini lucu sekali" = {"flagged":false,"reason":null,"type":null,"confidence":0.0}
- "kapan bisa ketemu?" = {"flagged":false,"reason":null,"type":null,"confidence":0.0}

BALAS HANYA JSON TANPA MARKDOWN, TANPA PENJELASAN:
{"flagged":true/false,"reason":"...","type":"...","confidence":0.0-1.0}
PROMPT;

    /**
     * Moderate a message using Gemini AI
     */
    public static function moderate(string $message): array
    {
        $defaultResult = [
            'flagged' => false,
            'reason' => null,
            'type' => null,
            'confidence' => 0.0,
            'ai_checked' => false,
        ];

        if (empty($message) || strlen($message) < 3) {
            return $defaultResult;
        }

        self::$apiKey = config('services.gemini.api_key');
        if (empty(self::$apiKey)) {
            Log::warning('Gemini API key not configured');
            return $defaultResult;
        }

        // Rate limiting
        $cacheKey = 'gemini_rate_limit_' . request()->ip();
        $requestCount = Cache::get($cacheKey, 0);
        if ($requestCount >= 15) {
            Log::warning('Gemini rate limit exceeded');
            return $defaultResult;
        }
        Cache::put($cacheKey, $requestCount + 1, 60);

        try {
            Log::info('Gemini moderating message', ['message' => substr($message, 0, 50)]);

            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(self::$baseUrl . '?key=' . self::$apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => self::$systemPrompt . "\n\nPESAN UNTUK DIMODERASI: \"" . $message . "\""],
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.0,
                        'maxOutputTokens' => 300, // Increased to avoid truncation
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                Log::info('Gemini raw response', ['response' => $text]);

                $result = self::parseResponse($text);
                $result['ai_checked'] = true;

                Log::info('Gemini moderation result', [
                    'message' => substr($message, 0, 100),
                    'result' => $result,
                ]);

                return $result;
            }

            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        } catch (\Exception $e) {
            Log::error('Gemini moderation failed', ['error' => $e->getMessage()]);
        }

        return $defaultResult;
    }

    /**
     * Parse Gemini response to extract JSON
     */
    private static function parseResponse(string $text): array
    {
        $default = [
            'flagged' => false,
            'reason' => null,
            'type' => null,
            'confidence' => 0.0,
        ];

        $originalText = $text;
        $text = trim($text);

        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
            $text = $matches[1];
        }

        // Also try to find JSON object directly
        if (preg_match('/\{[^}]+\}/', $text, $matches)) {
            $text = $matches[0];
        }

        try {
            $json = json_decode($text, true);
            if (is_array($json)) {
                return [
                    'flagged' => (bool) ($json['flagged'] ?? false),
                    'reason' => $json['reason'] ?? null,
                    'type' => $json['type'] ?? null,
                    'confidence' => (float) ($json['confidence'] ?? 0.0),
                ];
            }
        } catch (\Exception $e) {
            // Continue to fallback
        }

        // FALLBACK: If JSON parsing failed but response contains "flagged":true or "flagged": true
        // This handles truncated responses
        if (preg_match('/"flagged"\s*:\s*true/i', $originalText)) {
            Log::info('Gemini response truncated but flagged=true detected, flagging message');

            // Try to extract reason
            $reason = null;
            if (preg_match('/"reason"\s*:\s*"([^"]+)"/', $originalText, $reasonMatch)) {
                $reason = $reasonMatch[1];
            }

            // Try to extract type
            $type = null;
            if (preg_match('/"type"\s*:\s*"([^"]+)"/', $originalText, $typeMatch)) {
                $type = $typeMatch[1];
            }

            return [
                'flagged' => true,
                'reason' => $reason ?? 'Terdeteksi konten mencurigakan',
                'type' => $type ?? 'indirect',
                'confidence' => 0.9,
            ];
        }

        return $default;
    }

    /**
     * Check if Gemini is configured
     */
    public static function isConfigured(): bool
    {
        return !empty(config('services.gemini.api_key'));
    }

    /**
     * Generate a natural MangOyen response based on the violation
     */
    public static function generateMangoyenResponse(string $originalMessage, string $violationType, ?string $reason = null): string
    {
        $fallback = "🐱 Meow! Ini MangOyen. Pesanmu mengandung informasi yang tidak boleh dibagikan. Ingat ya, tukar kontak HANYA setelah pembayaran! 😾";

        if (!self::isConfigured()) {
            return $fallback;
        }

        $prompt = <<<PROMPT
Kamu adalah MangOyen, kucing oranye yang menjadi admin platform adopsi hewan. Karaktermu: ramah, tegas, sedikit lucu, suka pakai emoji kucing.

TUGAS: Buat pesan peringatan SINGKAT (max 2 kalimat) untuk user yang baru saja mencoba mengirim informasi terlarang.

KONTEKS:
- Pesan asli: "$originalMessage"
- Jenis pelanggaran: $violationType
- Alasan: $reason

ATURAN:
1. Mulai dengan emoji kucing dan sapaan khasmu (Meow!, Psst!, Hai!)
2. Jelaskan APA yang salah (sebutkan jenis pelanggarannya secara spesifik)
3. Ingatkan bahwa tukar kontak HANYA boleh SETELAH pembayaran selesai
4. Akhiri dengan peringatan tentang strike/ban
5. Gunakan bahasa gaul Indonesia yang ramah tapi tegas
6. Maksimal 2 kalimat!

BALAS LANGSUNG DENGAN PESAN MANGOYEN SAJA (tanpa tanda kutip):
PROMPT;

        try {
            $response = Http::timeout(5)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(self::$baseUrl . '?key=' . config('services.gemini.api_key'), [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 150,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
                $text = trim($text, '"\'');

                if (!empty($text) && strlen($text) > 20) {
                    return $text;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to generate MangOyen response', ['error' => $e->getMessage()]);
        }

        return $fallback;
    }
}
