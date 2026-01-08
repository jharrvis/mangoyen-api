<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Adoption;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// User-specific notification channel
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chat channel for adoption - only adopter and shelter owner can join
Broadcast::channel('chat.{adoptionId}', function ($user, $adoptionId) {
    $adoption = Adoption::with('cat.shelter')->find($adoptionId);
    if (!$adoption)
        return false;

    // Check if user is the adopter or shelter owner
    return $adoption->adopter_id === $user->id ||
        $adoption->cat->shelter->user_id === $user->id;
});
