<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'cat_id',
        'photo_path',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        if (!$this->photo_path) {
            return null;
        }

        // If already a full URL, return as-is
        if (str_starts_with($this->photo_path, 'http')) {
            return $this->photo_path;
        }

        // Generate full URL from storage path
        return asset('storage/' . $this->photo_path);
    }

    public function cat()
    {
        return $this->belongsTo(Cat::class);
    }
}
