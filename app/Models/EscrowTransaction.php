<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscrowTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'adoption_id',
        'amount',
        'platform_fee',
        'payment_method',
        'payment_reference',
        'payment_status',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'snap_token',
        'paid_at',
        'released_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'paid_at' => 'datetime',
        'released_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function adoption()
    {
        return $this->belongsTo(Adoption::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isReleased(): bool
    {
        return $this->payment_status === 'released';
    }

    public function markAsPaid(string $method, ?string $reference = null): void
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_method' => $method,
            'payment_reference' => $reference,
            'paid_at' => now(),
        ]);
    }

    public function release(): void
    {
        $this->update([
            'payment_status' => 'released',
            'released_at' => now(),
        ]);
    }
}
