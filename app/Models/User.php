<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'role',
        'google_id',
        'password',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function shelter()
    {
        return $this->hasOne(Shelter::class);
    }

    public function adoptions()
    {
        return $this->hasMany(Adoption::class, 'adopter_id');
    }

    public function fraudReports()
    {
        return $this->hasMany(FraudReport::class, 'reporter_id');
    }

    public function kyc()
    {
        return $this->hasOne(ShelterKyc::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isShelter(): bool
    {
        return $this->role === 'shelter';
    }

    public function isAdopter(): bool
    {
        return $this->role === 'adopter';
    }
}
