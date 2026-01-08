<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'link',
        'reference_type',
        'reference_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Create notification for user and broadcast via Reverb
     */
    public static function notify(int $userId, string $type, string $title, string $message, ?string $link = null, $reference = null): self
    {
        $notification = static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
        ]);

        // Broadcast realtime notification via Reverb (gracefully fail if not configured)
        try {
            event(new \App\Events\NewNotification($notification));
        } catch (\Exception $e) {
            // Broadcasting failed - log but don't break the flow
            \Log::warning('Notification broadcast failed: ' . $e->getMessage());
        }

        return $notification;
    }

    /**
     * Get icon for notification type
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'adoption_request' => '📥',
            'adoption_approved' => '✅',
            'adoption_rejected' => '❌',
            'payment_received' => '💰',
            default => '🔔',
        };
    }
}
