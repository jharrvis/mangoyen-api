<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'category',
        'thumbnail',
        'meta_title',
        'meta_description',
        'reading_time',
        'view_count',
        'is_ai_generated',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_ai_generated' => 'boolean',
        'published_at' => 'datetime',
        'reading_time' => 'integer',
        'view_count' => 'integer',
    ];

    protected $appends = ['thumbnail_url'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($article) {
            if (!$article->slug) {
                $article->slug = Str::slug($article->title);
            }
            // Calculate reading time based on word count (avg 200 words per minute)
            if ($article->content && !$article->reading_time) {
                $wordCount = str_word_count(strip_tags($article->content));
                $article->reading_time = max(1, ceil($wordCount / 200));
            }
        });

        static::updating(function ($article) {
            // Recalculate reading time if content changed
            if ($article->isDirty('content') && $article->content) {
                $wordCount = str_word_count(strip_tags($article->content));
                $article->reading_time = max(1, ceil($wordCount / 200));
            }
        });
    }

    /**
     * Get the author of the article
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the tags for the article
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'article_tag')
            ->withTimestamps();
    }

    /**
     * Get the thumbnail URL
     */
    public function getThumbnailUrlAttribute()
    {
        if (!$this->thumbnail) {
            return null;
        }

        if (str_starts_with($this->thumbnail, 'http')) {
            return $this->thumbnail;
        }

        return url('/storage/' . $this->thumbnail);
    }

    /**
     * Increment view count
     */
    public function incrementViewCount()
    {
        $this->increment('view_count');
    }

    /**
     * Scope for published articles
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $category)
    {
        if ($category) {
            return $query->where('category', $category);
        }
        return $query;
    }

    /**
     * Scope to filter by tag
     */
    public function scopeByTag($query, $tagSlug)
    {
        if ($tagSlug) {
            return $query->whereHas('tags', function ($q) use ($tagSlug) {
                $q->where('slug', $tagSlug);
            });
        }
        return $query;
    }
}
