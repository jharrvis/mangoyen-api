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

    public function cat()
    {
        return $this->belongsTo(Cat::class);
    }
}
