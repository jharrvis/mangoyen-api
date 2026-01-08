<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    /**
     * Update user's last_seen_at on each request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Update last_seen_at for authenticated users (after response to not slow down)
        if ($request->user()) {
            // Only update every minute to reduce DB writes
            $user = $request->user();
            if (!$user->last_seen_at || $user->last_seen_at->diffInMinutes(now()) >= 1) {
                $user->last_seen_at = now();
                $user->saveQuietly(); // Don't trigger events
            }
        }

        return $response;
    }
}
