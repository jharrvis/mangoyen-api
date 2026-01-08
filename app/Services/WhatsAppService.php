<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Send a plain text message via Fonnte
     */
    public static function send($target, $message)
    {
        $token = config('services.fonnte.token');

        if (!$token) {
            Log::warning('Fonnte token not set. WA message skipped.');
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token
            ])->post('https://api.fonnte.com/send', [
                        'target' => $target,
                        'message' => $message,
                        'countryCode' => '62', // Default to Indonesia
                    ]);

            $result = $response->json();

            if ($response->successful() && ($result['status'] ?? false)) {
                return true;
            }

            Log::error('Fonnte API Error: ' . ($result['reason'] ?? 'Unknown error'));
            return false;

        } catch (\Exception $e) {
            Log::error('Fonnte Connection Error: ' . $e->getMessage());
            return false;
        }
    }
}
