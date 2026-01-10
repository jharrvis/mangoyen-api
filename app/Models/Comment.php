<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'user_id',
        'shelter_id',
        'guest_name',
        'guest_email',
        'parent_id',
        'content',
        'status',
        'approved_at',
        'approved_by',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    protected $appends = ['author_name', 'author_avatar', 'author_type'];

    // ============ Relationships ============

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shelter(): BelongsTo
    {
        return $this->belongsTo(Shelter::class, 'shelter_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->where('status', 'approved')
            ->orderBy('created_at', 'asc');
    }

    public function allReplies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->orderBy('created_at', 'asc');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ============ Scopes ============

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSpam($query)
    {
        return $query->where('status', 'spam');
    }

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    // ============ Accessors ============

    public function getAuthorNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        if ($this->shelter) {
            return $this->shelter->name ?? 'Shelter';
        }

        return $this->guest_name ?? 'Anonim';
    }

    public function getAuthorAvatarAttribute(): ?string
    {
        if ($this->user) {
            return $this->user->avatar ?? $this->user->profile_photo_url ?? null;
        }

        if ($this->shelter) {
            return $this->shelter->logo_url ?? null;
        }

        // Generate avatar from name for guests
        $name = urlencode($this->guest_name ?? 'A');
        return "https://ui-avatars.com/api/?name={$name}&background=f97316&color=fff&size=80";
    }

    public function getAuthorTypeAttribute(): string
    {
        if ($this->user_id) {
            $user = $this->user;
            if ($user && in_array($user->role, ['admin', 'superadmin'])) {
                return 'admin';
            }
            return 'user';
        }

        if ($this->shelter_id) {
            return 'shelter';
        }

        return 'guest';
    }

    // ============ Content Sanitization ============

    public static function sanitizeContent(string $content): string
    {
        // Remove HTML tags
        $content = strip_tags($content);

        // Convert special characters to HTML entities
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        // Trim and limit length
        $content = trim($content);
        $content = mb_substr($content, 0, 2000);

        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Convert line breaks to <br> for display (optional)
        // $content = nl2br($content);

        return $content;
    }

    // Boot method to auto-sanitize
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($comment) {
            $comment->content = self::sanitizeContent($comment->content);
        });

        static::updating(function ($comment) {
            if ($comment->isDirty('content')) {
                $comment->content = self::sanitizeContent($comment->content);
            }
        });
    }
}
