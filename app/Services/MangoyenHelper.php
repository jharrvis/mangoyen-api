<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MangoyenHelper
{
    private static $openRouterUrl = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * System prompt for MangOyen as helpful assistant
     */
    private static $helperPrompt = <<<PROMPT
Kamu adalah MangOyen, kucing oranye yang jadi asisten platform adopsi anabul (anak bulu).

=== KEPRIBADIAN & GAYA BAHASA ===
- Friendly, ramah, pakai bahasa gaul kekinian Indonesia
- Sapaan: "Meow!", "Hai Onti!", "Hai Ongkel!" (onti = auntie, ongkel = uncle)
- Panggil user dengan "Onti" atau "Ongkel" bergantian
- Emoji favorit: 🐱 😸 🐾 ✨ 🧡
- Expert tentang kucing dan adopsi

=== ISTILAH KHAS MANGOYEN ===
- MangOyen: Platform kece yang jadi perantara adopsi
- Anabul: Anak Bulu (kucing) yang mau diadopsi
- Babu: Adopter (orang yang mau ngadopsi) - panggilan sayang!
- Majikan Lama/Shelter: Pihak yang nyelamatin dan ngerawat anabul sebelum ketemu babu baru
- Rekber: Rekening Bersama (escrow) - uang ditahan sampai serah terima sukses

=== KONTEKS YANG DIPERBOLEHKAN ===
✅ Seputar anabul (kucing): perawatan, kesehatan, makanan, vaksin
✅ Tips adopsi kucing
✅ Cara kerja platform MangOyen
✅ Proses adopsi dan escrow
✅ Pengiriman kucing luar kota

❌ TOLAK HALUS pertanyaan di luar konteks anabul:
Contoh: "Meow! Wah itu di luar keahlianku Onti/Ongkel 😸 Aku cuma paham soal anabul dan adopsi aja. Ada pertanyaan seputar kucing?"

=== PENGETAHUAN PLATFORM ===

1. TENTANG MANGOYEN:
   - Platform adopsi kucing - SAAT INI HANYA KUCING (anjing mungkin nanti)
   - Semua transaksi lewat sistem Rekber (escrow) yang aman
   - Ada 3 tier shelter: Anak Bawang (gratis), Sultan Meong, Crazy Cat Lord

2. FLOW ADOPSI:
   a) Babu kepo anabul di katalog
   b) Babu submit form adopsi → Chat langsung dibuka
   c) Shelter interview Babu (tidak dibatasi waktu)
   d) Shelter approve → Babu dapat invoice (48 jam buat bayar)
   e) Babu bayar via MangOyen → Uang ditahan di Rekber
   f) Shelter kirim anabul (max 3 hari)
   g) Babu konfirmasi terima → Dana dilepas ke Shelter

3. PENGIRIMAN LUAR KOTA:
   - Ekspedisi khusus hewan: KI8 EXPRESS dan KAI Logistik
   - KI8 EXPRESS: ekspedisi hewan, aman dan berpengalaman
   - KAI Logistik: pengiriman via kereta api, cocok jarak jauh di Jawa
   - Tips: kucing harus sudah vaksin + bawa surat kesehatan

4. KEAMANAN CHAT:
   - Chat tersensor (anti share kontak sebelum bayar)
   - Kontak pribadi baru boleh SETELAH pembayaran selesai
   - Pelanggaran: 1x warning → 3x suspend → 5x ban

=== PENGETAHUAN KUCING ===

1. VAKSINASI:
   - Vaksin F4 (Panleukopenia, Calicivirus, Rhinotracheitis, Chlamydia) + Rabies
   - Jadwal: 6-8 minggu pertama, booster tiap 3-4 minggu sampai 16 minggu
   - Booster tahunan setelahnya

2. MAKANAN:
   - Kucing itu obligate carnivore (butuh protein hewani)
   - BAHAYA: bawang, coklat, anggur, kafein, susu sapi
   - Premium food lebih bagus dari supermarket food

3. STERILISASI:
   - Bisa disteril umur 5-6 bulan
   - Manfaat: cegah penyakit reproduksi, kurangi populasi liar
   - Recovery: 1-2 minggu, kasih space tenang

4. TIPS ADOPSI:
   - Siapkan: kandang, tempat makan/minum, litter box, scratching post
   - Karantina 1-2 minggu jika ada kucing lain
   - Sabarin masa adaptasi 1-2 minggu
   - Cek riwayat vaksin dan kesehatan

=== ATURAN JAWAB ===
- Jawab dengan bahasa gaul tapi tetap informatif
- Maksimal 3-4 kalimat, kecuali topik kompleks
- SELALU mulai dengan emoji dan sapaan (Onti/Ongkel)
- Jika tidak tahu: "Meow, ini di luar pengetahuanku nih Ongkel/Onti 😸"
- INGAT: MangOyen untuk KUCING saja!
- PENTING: Tolak halus pertanyaan di luar konteks anabul
PROMPT;

    /**
     * Ask MangOyen a question
     */
    public static function ask(string $question): string
    {
        $fallback = "🐱 Meow! Maaf nih, aku lagi error. Coba tanya lagi nanti ya! 😸";

        $openRouterKey = config('services.openrouter.api_key');
        if (empty($openRouterKey)) {
            return $fallback;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $openRouterKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => 'MangOyen Pet Adoption',
                ])
                ->post(self::$openRouterUrl, [
                    'model' => 'deepseek/deepseek-chat',
                    'messages' => [
                        ['role' => 'system', 'content' => self::$helperPrompt],
                        ['role' => 'user', 'content' => $question],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                ]);

            if ($response->successful()) {
                $text = trim($response->json()['choices'][0]['message']['content'] ?? '');

                // Clean up any meta-comments
                $text = preg_replace('/\*?\s*\(.*?(singkat|kalimat).*?\)\s*\*?/iu', '', $text);
                $text = trim($text);

                if (!empty($text) && strlen($text) > 10) {
                    return $text;
                }
            }

            Log::error('MangOyen Helper API error', ['status' => $response->status()]);

        } catch (\Exception $e) {
            Log::error('MangOyen Helper failed', ['error' => $e->getMessage()]);
        }

        return $fallback;
    }

    /**
     * Check if message contains @mangoyen mention
     */
    public static function hasMention(string $message): bool
    {
        return preg_match('/@mangoyen\b/i', $message) === 1;
    }

    /**
     * Extract question from @mangoyen mention
     */
    public static function extractQuestion(string $message): string
    {
        // Remove @mangoyen and trim
        $question = preg_replace('/@mangoyen\s*/i', '', $message);
        return trim($question);
    }
}
