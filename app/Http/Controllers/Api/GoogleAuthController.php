<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth page
     */
    public function redirect()
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
        $driver = Socialite::driver('google');
        return $driver->stateless()->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback(Request $request)
    {
        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->user();

            // Find existing user by google_id or email
            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                // Update google_id if not set
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleUser->getId()]);
                }

                // Update avatar if changed
                if ($googleUser->getAvatar() && $user->avatar !== $googleUser->getAvatar()) {
                    $user->update(['avatar' => $googleUser->getAvatar()]);
                }
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => Hash::make(Str::random(24)), // Random password for OAuth users
                    'role' => 'adopter', // Default role
                    'email_verified_at' => now(), // Auto-verify for Google users
                ]);

                // Log activity for new registration
                ActivityLog::log(
                    'user_registered',
                    "{$user->name} mendaftar via Google",
                    $user,
                    $user,
                    ['method' => 'google', 'email' => $user->email]
                );
            }

            // Create Sanctum token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Redirect to frontend with token
            $frontendUrl = config('app.frontend_url');

            // Load shelter relationship for shelter users
            $user->load('shelter');

            return redirect()->away("{$frontendUrl}/auth/google/callback?token={$token}&user=" . urlencode(json_encode([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $user->role,
                'shelter' => $user->shelter,
            ])));

        } catch (\Exception $e) {
            $frontendUrl = config('app.frontend_url');
            return redirect()->away("{$frontendUrl}/login?error=" . urlencode('Google login gagal: ' . $e->getMessage()));
        }
    }
}
