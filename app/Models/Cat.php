<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Cat extends Model
{
    use HasFactory;

    protected $fillable = [
        'shelter_id',
        'name',
        'slug',
        'breed',
        'age_category',
        'age_months',
        'date_of_birth',
        'gender',
        'color',
        'weight',
        'description',
        'energy_level',
        'temperament',
        'good_with_kids',
        'good_with_cats',
        'good_with_dogs',
        'indoor_only',
        'tags',
        'health_status',
        'vaccination_status',
        'is_sterilized',
        'is_dewormed',
        'is_flea_free',
        'special_condition',
        'medical_notes',
        'vaccine_proof',
        'certificate',
        'awards',
        'youtube_url',
        'adoption_fee',
        'status',
        'is_urgent',
        'adoption_requirements',
        'body_type',
        'coat_length',
        'coat_pattern',
        'face_shape',
        'eye_color',
        'tail_type',
        'leg_type',
        'ear_type',
        'nose_type',
        'view_count',
        'saved_count',
        'price_visible',
        'is_negotiable',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'weight' => 'decimal:2',
        'is_sterilized' => 'boolean',
        'is_dewormed' => 'boolean',
        'is_flea_free' => 'boolean',
        'is_urgent' => 'boolean',
        'good_with_kids' => 'boolean',
        'good_with_cats' => 'boolean',
        'good_with_dogs' => 'boolean',
        'indoor_only' => 'boolean',
        'adoption_fee' => 'decimal:2',
        'tags' => 'array',
        'awards' => 'array',
        'price_visible' => 'boolean',
        'is_negotiable' => 'boolean',
    ];

    protected $appends = ['calculated_age', 'age_display'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cat) {
            if (!$cat->slug) {
                $cat->slug = Str::slug($cat->name) . '-' . Str::random(6);
            }
            // Auto calculate age_months from DOB
            if ($cat->date_of_birth && !$cat->age_months) {
                $cat->age_months = Carbon::parse($cat->date_of_birth)->diffInMonths(now());
            }
            // Auto set age_category based on age
            if ($cat->age_months) {
                $cat->age_category = $cat->age_months < 12 ? 'kitten' : 'adult';
            }
        });

        static::updating(function ($cat) {
            // Auto calculate age_months from DOB when updating
            if ($cat->date_of_birth) {
                $cat->age_months = Carbon::parse($cat->date_of_birth)->diffInMonths(now());
                $cat->age_category = $cat->age_months < 12 ? 'kitten' : 'adult';
            }
        });
    }

    // Accessor for calculated age
    public function getCalculatedAgeAttribute()
    {
        if ($this->date_of_birth) {
            return Carbon::parse($this->date_of_birth)->diffInMonths(now());
        }
        return $this->age_months;
    }

    // Accessor for display age (e.g., "2 Tahun 3 Bulan")
    public function getAgeDisplayAttribute()
    {
        $months = $this->calculated_age;
        if (!$months)
            return null;

        $years = floor($months / 12);
        $remainingMonths = $months % 12;

        if ($years > 0 && $remainingMonths > 0) {
            return "{$years} Tahun {$remainingMonths} Bulan";
        } elseif ($years > 0) {
            return "{$years} Tahun";
        } else {
            return "{$months} Bulan";
        }
    }

    public function shelter()
    {
        return $this->belongsTo(Shelter::class);
    }

    public function photos()
    {
        return $this->hasMany(CatPhoto::class);
    }

    public function primaryPhoto()
    {
        return $this->hasOne(CatPhoto::class)->where('is_primary', true);
    }

    public function adoptions()
    {
        return $this->hasMany(Adoption::class);
    }

    public function savedByUsers()
    {
        return $this->belongsToMany(User::class, 'cat_saves')->withTimestamps();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeByBreed($query, $breed)
    {
        if ($breed && $breed !== 'Semua') {
            return $query->where('breed', $breed);
        }
        return $query;
    }

    public function scopeByAge($query, $age)
    {
        if ($age && $age !== 'Semua') {
            return $query->where('age_category', strtolower($age));
        }
        return $query;
    }

    public function scopeUrgent($query)
    {
        return $query->where('is_urgent', true);
    }
}
