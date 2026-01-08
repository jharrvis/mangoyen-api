<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AIModerator
{
    // OpenRouter API endpoint - supports multiple models including DeepSeek
    private static $openRouterUrl = 'https://openrouter.ai/api/v1/chat/completions';

    // Gemini API endpoint (fallback)
    private static $geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    /**
     * System prompt for contact exchange detection with adoption flow understanding
     */
    private static $systemPrompt = <<<PROMPT
PERAN: Kamu adalah MangOyen AI, moderator platform adopsi hewan peliharaan "anabul" (anak bulu: kucing, anjing, dll).

=== PEMAHAMAN FLOW ADOPSI ===
1. Calon adopter melihat profil hewan di platform
2. Calon adopter mengajukan permohonan adopsi 
3. Pemilik/shelter menyetujui permohonan
4. Chat dibuka antara kedua pihak
5. Diskusi seputar kondisi hewan, kebutuhan, jadwal serah terima
6. PEMBAYARAN adoption fee melalui platform (jika ada)
7. SETELAH PEMBAYARAN SELESAI, kontak boleh ditukar untuk koordinasi serah terima
8. Proses adopsi selesai

=== ATURAN YANG TIDAK BOLEH DILANGGAR ===
[RULE-01] NOMOR TELEPON: Dilarang berbagi nomor HP dalam bentuk APAPUN
  - Format biasa: 08123456789, +6281234567890
  - Disamarkan dengan teks: "kosong delapan", "nol delapan", "o delapan"
  - Disamarkan dengan titik: 0.8.1.2.3.4.5.6.7.8.9
  - Disamarkan dengan spasi: 0 8 1 2 3 4 5 6 7 8 9
  - Disamarkan dengan huruf: zeroeight, ohdelapan
  - Pecah menjadi bagian: "awalan 081, lanjut 234, sisanya 5678"

[RULE-02] EMAIL: Dilarang berbagi email
  - Format biasa: user@gmail.com
  - Disamarkan: "user at gmail dot com", "user (at) gmail"
  - Partial: "emailku julian123 di gmail"

[RULE-03] SOSIAL MEDIA: Dilarang berbagi username sosmed
  - IG/Instagram, WA/WhatsApp, Telegram/TG, Facebook/FB, TikTok
  - Username: @username, "ig: xxx", "cari aku di ig"

[RULE-04] AJAKAN KELUAR PLATFORM: Dilarang mengajak chat di luar
  - "DM aku", "PM aku", "inbox ya", "japri aku"
  - "lanjut di WA", "pindah ke telegram", "chat langsung aja"
  - "hubungi aku", "kontak saya", "telpon aku"

[RULE-05] ALAMAT LENGKAP: Dilarang berbagi alamat dengan detail
  - Alamat dengan nomor rumah dan RT/RW

[RULE-06] REKENING & TRANSFER: Dilarang berbagi rekening atau meminta transfer langsung
  - Nomor rekening: 10-16 digit angka, "norek", "no rek"
  - Nama bank: BCA, BNI, BRI, Mandiri, CIMB, Permata, Jago, Blu, Seabank, dll
  - Ajakan transfer: "transfer ke", "tf ke", "tt aja", "kirim ke rekening"
  - Meminta rekening: "minta norek", "kasih nomor rekening", "rekening kamu berapa"
  - E-wallet: OVO, GoPay, DANA, ShopeePay, LinkAja

[RULE-07] LINK EKSTERNAL: Dilarang berbagi link di luar platform
  - URL apapun (http, https, www, .com, .id, bit.ly, dll)

=== TOPIK YANG DIPERBOLEHKAN ===
- Diskusi kondisi kesehatan hewan
- Pertanyaan tentang makanan, vaksin, steril
- Jadwal serah terima (tanpa alamat detail)
- Negosiasi adoption fee
- Pertanyaan tentang karakter/perilaku hewan
- Salam, basa-basi yang sopan

=== EDUKASI ESCROW & KEAMANAN ===
Jika user mencoba bertransaksi di luar platform, EDUKASI dengan cara halus:

KENAPA HARUS PAKAI ESCROW MANGOYEN:
1. Dana ditahan platform sampai serah terima sukses
2. Perlindungan dari penipuan - uang kembali jika ada masalah
3. Bukti transaksi resmi dan bisa dilacak
4. Mediasi gratis jika ada sengketa
5. Garansi kondisi hewan sesuai deskripsi

TIPS AMAN TRANSAKSI:
- Jangan transfer langsung ke rekening pribadi
- Selalu gunakan fitur pembayaran di platform
- Verifikasi identitas pemilik/adopter
- Minta foto/video terbaru hewan sebelum deal
- Serah terima di tempat umum yang aman
- Bawa teman/keluarga saat ketemu

RISIKO TRANSAKSI DI LUAR PLATFORM:
- Uang hilang, hewan tidak dikirim (penipuan)
- Kondisi hewan tidak sesuai deskripsi
- Tidak ada bukti transaksi
- Tidak bisa komplain atau minta refund

=== FORMAT RESPONSE ===
Balas HANYA dengan JSON (tanpa markdown, tanpa penjelasan):
{
  "flagged": true/false,
  "rule_violated": "RULE-XX" atau null,
  "reason": "penjelasan singkat pelanggaran",
  "type": "phone/email/social/indirect/address/bank/url",
  "confidence": 0.0-1.0,
  "detected_pattern": "pola yang terdeteksi" atau null
}

PENTING: Jika ada KERAGUAN atau pola mencurigakan, lebih baik FLAG daripada loloskan!
PROMPT;

    /**
     * Moderate message using OpenRouter (DeepSeek) or Gemini as fallback
     */
    public static function moderate(string $message): array
    {
        $default = [
            'flagged' => false,
            'reason' => null,
            'type' => null,
            'confidence' => 0.0,
            'ai_checked' => false,
            'provider' => null,
        ];

        if (empty($message) || strlen($message) < 3) {
            return $default;
        }

        // Rate limiting
        $cacheKey = 'ai_mod_rate_' . request()->ip();
        $count = Cache::get($cacheKey, 0);
        if ($count >= 20) {
            Log::warning('AI moderation rate limit exceeded');
            return $default;
        }
        Cache::put($cacheKey, $count + 1, 60);

        // Try OpenRouter (DeepSeek) first
        $openRouterKey = config('services.openrouter.api_key');
        if (!empty($openRouterKey)) {
            $result = self::moderateWithOpenRouter($message, $openRouterKey);
            if ($result['ai_checked']) {
                return $result;
            }
        }

        // Fallback to Gemini
        $geminiKey = config('services.gemini.api_key');
        if (!empty($geminiKey)) {
            return self::moderateWithGemini($message, $geminiKey);
        }

        return $default;
    }

    /**
     * Moderate using OpenRouter (DeepSeek)
     */
    private static function moderateWithOpenRouter(string $message, string $apiKey): array
    {
        $default = ['flagged' => false, 'reason' => null, 'type' => null, 'confidence' => 0.0, 'ai_checked' => false, 'provider' => null];

        try {
            Log::info('OpenRouter moderating', ['message' => substr($message, 0, 50)]);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => 'MangOyen Pet Adoption',
                ])
                ->post(self::$openRouterUrl, [
                    'model' => 'deepseek/deepseek-chat', // DeepSeek Chat model
                    'messages' => [
                        ['role' => 'system', 'content' => self::$systemPrompt],
                        ['role' => 'user', 'content' => 'Pesan: "' . $message . '"'],
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 200,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? '';

                Log::info('OpenRouter response', ['text' => $text]);

                $result = self::parseResponse($text);
                $result['ai_checked'] = true;
                $result['provider'] = 'openrouter/deepseek';
                return $result;
            }

            Log::error('OpenRouter API error', ['status' => $response->status(), 'body' => $response->body()]);

        } catch (\Exception $e) {
            Log::error('OpenRouter failed', ['error' => $e->getMessage()]);
        }

        return $default;
    }

    /**
     * Moderate using Gemini (fallback)
     */
    private static function moderateWithGemini(string $message, string $apiKey): array
    {
        $default = ['flagged' => false, 'reason' => null, 'type' => null, 'confidence' => 0.0, 'ai_checked' => false, 'provider' => null];

        try {
            $response = Http::timeout(10)
                ->post(self::$geminiUrl . '?key=' . $apiKey, [
                    'contents' => [['parts' => [['text' => self::$systemPrompt . "\n\nPesan: \"$message\""]]]],
                    'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 200],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                $result = self::parseResponse($text);
                $result['ai_checked'] = true;
                $result['provider'] = 'gemini';
                return $result;
            }
        } catch (\Exception $e) {
            Log::error('Gemini failed', ['error' => $e->getMessage()]);
        }

        return $default;
    }

    /**
     * Parse AI response to JSON
     */
    private static function parseResponse(string $text): array
    {
        $default = [
            'flagged' => false,
            'reason' => null,
            'type' => null,
            'confidence' => 0.0,
            'rule_violated' => null,
            'detected_pattern' => null,
        ];

        $text = trim($text);

        // Remove markdown code blocks
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $m)) {
            $text = $m[1];
        }

        // Find JSON object - handle nested objects
        if (preg_match('/\{(?:[^{}]|(?:\{[^{}]*\}))*\}/s', $text, $m)) {
            $text = $m[0];
        }

        $json = json_decode($text, true);
        if (is_array($json)) {
            return [
                'flagged' => (bool) ($json['flagged'] ?? false),
                'reason' => $json['reason'] ?? null,
                'type' => $json['type'] ?? null,
                'confidence' => (float) ($json['confidence'] ?? 0.0),
                'rule_violated' => $json['rule_violated'] ?? null,
                'detected_pattern' => $json['detected_pattern'] ?? null,
            ];
        }

        // Fallback: detect flagged:true in text
        if (preg_match('/"flagged"\s*:\s*true/i', $text)) {
            // Try to extract rule from text
            $rule = null;
            if (preg_match('/RULE-0[1-7]/i', $text, $ruleMatch)) {
                $rule = strtoupper($ruleMatch[0]);
            }
            return [
                'flagged' => true,
                'reason' => 'Konten mencurigakan terdeteksi',
                'type' => 'indirect',
                'confidence' => 0.9,
                'rule_violated' => $rule,
                'detected_pattern' => null,
            ];
        }

        return $default;
    }

    /**
     * Generate MangOyen response with rule explanation
     */
    public static function generateMangoyenResponse(string $originalMessage, string $violationType, ?string $reason = null, ?string $ruleViolated = null): string
    {
        $fallback = "🐱 Meow Onti/Ongkel! Pesan kamu mengandung info yang belum boleh dibagi. Tukar kontak HANYA setelah transaksi selesai ya! Pakai Rekber biar aman 😾";

        $openRouterKey = config('services.openrouter.api_key');
        if (empty($openRouterKey)) {
            return $fallback;
        }

        // Build rule explanation if available
        $ruleExplanation = match ($ruleViolated) {
            'RULE-01' => 'nyebarin nomor HP',
            'RULE-02' => 'share email',
            'RULE-03' => 'kasih username sosmed',
            'RULE-04' => 'ngajak chat di luar platform',
            'RULE-05' => 'share alamat lengkap',
            'RULE-06' => 'share rekening/minta transfer',
            'RULE-07' => 'share link luar',
            default => 'share info kontak'
        };

        $prompt = "Kamu MangOyen, kucing oranye yang jadi admin platform adopsi anabul (anak bulu). 

KEPRIBADIAN:
- Friendly, tegas tapi nggak galak
- Pakai bahasa gaul kekinian Indonesia
- Sapaan: 'Meow!', 'Hai Onti!', 'Hai Ongkel!' (onti = auntie, ongkel = uncle)
- Panggil user dengan 'Onti' atau 'Ongkel'

ISTILAH:
- Anabul = Anak Bulu (kucing)
- Babu = Adopter (panggilan sayang)
- Majikan Lama = Shelter
- Rekber = Escrow (rekening bersama)

TUGAS: Buat pesan peringatan SINGKAT (max 3 kalimat) untuk user yang melanggar aturan.

KONTEKS:
- Pelanggaran: User mencoba $ruleExplanation sebelum pembayaran selesai
- Pesan asli: \"$originalMessage\"
- Detail: $reason

WAJIB INCLUDE:
1. Sebutkan pelanggarannya dengan halus
2. Ingatkan pakai fitur REKBER di platform untuk keamanan
3. Warning strike jika berulang

STYLE:
- Sapaan: 'Hai Onti!', 'Meow Ongkel!', 'Psst Onti/Ongkel!'
- Emoji kucing: 🐱 😸 😾 🙀 🐾

CONTOH OUTPUT:
- 🐱 Hai Onti! Share nomor HP belum boleh nih~ Yuk pakai Rekber MangOyen biar aman dari penipuan! Pelanggaran berulang = strike lho 😾
- 😸 Meow Ongkel! Mau tf langsung ya? Sabar dulu~ Pakai Rekber biar duit kamu aman. Kontak baru boleh setelah transaksi kelar! 🐾

BALAS LANGSUNG DENGAN PESAN MANGOYEN:";

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $openRouterKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::$openRouterUrl, [
                    'model' => 'deepseek/deepseek-chat',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.7,
                    'max_tokens' => 150,
                ]);

            if ($response->successful()) {
                $text = trim($response->json()['choices'][0]['message']['content'] ?? '');

                // Clean up AI meta-comments like "(Tetap singkat...)*"
                $text = preg_replace('/\*?\s*\(.*?(singkat|kalimat|emoji|warning|sesuai|permintaan).*?\)\s*\*?\s*😊?/iu', '', $text);
                $text = preg_replace('/\*\*.*?\*\*/u', '', $text); // Remove **bold** markers
                $text = trim($text);

                if (!empty($text) && strlen($text) > 20) {
                    return $text;
                }
            }
        } catch (\Exception $e) {
            Log::warning('MangOyen generation failed', ['error' => $e->getMessage()]);
        }

        return $fallback;
    }

    /**
     * Check if any AI provider is configured
     */
    public static function isConfigured(): bool
    {
        return !empty(config('services.openrouter.api_key')) || !empty(config('services.gemini.api_key'));
    }
}
