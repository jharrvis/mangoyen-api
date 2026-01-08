<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use App\Models\EscrowTransaction;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Create Snap token for an adoption
     */
    public function createSnapToken(EscrowTransaction $transaction)
    {
        $adoption = $transaction->adoption;
        $adopter = $adoption->adopter;
        $cat = $adoption->cat;

        $params = [
            'transaction_details' => [
                'order_id' => 'ADOPT-' . $adoption->id . '-' . time(),
                'gross_amount' => (int) $transaction->amount,
            ],
            'customer_details' => [
                'first_name' => $adopter->name,
                'email' => $adopter->email,
                'phone' => $adopter->phone,
            ],
            'item_details' => [
                [
                    'id' => 'CAT-' . $cat->id,
                    'price' => (int) $transaction->amount,
                    'quantity' => 1,
                    'name' => 'Biaya Adopsi: ' . $cat->name,
                ]
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            $transaction->update([
                'midtrans_order_id' => $params['transaction_details']['order_id'],
                'snap_token' => $snapToken,
            ]);

            return $snapToken;
        } catch (\Exception $e) {
            Log::error('Midtrans Snap Error: ' . $e->getMessage());
            return null;
        }
    }
}
