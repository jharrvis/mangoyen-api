<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'type',
        'description',
        'subject_type',
        'subject_id',
        'causer_id',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function subject()
    {
        return $this->morphTo();
    }

    public function causer()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * Log an activity
     */
    public static function log(string $type, string $description, $subject = null, $causer = null, array $properties = []): self
    {
        return static::create([
            'type' => $type,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'causer_id' => $causer?->id ?? auth()->id(),
            'properties' => $properties ?: null,
        ]);
    }

    /**
     * Get icon for activity type
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'user_registered' => '👤',
            'adoption_submitted' => '📝',
            'adoption_approved' => '✅',
            'adoption_rejected' => '❌',
            'cat_created' => '🐱',
            'payment_confirmed' => '💰',
            'shelter_verified' => '✔️',
            default => '📌',
        };
    }

    /**
     * Get color class for activity type
     */
    public function getColorAttribute(): string
    {
        return match ($this->type) {
            'user_registered' => 'blue',
            'adoption_submitted' => 'orange',
            'adoption_approved' => 'green',
            'adoption_rejected' => 'red',
            'cat_created' => 'purple',
            'payment_confirmed' => 'emerald',
            default => 'gray',
        };
    }
}
