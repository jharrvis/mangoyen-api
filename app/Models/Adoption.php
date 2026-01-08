<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adoption extends Model
{
    use HasFactory;

    protected $fillable = [
        'adopter_id',
        'cat_id',
        'status',
        'notes',
        'rejection_reason',
        'adopter_address',
        'adopter_phone',
        'final_price',
        'price_negotiated_at',
        'shipping_deadline',
        'tracking_number',
        'shipping_proof',
        'shipped_at',
    ];

    protected $casts = [
        'final_price' => 'decimal:2',
        'price_negotiated_at' => 'datetime',
        'shipping_deadline' => 'datetime',
        'shipped_at' => 'datetime',
    ];

    public function adopter()
    {
        return $this->belongsTo(User::class, 'adopter_id');
    }

    public function cat()
    {
        return $this->belongsTo(Cat::class);
    }

    public function escrowTransaction()
    {
        return $this->hasOne(EscrowTransaction::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isShippingOverdue(): bool
    {
        return $this->status === 'payment'
            && $this->shipping_deadline
            && $this->shipping_deadline->isPast();
    }

    public function setShippingDeadline(int $days = 3): void
    {
        $this->update([
            'shipping_deadline' => now()->addDays($days)
        ]);
    }
}
